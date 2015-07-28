<?php

namespace Mindcrack\Site\DataUpdater;

use Bolt\Application;

class Runner {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @param Application $app
	 */
	function __construct(Application $app) {
		$this->app = $app;
	}

	/**
	 *
	 */
	public function run() {
		$promises = [
			'youtube' => $this->updateYoutube(),
		];

		return \React\Promise\all($promises);
	}

	/**
	 *
	 */
	protected function updateYoutube() {
		$checker = new YoutubeChecker($this->app);

		return $checker->update();
	}

}