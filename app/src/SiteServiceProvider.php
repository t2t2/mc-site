<?php

namespace Mindcrack\Site;


use Bolt\Config;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Nut;
use Mindcrack\Site\Commands\DataUpdaterCommand;
use Mindcrack\Site\DataUpdater\YoutubeChecker;
use Mindcrack\Site\Field\BigIntegerField;
use Mindcrack\Site\Field\TimeZoneField;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
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
		$this->registerCustomFields($app);
		$this->watchForUpdates($app);
	}

	/**
	 * Registers nut commands
	 * Soooo... BaseCommand likes to rewrite the request object on every new object initialised... yeaahh...
	 * In addition the way to register commands is just a big nesting doll of arrays.
	 *
	 * @param Application $app
	 */
	protected function registerCommands(Application $app) {
		$app['nut.commands'] = $app->share(function ($app) {
			// Get the current request for default commands
			try {
				$request = $app['request'];
			} catch (\Exception $e) {
				$request = Request::createFromGlobals();
			}

			return [
				new Nut\CronRunner($app, $request),
				new Nut\CacheClear($app, $request),
				new Nut\Info($app, $request),
				new Nut\LogTrim($app, $request),
				new Nut\LogClear($app, $request),
				new Nut\DatabaseCheck($app, $request),
				new Nut\DatabaseExport($app, $request),
				new Nut\DatabaseImport($app, $request),
				new Nut\DatabasePrefill($app, $request),
				new Nut\DatabaseRepair($app, $request),
				new Nut\TestRunner($app, $request),
				new Nut\ConfigGet($app, $request),
				new Nut\ConfigSet($app, $request),
				new Nut\Extensions($app, $request),
				new Nut\ExtensionsAutoloader($app, $request),
				new Nut\ExtensionsEnable($app, $request),
				new Nut\ExtensionsDisable($app, $request),
				new Nut\UserAdd($app, $request),
				new Nut\UserRoleAdd($app, $request),
				new Nut\UserRoleRemove($app, $request),
				// Custom Commands
				new DataUpdaterCommand($app),
			];
		});
	}

	protected function installTwigExtensions(Application $app) {
		$app['twig']->addExtension(new PhpFunctionExtension(['timezone_identifiers_list']));
	}

	/**
	 * Registers custom fields onto the application
	 *
	 * @param Application $app
	 */
	protected function registerCustomFields(Application $app) {
		/** @var Config $config */
		$config = $app['config'];

		$config->getFields()->addField(new BigIntegerField());
		$config->getFields()->addField(new TimeZoneField());

		$app['twig.loader.filesystem']->prependPath(__DIR__ . '/Field', 'MindcrackSiteFields');
	}

	protected function watchForUpdates(Application $app) {
		/** @var EventDispatcher $dispatcher */
		$dispatcher = $app['dispatcher'];

		$dispatcher->addListener(StorageEvents::POST_SAVE, function(StorageEvent $event) use($app) {
			$this->handleUpdateEvent($app, $event);
		});
	}

	public function handleUpdateEvent(Application $app, StorageEvent $event) {
		// Member creation
		if ($event->getContentType() == 'members' && $event->isCreate()) {
			$updater = new YoutubeChecker($app);

			$updater->update([$event->getContent()]);
		}
	}
}