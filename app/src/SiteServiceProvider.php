<?php

namespace Mindcrack\Site;


use Bolt\Config;
use Bolt\Events\CronEvent;
use Bolt\Events\CronEvents;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Nut;
use Bolt\Storage\FieldManager;
use Mindcrack\Site\Commands\DataUpdaterCommand;
use Mindcrack\Site\DataUpdater\Runner;
use Mindcrack\Site\DataUpdater\YoutubeChecker;
use Mindcrack\Site\Extensions\AssetVersioningExtension;
use Mindcrack\Site\Field\BigIntegerField;
use Mindcrack\Site\Field\TimeZoneField;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Umpirsky\Twig\Extension\PhpFunctionExtension;

class SiteServiceProvider implements ServiceProviderInterface {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * Registers services on the given app.
	 * This method should only be used to configure services and parameters.
	 * It should not get services.
	 *
	 * @param Application $app
	 */
	public function register(Application $app) {
		$this->app = $app;

		$this->registerCustomFields();
		$this->registerCommands();
	}

	/**
	 * Bootstraps the application.
	 * This method is called after all services are registered
	 * and should be used for "dynamic" configuration (whenever
	 * a service must be requested).
	 *
	 * @param Application $app
	 */
	public function boot(Application $app) {
		$this->installTwigExtensions();
		$this->watchForUpdates();
		$this->registerCronListeners();
	}

	/**
	 * Registers nut commands
	 */
	protected function registerCommands() {
		$this->app['nut.commands.add'](function ($app) {
			return [
				new DataUpdaterCommand($app),
			];
		});
	}

	/**
	 * Install twig extensions
	 */
	protected function installTwigExtensions() {
		$app = $this->app;
		$app['twig']->addExtension(new PhpFunctionExtension(['timezone_identifiers_list']));
		$app['twig']->addExtension(new AssetVersioningExtension($app));
	}

	/**
	 * Registers custom fields onto the application
	 */
	protected function registerCustomFields() {
		$app = $this->app;

		$app['twig.loader.filesystem']->prependPath(__DIR__ . '/Field', 'bolt');

		// Advanced
		$app['storage.typemap'] = array_merge(
			$app['storage.typemap'],
			[
				'biginteger' => BigIntegerField::class,
			]
		);

		$app['storage.field_manager'] = $app->share(
			$app->extend(
				'storage.field_manager',
				function (FieldManager $manager) {
					$manager->addFieldType('biginteger', new BigIntegerField());

					return $manager;
				}
			)
		);

		/** @var Config $config */
		$config = $app['config'];

		$config->getFields()->addField(new TimeZoneField());

	}

	/**
	 * Watch for updated items
	 */
	protected function watchForUpdates() {
		$app = $this->app;
		/** @var EventDispatcher $dispatcher */
		$dispatcher = $app['dispatcher'];

		$dispatcher->addListener(StorageEvents::POST_SAVE, function (StorageEvent $event) use ($app) {
			$this->handleUpdateEvent($app, $event);
		});
	}

	/**
	 * Handler for updated items
	 *
	 * @param Application  $app
	 * @param StorageEvent $event
	 */
	public function handleUpdateEvent(Application $app, StorageEvent $event) {
		// Member creation
		if ($event->getContentType() == 'members' && $event->isCreate()) {
			$updater = new YoutubeChecker($app);

			$updater->update([$event->getContent()]);
		}
	}

	/**
	 * Register cron listeners
	 */
	protected function registerCronListeners() {
		$app = $this->app;
		$app['dispatcher']->addListener(CronEvents::CRON_HOURLY, [$this, 'runHourlyCronUpdate']);
	}

	/**
	 * Run hourly cron updates
	 * @param CronEvent $event
	 * @throws \Exception
	 */
	public function runHourlyCronUpdate(CronEvent $event) {
		$app = $this->app;
		// Fetch youtube info
		if ($event->output) {
			$event->output->writeln("<comment>    Checking youtube for updates...</comment>");
		}

		$runner = new Runner($app);
		$results = $runner->run();
		$results->then(null, function ($error) use ($app) {
			$app['logger.system']->error($error->getMessage(), ['event' => 'cron']);
		})->wait(false);
	}
}