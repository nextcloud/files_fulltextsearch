<?php
declare(strict_types=1);


/**
 * Files_FullTextSearch - Index the content of your files
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018
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


namespace OCA\Files_FullTextSearch\Events;


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OC\AppFramework\Bootstrap\Coordinator;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Service\MiscService;
use OCP\App\IAppManager;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\FullTextSearch\Model\IIndex;


/**
 * Class FilesEvents
 *
 * @package OCA\Files_FullTextSearch\Events
 */
class FilesEvents {


	use TArrayTools;


	/** @var string */
	private $userId;

	/** @var IAppManager */
	private $appManager;

	/** @var Coordinator */
	private $coordinator;

	/** @var IFullTextSearchManager */
	private $fullTextSearchManager;

	/** @var FilesService */
	private $filesService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * FilesEvents constructor.
	 *
	 * @param string $userId
	 * @param IAppManager $appManager
	 * @param Coordinator $coordinator
	 * @param IFullTextSearchManager $fullTextSearchManager
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		$userId, IAppManager $appManager, Coordinator $coordinator,
		IFullTextSearchManager $fullTextSearchManager,
		FilesService $filesService, ConfigService $configService, MiscService $miscService
	) {
		$this->userId = $userId;
		$this->appManager = $appManager;
		$this->coordinator = $coordinator;
		$this->fullTextSearchManager = $fullTextSearchManager;
		$this->filesService = $filesService;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @return bool
	 */
	private function registerFullTextSearchServices() {
		$this->coordinator->bootApp('fulltextsearch');

		return $this->fullTextSearchManager->isAvailable();
	}


	/**
	 * @param array $params
	 */
	public function onFileUnshare(array $params) {
		if (!$this->registerFullTextSearchServices()) {
			return;
		}

		$fileId = $this->get('itemSource', $params, '');
		if ($fileId === '' || $this->userId === null) {
			return;
		}

		$this->fullTextSearchManager->updateIndexStatus('files', $fileId, IIndex::INDEX_META);
	}


}

