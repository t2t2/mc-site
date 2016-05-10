<?php

namespace Mindcrack\Site\Controllers;


use Bolt\Controller\Frontend;
use Bolt\Storage;
use Silex\Application;

class Calendar extends Frontend {

	/**
	 * Get the upcoming events
	 * 
	 * @param Application $app
	 * @param             $contentTypeSlug
	 * @param string      $template
	 *
	 * @return \Twig_Markup
	 */
	public function upcoming(Application $app, $contentTypeSlug, $template = 'listing.twig') {
		/** @var Storage $storage */
		$storage = $app['storage'];
		$contentType = $storage->getContentType($contentTypeSlug);
		
		$content = $storage->getContent($contentType['slug'], [
			'event_time' => '> 3 days ago',
			'limit' => 5,
			'order' => 'event_time',
			'paging' => true
		]);
		
		// Make sure we can also access it as {{ pages }} for pages, etc. We set these in the global scope,
		// So that they're also available in menu's and templates rendered by extensions.
		$app['twig']->addGlobal('records', $content);
		$app['twig']->addGlobal($contentType['slug'], $content);
		$app['twig']->addGlobal('contenttype', $contentType['name']);

		// Group the data into months
		$groupedByMonth = [];
		
		
		
		return $this->render($app, $template, $contentType);
	}
}