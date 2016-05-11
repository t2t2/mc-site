<?php

namespace Mindcrack\Site;


use Bolt\Config;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Nut;
use Bolt\Storage\FieldManager;
use Mindcrack\Site\Commands\DataUpdaterCommand;
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
	 * Registers services on the given app.
	 * This method should only be used to configure services and parameters.
	 * It should not get services.
	 *
	 * @param Application $app
	 */
	public function register(Application $app) {
		$this->registerCustomFields($app);
		$this->registerCommands($app);
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
		$this->installTwigExtensions($app);
		$this->watchForUpdates($app);
	}

	/**
	 * Registers nut commands
	 *
	 * @param Application $app
	 */
	protected function registerCommands(Application $app) {
		$app['nut.commands.add'](function ($app) {
			return [
				new DataUpdaterCommand($app),
			];
		}) ;
	}

	/**
	 * Install twig extensions
	 *
	 * @param Application $app
	 */
	protected function installTwigExtensions(Application $app) {
		$app['twig']->addExtension(new PhpFunctionExtension(['timezone_identifiers_list']));
		$app['twig']->addExtension(new AssetVersioningExtension($app));
	}

	/**
	 * Registers custom fields onto the application
	 *
	 * @param Application $app
	 */
	protected function registerCustomFields(Application $app) {
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
	 *
	 * @param Application $app
	 */
	protected function watchForUpdates(Application $app) {
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
}