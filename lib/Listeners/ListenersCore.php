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

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc21\TNC21Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OC\AppFramework\Bootstrap\Coordinator;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Service\MiscService;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\IUserSession;

/**
 * Class CoreFileEvents
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class ListenersCore {
	use TArrayTools;
	use TNC21Logger;


	/** @var IUserSession */
	protected $userSession;

	/** @var Coordinator */
	protected $coordinator;

	/** @var IFullTextSearchManager */
	protected $fullTextSearchManager;

	/** @var FilesService */
	protected $filesService;

	/** @var ConfigService */
	protected $configService;

	/** @var MiscService */
	protected $miscService;


	/**
	 * CoreFileEvents constructor.
	 *
	 * @param Coordinator $coordinator
	 * @param IUserSession $userSession
	 * @param IFullTextSearchManager $fullTextSearchManager
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		Coordinator $coordinator, IUserSession $userSession, IFullTextSearchManager $fullTextSearchManager,
		FilesService $filesService, ConfigService $configService, MiscService $miscService
	) {
		$this->userSession = $userSession;
		$this->coordinator = $coordinator;
		$this->fullTextSearchManager = $fullTextSearchManager;
		$this->filesService = $filesService;
		$this->configService = $configService;
		$this->miscService = $miscService;

		$this->setup('app', 'files_fulltextsearch');
	}


	/**
	 * @return bool
	 */
	protected function registerFullTextSearchServices(): bool {
		$this->coordinator->bootApp('fulltextsearch');

		return $this->fullTextSearchManager->isAvailable();
	}
}
