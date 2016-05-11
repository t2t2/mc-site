<?php

namespace Mindcrack\Site\Controllers;


use Bolt\Controller\Frontend;
use Bolt\Storage;
use Silex\Application;

class Calendar {

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
		$globals = [
			'records'=> $content,
			$contentType['slug']=> $content,
			'contenttype'=> $contentType['name']
		];

		return $app['render']->render($template, [], $globals);
	}
}