<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OC\AppFramework\Bootstrap\Coordinator;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Tools\Traits\TArrayTools;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\IUserSession;

/**
 * Class CoreFileEvents
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class ListenersCore {
	use TArrayTools;

	public function __construct(
		protected Coordinator $coordinator,
		protected IUserSession $userSession,
		protected IFullTextSearchManager $fullTextSearchManager,
		protected FilesService $filesService,
		protected ConfigService $configService,
	) {
	}

	/**
	 * @return bool
	 */
	protected function registerFullTextSearchServices(): bool {
		$this->coordinator->bootApp('fulltextsearch');

		return $this->fullTextSearchManager->isAvailable();
	}
}
