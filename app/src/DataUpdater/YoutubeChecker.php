<?php

namespace Mindcrack\Site\DataUpdater;

use Bolt\Application;
use Bolt\Storage;
use GuzzleHttp\Client;

class YoutubeChecker {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @var Client
	 */
	protected $youtubeClient;

	/**
	 * @param Application $app
	 */
	function __construct(Application $app) {
		$this->app = $app;

	}

	/**
	 * Update all youtube stats
	 */
	public function update() {
		try {
			$this->setupClient();
		} catch (\Exception $e) {
			return \React\Promise\reject($e);
		}

		$promise = $this->getPeopleToUpdate();
	}

	/**
	 * Set up guzzle client for youtube
	 */
	protected function setupClient() {
		if (!($api_key = $this->app['config']->get('general/services/youtube'))) {
			throw new \Exception('No Youtube API key configured (config/services/youtube)');
		}

		$this->youtubeClient = new Client([
			'base_url' => 'https://www.googleapis.com/youtube/v3/',
			'query'    => [
				'key' => $api_key
			]
		]);

	}

	/**
	 *
	 */
	protected function getPeopleToUpdate() {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		$people = $storage->getContent('people', ['youtube_channel_id' => '!']);

	}

}