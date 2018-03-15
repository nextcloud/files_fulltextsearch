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


use OC\Share\Constants;
use OCA\Files_FullTextSearch\Db\SharesRequest;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\FileShares;
use OCA\FullTextSearch\Model\DocumentAccess;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share\IManager;

class LocalFilesService {


	const DOCUMENT_SOURCE = 'local';

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var SharesRequest */
	private $sharesRequest;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;

//
//	/** @var ExternalMount[] */
//	private $externalMounts = [];


	/**
	 * ExternalFilesService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param SharesRequest $sharesRequest
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		SharesRequest $sharesRequest, ConfigService $configService, MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->sharesRequest = $sharesRequest;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param Node $file
	 * @param string $source
	 */
	public function getFileSource(Node $file, &$source) {
		$source = self::DOCUMENT_SOURCE;
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function updateDocumentAccess(FilesDocument $document, Node $file) {

		$access = new DocumentAccess(
			$file->getOwner()
				 ->getUID()
		);

		$fileShares = new FileShares();
		$this->getSharesFromFile($file, $fileShares);
		$access->setUsers($fileShares->getUsers());
		$access->setGroups($fileShares->getGroups());
		$access->setCircles($fileShares->getCircles());
		$access->setLinks($fileShares->getLinks());

		$document->setAccess($access);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 * @param array $users
	 */
	public function getShareUsers(FilesDocument $document, Node $file, &$users) {


//		if ($file->getStorage()
//				 ->isLocal() === false) {
//			$shares = $this->externalFilesService->getAllSharesFromExternalFile($access);
//		} else {
//			$shares = $this->getAllSharesFromFile($file);
////		}
//
//	}
//
//
//	/**
//	 * @param Node $file
//	 *
//	 * @return array
//	 */
//	private function getAllSharesFromFile(Node $file) {

		$shares = $this->shareManager->getAccessList($file);

		if (!array_key_exists('users', $shares)) {
			return;
		}

		foreach ($shares['users'] as $user) {
			if (in_array($user, $users) || $this->userManager->get($user) === null) {
				continue;
			}

			array_push($users, $user);
		}

	}


	/**
	 * @param Node $file
	 * @param FileShares $fileShares
	 */
	private function getSharesFromFile(Node $file, FileShares $fileShares) {

		if (strlen($file->getPath()) <= 1) {
			return;
		}

		// we get shares from parent first
		$this->getSharesFromFile($file->getParent(), $fileShares);

		$shares = $this->sharesRequest->getFromFile($file);
		foreach ($shares as $share) {
			if ($share['parent'] !== null) {
				continue;
			}

			$this->parseUsersShares($share, $fileShares);
			$this->parseUsersGroups($share, $fileShares);
			$this->parseUsersCircles($share, $fileShares);
			$this->parseUsersLinks($share, $fileShares);
		}
	}


	/**
	 * @param Node $file
	 * @param FileShares $fileShares
	 */
	private function getSharesFromParent(Node $file, FileShares $fileShares) {
//		$parent = basename($file->getPath());
//
//		echo $parent . "\n";
//
//		if (strlen($parent) <= 1) {
//			return;
//		}

	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersShares($share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_USER) {
			return;
		}

		$fileShares->addUser($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersGroups($share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_GROUP) {
			return;
		}

		$fileShares->addGroup($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersCircles($share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_CIRCLE) {
			return;
		}

		$fileShares->addCircle($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersLinks($share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_LINK) {
			return;
		}

		$fileShares->addLink($share['share_with']);
	}


//
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
//
//
//	/**
//	 * @param FilesDocument $document
//	 * @param Node $file
//	 */
//	public function updateDocumentAccessFromExternalFile(FilesDocument &$document, Node $file) {
//
//		if ($document->getSource() !== self::DOCUMENT_SOURCE) {
//			return;
//		}
//
//		try {
//			$mount = $this->getExternalMount($file);
//		} catch (FileIsNotIndexableException $e) {
//			return;
//		}
//
//		$access = $document->getAccess();
//
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
//
//		$document->setAccess($access);
//	}
//
//
//	/**
//	 * @param ExternalMount $mount
//	 *
//	 * @return bool
//	 */
//	public function isMountFullGlobal(ExternalMount $mount) {
//		if (sizeof($mount->getGroups()) > 0) {
//			return false;
//		}
//
//		if (sizeof($mount->getUsers()) !== 1) {
//			return false;
//		}
//
//		if ($mount->getUsers()[0] === 'all') {
//			return true;
//		}
//
//		return false;
//	}
//
//
//	/**
//	 * @param Node $file
//	 *
//	 * @return ExternalMount
//	 * @throws FileIsNotIndexableException
//	 */
//	private function getExternalMount(Node $file) {
//
//		foreach ($this->externalMounts as $mount) {
//			if (strpos($file->getPath(), $mount->getPath()) === 0) {
//				return $mount;
//			}
//		}
//
//		throw new FileIsNotIndexableException();
//	}
//
//
//	/**
//	 * @param $userId
//	 *
//	 * @return ExternalMount[]
//	 */
//	private function getExternalMountsForUser($userId) {
//
//		$externalMounts = [];
//
//		// TODO: deprecated - use UserGlobalStoragesService::getStorages() and UserStoragesService::getStorages()
//		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($userId);
//		foreach ($mounts as $mountPoint => $mount) {
//			$externalMount = new ExternalMount();
//			$externalMount->setId($mount['id'])
//						  ->setPath($mountPoint)
//						  ->setGroups($mount['applicable']['groups'])
//						  ->setUsers($mount['applicable']['users'])
//						  ->setGlobal((!$mount['personal']));
//			$externalMounts[] = $externalMount;
//		}
//
//		return $externalMounts;
//	}

}