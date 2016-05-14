<?php

namespace Mindcrack\Site\DataUpdater;

use Bolt\Application;
use Bolt\Legacy\Content;
use Bolt\Legacy\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;

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
	 * @return PromiseInterface
	 */
	public function update($members) {
		if (!is_array($members)) {
			$members = [$members];
		}

		try {
			$this->setupClient();
		} catch (\Exception $e) {
			return \GuzzleHttp\Promise\rejection_for($e);
		}

		$keyedMembers = $this->keyMembersByYTID($members);

		$promise = $this->requestYoutubeData($keyedMembers);
		$promise = $this->parseYoutubeResults($promise, $keyedMembers);

		return $promise;
	}

	/**
	 * Update all youtube stats
	 *
	 * @return PromiseInterface
	 */
	public function updateAll() {
		$members = $this->getMembersToUpdate();
		$promise = \GuzzleHttp\Promise\all(array_map([$this, 'update'], $members));

		return $promise->then(function ($results) {
			$updated = array_sum($results);
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
			'base_uri' => 'https://www.googleapis.com/youtube/v3/',
			'query' => [
				'key' => $api_key,
			]
		]);
	}

	/**
	 * Get the list of members that should be updated
	 *
	 * @returns PromiseInterface
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
	 * @return PromiseInterface
	 */
	protected function requestYoutubeData($keyedMembers) {

		return $this->youtubeClient->getAsync('channels', [
			'query' => $this->youtubeClient->getConfig('query') + [
					'id' => implode(',', array_keys($keyedMembers)),
					'part' => 'brandingSettings,contentDetails,statistics',
					'fields' => 'items(id,contentDetails(relatedPlaylists(uploads)),brandingSettings(channel(unsubscribedTrailer)),statistics(viewCount,subscriberCount,videoCount))'
				],
		]);
	}

	/**
	 * Parse the results from the youtube response
	 *
	 * @param PromiseInterface $promise
	 * @param Content[]        $keyedMembers
	 *
	 * @return PromiseInterface
	 */
	protected function parseYoutubeResults(PromiseInterface $promise, $keyedMembers) {
		return $promise->then(function (ResponseInterface $response) use ($keyedMembers) {
			$answer = json_decode($response->getBody(), true);

			$updateCount = 0;
			array_map(function ($channel) use ($keyedMembers, &$updateCount) {
				$dbChannel = $keyedMembers[$channel['id']];
				if (!$dbChannel) {
					return;
				}

				$dbChannel->values['youtube_subscribers'] = $channel['statistics']['subscriberCount'] ?: $dbChannel->values['youtube_subscribers'];
				$dbChannel->values['youtube_videos'] = $channel['statistics']['videoCount'] ?: $dbChannel->values['youtube_videos'];
				$dbChannel->values['youtube_views'] = $channel['statistics']['viewCount'] ?: $dbChannel->values['youtube_views'];
				$dbChannel->values['youtube_trailer'] = $channel['brandingSettings']['channel']['unsubscribedTrailer'] ?: $dbChannel->values['youtube_trailer'];
				$dbChannel->values['youtube_uploads_playlist'] = $channel['contentDetails']['relatedPlaylists']['uploads'] ?: $dbChannel->values['youtube_uploads_playlist'];

				$this->storeUpdatedPerson($dbChannel);

				$updateCount++;
			}, $answer['items']);

			return $updateCount;
		})->otherwise(function (RequestException $error) {
			if ($error->hasResponse()) {
				$response = json_decode($error->getResponse()->getBody(), true);
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
	 * @return PromiseInterface
	 **/
	public function updateTotals() {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		return \GuzzleHTTP\Promise\promise_for(true)->then(function () use ($storage) {
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