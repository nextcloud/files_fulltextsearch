<?php
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

/**
 * Created by PhpStorm.
 * User: maxence
 * Date: 12/13/17
 * Time: 4:11 PM
 */

namespace OCA\Files_FullTextSearch\Service;


use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\ExternalMount;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\App;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share\IManager;

class ExternalFilesService {


	const DOCUMENT_SOURCE = 'external';

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var LocalFilesService */
	private $localFilesService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/** @var ExternalMount[] */
	private $externalMounts = [];


	/**
	 * ExternalFilesService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param LocalFilesService $localFilesService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		LocalFilesService $localFilesService, ConfigService $configService, MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->localFilesService = $localFilesService;

		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $userId
	 */
	public function initExternalFilesForUser($userId) {
		$this->externalMounts = [];
		if (!App::isEnabled('files_external')) {
			return;
		}

		if ($this->configService->getAppValue(ConfigService::FILES_EXTERNAL) !== '1') {
			return;
		}

		$this->externalMounts = $this->getExternalMountsForUser($userId);
	}


	/**
	 * @param Node $file
	 *
	 * @param string $source
	 *
	 * @throws FileIsNotIndexableException
	 * @throws NotFoundException
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, &$source) {
		if ($file->getStorage()
				 ->isLocal() === true) {
			return;
		}

		if (!$this->configService->optionIsSelected(ConfigService::FILES_EXTERNAL)) {
			throw new FileIsNotIndexableException();
		}

		$this->getExternalMount($file);
		$source = self::DOCUMENT_SOURCE;

		throw new KnownFileSourceException();
	}


	/**
	 * @param FilesDocument $document
	 * @param array $users
	 */
	public function getShareUsers(FilesDocument $document, &$users) {

		if ($document->getSource() !== self::DOCUMENT_SOURCE) {
			return;
		}

		$this->localFilesService->getSharedUsersFromAccess($document->getAccess(), $users);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function updateDocumentAccess(FilesDocument &$document, Node $file) {

		if ($document->getSource() !== self::DOCUMENT_SOURCE) {
			return;
		}

		try {
			$mount = $this->getExternalMount($file);
		} catch (FileIsNotIndexableException $e) {
			return;
		}

		$access = $document->getAccess();

		if ($this->isMountFullGlobal($mount)) {
			$access->addUsers(['__all']);
		} else {
			$access->addUsers($mount->getUsers());
			$access->addGroups($mount->getGroups());
//		 	$access->addCircles($mount->getCircles());
		}

		// twist 'n tweak.
		if (!$mount->isGlobal()) {
			$access->setOwnerId($mount->getUsers()[0]);
		}

		$document->setAccess($access);
	}


	/**
	 * @param ExternalMount $mount
	 *
	 * @return bool
	 */
	public function isMountFullGlobal(ExternalMount $mount) {
		if (sizeof($mount->getGroups()) > 0) {
			return false;
		}

		if (sizeof($mount->getUsers()) !== 1) {
			return false;
		}

		if ($mount->getUsers()[0] === 'all') {
			return true;
		}

		return false;
	}


	/**
	 * @param Node $file
	 *
	 * @return ExternalMount
	 * @throws FileIsNotIndexableException
	 */
	private function getExternalMount(Node $file) {

		foreach ($this->externalMounts as $mount) {
			if (strpos($file->getPath(), $mount->getPath()) === 0) {
				return $mount;
			}
		}

		throw new FileIsNotIndexableException();
	}


	/**
	 * @param $userId
	 *
	 * @return ExternalMount[]
	 */
	private function getExternalMountsForUser($userId) {

		$externalMounts = [];

		// TODO: deprecated - use UserGlobalStoragesService::getStorages() and UserStoragesService::getStorages()
		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($userId);
		foreach ($mounts as $mountPoint => $mount) {
			$externalMount = new ExternalMount();
			$externalMount->setId($mount['id'])
						  ->setPath($mountPoint)
						  ->setGroups($mount['applicable']['groups'])
						  ->setUsers($mount['applicable']['users'])
						  ->setGlobal((!$mount['personal']));
			$externalMounts[] = $externalMount;
		}

		return $externalMounts;
	}

}