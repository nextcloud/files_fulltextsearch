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


namespace OCA\Files_FullTextSearch\Service;


use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\GroupFolderMount;
use OCA\Files_FullTextSearch\Model\GroupSharesMount;
use OCA\FullTextSearch\Model\DocumentAccess;
use OCP\App;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserManager;
use OCP\Share\IManager;

class GroupFoldersService {


	const DOCUMENT_SOURCE = 'group_folders';

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


	/** @var GroupFolderMount[] */
	private $groupFolders = [];


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
	 *
	 */
	public function initGroupShares() {
		$this->groupFolders = [];
		if (!App::isEnabled('groupfolders')) {
			return;
		}

		$this->groupFolders = $this->getGroupFoldersMounts();
	}


	/**
	 * @param Node $file
	 *
	 * @param string $source
	 *
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, &$source) {


		try {
			$this->getGroupFolderMount($file);
		} catch (FileIsNotIndexableException $e) {
			return;
		}

		$source = self::DOCUMENT_SOURCE;
		throw new KnownFileSourceException();
	}

//
//	/**
//	 * @param DocumentAccess $access
//	 *
//	 * @return array
//	 */
//	public function getAllSharesFromExternalFile(DocumentAccess $access) {
//		$result = $access->getUsers();
//
//		if ($access->getOwnerId() !== '') {
//			array_push($result, $access->getOwnerId());
//		}
//
//		// TODO: get users from groups & circles.
//		return $result;
//	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function updateDocumentAccess(FilesDocument &$document, Node $file) {

		if ($document->getSource() !== self::DOCUMENT_SOURCE) {
			return;
		}

//		try {
//			$mount = $this->getGroupFolderMount($file);
//		} catch (FileIsNotIndexableException $e) {
//			return;
//		}

		$access = $document->getAccess();
echo json_encode($access);
//		if ($this->isMountFullGlobal($mount)) {
//			$access->addUsers(['__all']);
//		} else {
//			$access->addUsers($mount->getUsers());
//			$access->addGroups($mount->getGroups());
////		 	$access->addCircles($mount->getCircles());
//		}
//
//		// twist 'n tweak.
//		if (!$mount->isGlobal()) {
//			$access->setOwnerId($mount->getUsers()[0]);
//		}

		$document->setAccess($access);
	}


	/**
	 * @param GroupFolderMount $mount
	 *
	 * @return bool
	 */
	public function isMountFullGlobal(GroupFolderMount $mount) {
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
	 * @return GroupFolderMount
	 * @throws FileIsNotIndexableException
	 */
	private function getGroupFolderMount(Node $file) {

		foreach ($this->groupFolders as $mount) {
			if (strpos($file->getPath(), $mount->getPath()) === 0) {
				return $mount;
			}
		}

		throw new FileIsNotIndexableException();

	}


	/**
	 * @return GroupFolderMount[]
	 */
	private function getGroupFoldersMounts() {

		$groupFolders = [];

		// TODO: deprecated - use UserGlobalStoragesService::getStorages() and UserStoragesService::getStorages()
		$mounts = [];
		foreach ($mounts as $mountPoint => $mount) {
			$groupFolder = new GroupFolderMount();
//			$externalMount->setId($mount['id'])
//						  ->setPath($mountPoint)
//						  ->setGroups($mount['applicable']['groups'])
//						  ->setUsers($mount['applicable']['users'])
//						  ->setGlobal((!$mount['personal']));
			$groupFolders[] = $groupFolder;
		}

		return $groupFolders;
	}



}