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
	 * @param Content|Content[] $members
	 *
	 * @return ExtendedPromiseInterface
	 */
	public function update($members) {
		if (!is_array($members)) {
			$members = [$members];
		}

		try {
			$this->setupClient();
		} catch (\Exception $e) {
			return \React\Promise\reject($e);
		}

		$keyedMembers = $this->keyMembersByYTID($members);

		$promise = $this->requestYoutubeData($keyedMembers);
		$promise = $this->parseYoutubeResults($promise, $keyedMembers);

		return $promise;
	}

	/**
	 * Update all youtube stats
	 *
	 * @return ExtendedPromiseInterface
	 */
	public function updateAll() {
		$members = $this->getMembersToUpdate();
		$promise = \React\Promise\map($members, [$this, 'update']);

		$total = \React\Promise\reduce($promise, function ($total, $updated) {
			return $total + $updated;
		}, 0);

		return $total->then(function ($updated) {
			// Store updated so it's sent back once done
			return $this->updateTotals()->then(function () use ($updated) {
				return $updated;
			});
		});
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
	 * Get the list of members that should be updated
	 *
	 * @returns ExtendedPromiseInterface
	 */
	protected function getMembersToUpdate() {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		$members = $storage->getContent('members', ['youtube_channel_id' => '!']);

		$chunked = array_chunk($members, self::$usersPerRequest);

		return $chunked;
	}

	/**
	 * Keys an array of members by youtube ID
	 *
	 * @param Content[] $members
	 * @param string    $key
	 *
	 * @return array
	 */
	protected function keyMembersByYTID($members, $key = 'youtube_channel_id') {
		$keyed = [];

		foreach ($members as $member) {
			$keyed[$member->values[$key]] = $member;
		}

		return $keyed;
	}

	/**
	 * Request data from youtube based on keyed members list
	 *
	 * @param Content[] $keyedMembers
	 *
	 * @return ExtendedPromiseInterface
	 */
	protected function requestYoutubeData($keyedMembers) {

		return \React\Promise\resolve($this->youtubeClient->get('channels', [
			'future' => true,
			'query' => array_replace_recursive($this->queryDefaults, [
				'id' => implode(',', array_keys($keyedMembers)),
				'part' => 'brandingSettings,statistics',
				'fields' => 'items(id,brandingSettings(channel(unsubscribedTrailer)),statistics(viewCount,subscriberCount,videoCount))'
			]),
		]));
	}

	/**
	 * Parse the results from the youtube response
	 *
	 * @param ExtendedPromiseInterface $promise
	 * @param Content[]                $keyedMembers
	 *
	 * @return \React\Promise\PromiseInterface
	 */
	protected function parseYoutubeResults(ExtendedPromiseInterface $promise, $keyedMembers) {
		return $promise->then(function (ResponseInterface $response) use ($keyedMembers) {
			$answer = $response->json();

			$updateCount = 0;
			array_map(function ($channel) use ($keyedMembers, &$updateCount) {
				$dbChannel = $keyedMembers[$channel['id']];
				if (!$dbChannel) {
					return;
				}

				$dbChannel->values['youtube_subscribers'] = $channel['statistics']['subscriberCount'];
				$dbChannel->values['youtube_videos'] = $channel['statistics']['videoCount'];
				$dbChannel->values['youtube_views'] = $channel['statistics']['viewCount'];
				$dbChannel->values['youtube_trailer'] = $channel['brandingSettings']['channel']['unsubscribedTrailer'];

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
	 * Store updated member info in the database
	 *
	 * @param Content $member
	 */
	protected function storeUpdatedPerson(Content $member) {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		$storage->saveContent($member, 'Update by YouTube Checker');
	}

	/**
	 * Update Totals
	 *
	 * Updates totals on the home page once the promise is done
	 *
	 * @return ExtendedPromiseInterface
	 **/
	public function updateTotals() {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		return \React\Promise\resolve()->then(function () use ($storage) {
			$members = $storage->getContent('members');

			$pages_to_update = $storage->getContent('pages', ['template' => 'home.twig']);

			$totals = array_reduce($members, function ($totals, $member) {
				$totals['youtube_subscribers'] += $member['youtube_subscribers'];
				$totals['youtube_videos'] += $member['youtube_videos'];
				$totals['youtube_views'] += $member['youtube_views'];

				return $totals;
			}, ['youtube_subscribers' => 0, 'youtube_videos' => 0, 'youtube_views' => 0]);

			array_map(function ($page) use ($totals, $storage) {
				$page['templatefields']['youtube_subscribers'] = $totals['youtube_subscribers'];
				$page['templatefields']['youtube_videos'] = $totals['youtube_videos'];
				$page['templatefields']['youtube_views'] = $totals['youtube_views'];

				$storage->saveContent($page, 'Update by YouTube Checker');
			}, $pages_to_update);
		});
	}

}