<?php

declare(strict_types=1);


/**
 * Files_FullTextSearch - Index the content of your files
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2020
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Files_FullTextSearch\Listeners;

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OC\AppFramework\Bootstrap\Coordinator;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\FilesService;
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
		protected ConfigService $configService
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
