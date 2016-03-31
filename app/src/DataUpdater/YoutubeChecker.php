<?php

namespace Mindcrack\Site\DataUpdater;

use Bolt\Application;
use Bolt\Content;
use Bolt\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Message\ResponseInterface;
use React\Promise\ExtendedPromiseInterface;

class YoutubeChecker {

	/**
	 * Max amount of users to request from youtube per update.
	 *
	 * Not really sure what the limit is though. For channels.list resource
	 * the limit for maxResults is 50 but I think it's ignored for requests
	 * with a list of IDs
	 *
	 * @var int
	 */
	static public $usersPerRequest = 40;

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @var array
	 */
	protected $queryDefaults;

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
	 * @param Content|Content[] $people
	 *
	 * @return ExtendedPromiseInterface
	 */
	public function update($people) {
		if (!is_array($people)) {
			$people = [$people];
		}

		try {
			$this->setupClient();
		} catch (\Exception $e) {
			return \React\Promise\reject($e);
		}

		$keyedPeople = $this->keyPeopleByYTID($people);

		$promise = $this->requestYoutubeData($keyedPeople);
		$promise = $this->parseYoutubeResults($promise, $keyedPeople);

		return $promise;
	}

	/**
	 * Update all youtube stats
	 *
	 * @return ExtendedPromiseInterface
	 */
	public function updateAll() {
		$people = $this->getPeopleToUpdate();
		$promise = \React\Promise\map($people, [$this, 'update']);

		return \React\Promise\reduce($promise, function ($total, $updated) {
			return $total + $updated;
		}, 0);
	}

	/**
	 * Set up guzzle client for youtube
	 */
	protected function setupClient() {
		if ($this->youtubeClient) {
			return;
		}

		if (!($api_key = $this->app['config']->get('general/services/youtube'))) {
			throw new \Exception('No Youtube API key configured (config/services/youtube)');
		}

		$this->youtubeClient = new Client([
			'base_url' => 'https://www.googleapis.com/youtube/v3/',
		]);
		$this->queryDefaults = [
			'key' => $api_key,
		];

	}

	/**
	 * Get the list of people that should be updated
	 *
	 * @returns ExtendedPromiseInterface
	 */
	protected function getPeopleToUpdate() {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		$people = $storage->getContent('people', ['youtube_channel_id' => '!']);

		$chunked = array_chunk($people, self::$usersPerRequest);

		return $chunked;
	}

	/**
	 * Keys an array of people by youtube ID
	 *
	 * @param Content[] $people
	 * @param string    $key
	 *
	 * @return array
	 */
	protected function keyPeopleByYTID($people, $key = 'youtube_channel_id') {
		$keyed = [];

		foreach ($people as $person) {
			$keyed[$person->values[$key]] = $person;
		}

		return $keyed;
	}

	/**
	 * Request data from youtube based on keyed people list
	 *
	 * @param Content[] $keyedPeople
	 *
	 * @return ExtendedPromiseInterface
	 */
	protected function requestYoutubeData($keyedPeople) {

		return \React\Promise\resolve($this->youtubeClient->get('channels', [
			'future' => true,
			'query'  => array_replace_recursive($this->queryDefaults, [
				'id'   => implode(',', array_keys($keyedPeople)),
				'part' => 'statistics',
				'fields' => 'items(id,statistics(viewCount,subscriberCount,videoCount))'
			]),
		]));
	}

	/**
	 * Parse the results from the youtube response
	 *
	 * @param ExtendedPromiseInterface $promise
	 * @param Content[]                $keyedPeople
	 *
	 * @return \React\Promise\PromiseInterface
	 */
	protected function parseYoutubeResults(ExtendedPromiseInterface $promise, $keyedPeople) {
		return $promise->then(function (ResponseInterface $response) use ($keyedPeople) {
			$answer = $response->json();

			$updateCount = 0;
			array_map(function ($channel) use ($keyedPeople, &$updateCount) {
				$dbChannel = $keyedPeople[$channel['id']];
				if (!$dbChannel) {
					return;
				}

				$dbChannel->values['youtube_subscribers'] = $channel['statistics']['subscriberCount'];
				$dbChannel->values['youtube_videos'] = $channel['statistics']['videoCount'];
				$dbChannel->values['youtube_views'] = $channel['statistics']['viewCount'];

				$this->storeUpdatedPerson($dbChannel);

				$updateCount++;
			}, $answer['items']);

			return $updateCount;
		})->otherwise(function (BadResponseException $error) {
			if ($error->hasResponse()) {
				$response = $error->getResponse()->json();
				throw new \Exception('Youtube Error: ' . $response['error']['message']);
			} else {
				throw $error;
			}
		});

	}

	/**
	 * Store updated person info in the database
	 *
	 * @param Content $dbChannel
	 */
	protected function storeUpdatedPerson(Content $person) {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		$storage->saveContent($person, 'Update by YouTube Checker');
	}

}