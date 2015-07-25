<?php

namespace Mindcrack\Site;


use Bolt\Config;
use Mindcrack\Site\Field\BigIntegerField;
use Silex\Application;
use Silex\ServiceProviderInterface;

class SiteServiceProvider implements ServiceProviderInterface {

	/**
	 * Registers services on the given app.
	 * This method should only be used to configure services and parameters.
	 * It should not get services.
	 */
	public function register(Application $app) {
		// TODO: Implement register() method.
	}

	/**
	 * Bootstraps the application.
	 * This method is called after all services are registered
	 * and should be used for "dynamic" configuration (whenever
	 * a service must be requested).
	 */
	public function boot(Application $app) {
		/** @var Config $config */
		$config = $app['config'];

		$config->getFields()->addField(new BigIntegerField());

		$app['twig.loader.filesystem']->prependPath(__DIR__ . '/Field', 'MindcrackSiteFields');
	}
}