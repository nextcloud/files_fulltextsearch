<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Command;

use Exception;
use OC\Core\Command\Base;
use OCA\Files_FullTextSearch\Service\ConfigService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Configure
 *
 * @package OCA\Files_FullTextSearch\Command
 */
class Configure extends Base {
	public function __construct(
		private ConfigService $configService,
	) {
		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();
		$this->setName('files_fulltextsearch:configure')
			->addArgument('json', InputArgument::REQUIRED, 'set config')
			->setDescription('Configure the installation');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getArgument('json')) {
			$this->configService->setConfig(json_decode($input->getArgument('json') ?? '', true) ?? []);
		}

		$output->writeln(json_encode($this->configService->getConfig(), JSON_PRETTY_PRINT));
		return self::SUCCESS;
	}
}
