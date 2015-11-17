<?php

namespace Mindcrack\Site;


use Bolt\Config;
use Bolt\Nut;
use Mindcrack\Site\Commands\DataUpdaterCommand;
use Mindcrack\Site\Field\BigIntegerField;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

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
		$this->registerCustomFields($app);
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
			} catch(\Exception $e) {
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

	/**
	 * Registers custom fields onto the application
	 *
	 * @param Application $app
	 */
	protected function registerCustomFields(Application $app) {
		/** @var Config $config */
		$config = $app['config'];

		$config->getFields()->addField(new BigIntegerField());

		$app['twig.loader.filesystem']->prependPath(__DIR__ . '/Field', 'MindcrackSiteFields');
	}
}