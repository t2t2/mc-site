<?php

namespace Mindcrack\Site\Commands;

use Bolt\Application;
use Mindcrack\Site\DataUpdater\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DataUpdaterCommand extends Command {

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @param Application $app
	 */
	public function __construct(Application $app) {
		parent::__construct();
		$this->app = $app;
	}


	protected function configure() {
		$this->setName('mindcrack:update')
			->setDescription('Runs updater on automatically gathered site data');
	}

	/**
	 * Execute update gatherer
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int|null|void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$runner = new Runner($this->app);
		$results = $runner->run();

		$output->writeln('<info>Checking...</info>');

		$results->then(function ($results) use ($output) {
			foreach ($results as $key => $result) {
				$output->writeln("<info>{$key}: {$result} Updated</info>");
			}
		}, function ($error) use ($output) {
			/** @var \Exception $error */
			$output->writeln('<error>' . $error->getMessage() . '</error></>');
		})->wait(false);
	}


}