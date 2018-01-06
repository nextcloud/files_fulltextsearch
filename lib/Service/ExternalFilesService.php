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
use OCA\Files_FullTextSearch\Model\ExternalMount;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\FullTextSearch\Model\DocumentAccess;
use OCP\App;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share\IManager;

class ExternalFilesService {


	const DOCUMENT_TYPE = 'external_files';

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

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
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		ConfigService $configService, MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

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
	 * @throws FileIsNotIndexableException
	 * @throws NotFoundException
	 */
	public function externalFileMustBeIndexable(Node $file) {

		if ($file->getStorage()
				 ->isLocal() === true) {
			return;
		}

		if (!$this->configService->optionIsSelected(ConfigService::FILES_EXTERNAL)) {
			throw new FileIsNotIndexableException();
		}

		$this->getExternalMount($file);
	}


	/**
	 * @param DocumentAccess $access
	 *
	 * @return array
	 */
	public function getAllSharesFromExternalFile(DocumentAccess $access) {
		$result = $access->getUsers();

		if ($access->getOwnerId() !== '') {
			array_push($result, $access->getOwnerId());
		}

		// TODO: get users from groups & circles.
		return $result;
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 * @throws NotFoundException
	 */
	public function updateDocumentWithExternalFiles(FilesDocument &$document, Node $file) {

		if ($file->getStorage()
				 ->isLocal() === true) {
			return;
		}

		$document->addTag('external');
		$mount = $this->getExternalMount($file);
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