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
	 * @var Youtube Client
	 */
	protected $youtubeClient;


	/**
	 * @var Twitch Client
	 */
	protected $twitchClient;


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
			$this->setupYoutubeClient();
		} catch (\Exception $e) {
			return \GuzzleHttp\Promise\rejection_for($e);
		}

		try {
			$this->setupTwitchClient();
		} catch (\Exception $e) {
			return \GuzzleHttp\Promise\rejection_for($e);
		}

		$keyedMembers = $this->keyMembers($members);

		if (count($keyedMembers["twitch_ids"]) > 0) {
			$promise = $this->requestTwitchData($keyedMembers);
			$promise = $this->parseTwitchResults($promise, $keyedMembers);
			return $promise;
		} else {
			$promise = $this->requestYoutubeData($keyedMembers);
			$promise = $this->parseYoutubeResults($promise, $keyedMembers);
			return $promise;
		}	
	}

	/**
	 * Update all youtube stats
	 *
	 * @return PromiseInterface
	 */
	public function updateAll() {
		$youtubeMembers = $this->getYoutubeMembersToUpdate();
		$twitchMembers = $this->getTwitchMembersToUpdate();
		$promise = \GuzzleHttp\Promise\all(array_map([$this, 'update'], array_merge($youtubeMembers,$twitchMembers)));

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
	protected function setupYoutubeClient() {
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
	 * Set up guzzle client for twitch
	 */
	protected function setupTwitchClient() {
		if ($this->twitchClient) {
			return;
		}

		if (!($client_id = $this->app['config']->get('general/services/twitch'))) {
			throw new \Exception('No Twitch Client ID configured (config/services/twitch)');
		}

		$this->twitchClient = new Client([
			'base_uri' => 'https://api.twitch.tv/kraken/',
			'headers' => [
				'Client-ID' => $client_id,
			]
		]);
	}

	/**
	 * Get the list of primarily youtube members that should be updated
	 *
	 * @returns PromiseInterface
	 */
	protected function getYoutubeMembersToUpdate() {
		/** @var Storage $storage */
		$storage = $this->app['storage'];

		$members = $storage->getContent('members', ['youtube_channel_id' => '!','primarily_twitch' => "!1"]);

		$chunked = $this->chunkWithSecondaryChannels($members, self::$usersPerRequest);
		return $chunked;
	}

	/**
	 * Get the list of primarily twtich members that should be updated
	 * 
	 * @returns PromiseInterface
	 */
	protected function getTwitchMembersToUpdate() {
		$storage = $this->app['storage'];

		$members = $storage->getContent('members', ['twitch_channel_id' => '!','primarily_twitch' => "1"]);
		return $members;
	}


	/**
	 * Chunks the list of members, taking into account how many 
	 * channels each member contains.
	 *
	 * @param Content[] $members
	 * @param int $size
	 * @return Content[]
	 */
	protected function chunkWithSecondaryChannels($members, $size) {
		$chunks = array();
		$c_num = 0;
		$c_sum = 0;

		foreach($members as $member) {
			$m_sum = 1;
			foreach ($member->values["youtube_channel_secondary_repeater"] as $repeater) {
				if (!empty($repeater->get("youtube_channel_secondary_id"))) {
					$m_sum++;
				}
			}
			$c_sum += $m_sum;

			if ($c_sum > (int)$size) {
				$c_sum = $m_sum;
				$c_num++;
			}
			
			$chunks[$c_num][] = $member;
		}

		return $chunks;
	}

	/**
	 * Keys an array of both members by youtube ID and member by sub channel youtube id (in the future also twitch)
	 *
	 * @param Content[] $members
	 * @param string    $key
	 *
	 * @return array
	 */
	protected function keyMembers($members, 
				      $key = 'youtube_channel_id',
				      $childRepeaterKey = 'youtube_channel_secondary_repeater',
				      $childKey = 'youtube_channel_secondary_id',
				      $twitchKey = 'twitch_channel_id',
				      $twitchPrimary = 'primarily_twitch') {

		$keyed = ["yt_ids"=>[],"yt_sub_ids"=>[],"twitch_ids"=>[]];

		foreach ($members as $member) {
			$keyed["yt_ids"][$member->values[$key]] = $member;

			foreach ($member->values[$childRepeaterKey] as $repeater) {
				$secondaryID = $repeater->get($childKey);
				if (!empty($secondaryID)) {
					$keyed["yt_sub_ids"][$secondaryID] = $member;
				}
			}

			if ($member->values[$twitchPrimary] && !empty($member->values[$twitchKey])) {
				$keyed["twitch_ids"][$member->values[$twitchKey]] = $member;
			}
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
					'id' => implode(',', array_merge(array_keys($keyedMembers['yt_ids']),array_keys($keyedMembers['yt_sub_ids']))),
					'part' => 'brandingSettings,contentDetails,statistics',
					'fields' => 'items(id,contentDetails(relatedPlaylists(uploads)),brandingSettings(channel(unsubscribedTrailer)),statistics(viewCount,subscriberCount,videoCount))'
				],
		]);
	}

	/**
	 * Request data from twitch based on keyed members list
	 * The way things are set up(and the way twitch api is), only one member at a time
	 *
	 * @param Content[] $keyedMembers
	 *
	 * @return PromiseInterface
	 */
	protected function requestTwitchData($keyedMembers) {
		return $this->twitchClient->getAsync('channels/'.array_keys($keyedMembers['twitch_ids'])[0]);
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
				$dbChannel = $keyedMembers["yt_ids"][$channel['id']];
				if (!$dbChannel) {
					$dbChannel = $keyedMembers["yt_sub_ids"][$channel['id']];

					// If it's not a sub channel either
					if (!$dbChannel) {
						return;
					}

					$dbChannel->values['youtube_subscribers'] += $channel['statistics']['subscriberCount'] ?: 0;
					$dbChannel->values['youtube_videos'] += $channel['statistics']['videoCount'] ?: 0;
					$dbChannel->values['youtube_views'] += $channel['statistics']['viewCount'] ?: 0;

					$this->storeUpdatedPerson($dbChannel);					
				} else {
					$dbChannel->values['youtube_subscribers'] = $channel['statistics']['subscriberCount'] ?: $dbChannel->values['youtube_subscribers'];
					$dbChannel->values['youtube_videos'] = $channel['statistics']['videoCount'] ?: $dbChannel->values['youtube_videos'];
					$dbChannel->values['youtube_views'] = $channel['statistics']['viewCount'] ?: $dbChannel->values['youtube_views'];
					$dbChannel->values['youtube_trailer'] = $channel['brandingSettings']['channel']['unsubscribedTrailer'] ?: $dbChannel->values['youtube_trailer'];
					$dbChannel->values['youtube_uploads_playlist'] = $channel['contentDetails']['relatedPlaylists']['uploads'] ?: $dbChannel->values['youtube_uploads_playlist'];

					// Set Twitch numbers to 0, meant to be one or the other, not both
					$dbChannel->values['twitch_followers'] = 0;
					$dbChannel->values['twitch_views'] = 0;

					$this->storeUpdatedPerson($dbChannel);
					$updateCount++;
				}
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
	 * Parse the results from the twitch response
	 *
	 * @param PromiseInterface	$promise
	 * @param Content[]		$keyedMembers
	 *
	 * @return PromiseInterface
	 */
	protected function parseTwitchResults(PromiseInterface $promise, $keyedMembers) {
		return $promise->then(function (ResponseInterface $response) use ($keyedMembers) {
			$channel = json_decode($response->getBody(), true);

			$updateCount = 0;

			$dbChannel = $keyedMembers["twitch_ids"][$channel['name']];

			if (!$dbChannel) {
				return;
			}

			$dbChannel->values['twitch_followers'] = $channel['followers'] ?: $dbChannel->values['twitch_followers'];
			$dbChannel->values['twitch_views'] = $channel['views'] ?: $dbChannel->values['twitch_views'];

			// Set Youtube numbers to 0, meant to be one or the other, not both
			$dbChannel->values['youtube_subscribers'] = 0;
			$dbChannel->values['youtube_videos'] = 0;
			$dbChannel->values['youtube_views'] = 0;

			$this->storeUpdatedPerson($dbChannel);
			$updateCount++;

			return $updateCount;
		})->otherwise(function (RequestException $error) {
			if ($error->hasResponse()) {
				$reponse = json_decode($error->getResponse()->getBody(), true);
				throw new \Exception('Twitch Error: ' . $response['error']['message']);
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
				$totals['youtube_subscribers'] += $member['twitch_followers'];
				$totals['youtube_videos'] += $member['youtube_videos'];
				$totals['youtube_views'] += $member['youtube_views'];
				$totals['youtube_views'] += $member['twitch_views'];

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