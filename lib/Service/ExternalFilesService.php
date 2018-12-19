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


namespace OCA\Files_FullTextSearch\Service;


use daita\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OC\App\AppManager;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_FullTextSearch\Exceptions\ExternalMountNotFoundException;
use OCA\Files_FullTextSearch\Exceptions\ExternalMountWithNoViewerException;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\MountPoint;
use OCP\App\IAppManager;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndex;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;


/**
 * Class ExternalFilesService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class ExternalFilesService {


	use TArrayTools;


	/** @var IRootFolder */
	private $rootFolder;

	/** @var AppManager */
	private $appManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var GlobalStoragesService */
	private $globalStoragesService;

	/** @var IGroupManager */
	private $groupManager;

	/** @var LocalFilesService */
	private $localFilesService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/** @var MountPoint[] */
	private $externalMounts = [];


	/**
	 * ExternalFilesService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IAppManager $appManager
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IManager $shareManager
	 * @param LocalFilesService $localFilesService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IRootFolder $rootFolder, IAppManager $appManager, IUserManager $userManager,
		IGroupManager $groupManager, IManager $shareManager, LocalFilesService $localFilesService,
		ConfigService $configService, MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->appManager = $appManager;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->shareManager = $shareManager;

		$this->localFilesService = $localFilesService;

		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $userId
	 */
	public function initExternalFilesForUser(string $userId) {
		$this->externalMounts = [];
		if (!$this->appManager->isInstalled('files_external')) {
			return;
		}

		$this->globalStoragesService = \OC::$server->getGlobalStoragesService();
		$this->externalMounts = $this->getMountPoints($userId);
	}


	/**
	 * @param Node $file
	 *
	 * @param string $source
	 *
	 * @throws FileIsNotIndexableException
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, string &$source) {
		if ($this->globalStoragesService === null
			|| $file->getMountPoint()
					->getMountType() !== 'external') {
			return;
		}

		$this->getMountPoint($file);
		$source = ConfigService::FILES_EXTERNAL;

		throw new KnownFileSourceException();
	}


	/**
	 * @param FilesDocument $document
	 * @param array $users
	 */
	public function getShareUsers(FilesDocument $document, array &$users) {
		if ($document->getSource() !== ConfigService::FILES_EXTERNAL) {
			return;
		}

		$this->localFilesService->getSharedUsersFromAccess($document->getAccess(), $users);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function updateDocumentAccess(FilesDocument &$document, Node $file) {
		if ($document->getSource() !== ConfigService::FILES_EXTERNAL) {
			return;
		}

		try {
			$mount = $this->getMountPoint($file);
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

		$document->getIndex()
				 ->addOptionInt('external_mount_id', $mount->getId());
		$document->setAccess($access);

		$document->setAccess($access);
	}


	/**
	 * @param MountPoint $mount
	 *
	 * @return bool
	 */
	private function isMountFullGlobal(MountPoint $mount): bool {
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
	 * @return MountPoint
	 * @throws FileIsNotIndexableException
	 */
	private function getMountPoint(Node $file): MountPoint {

		foreach ($this->externalMounts as $mount) {
			if (strpos($file->getPath(), $mount->getPath()) === 0) {
				return $mount;
			}
		}

		throw new FileIsNotIndexableException();
	}


	/**
	 * @param string $userId
	 *
	 * @return MountPoint[]
	 */
	private function getMountPoints(string $userId): array {

		$mountPoints = [];

		// TODO: deprecated - use UserGlobalStoragesService::getStorages() and UserStoragesService::getStorages()
		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($userId);
		foreach ($mounts as $path => $mount) {
			$mountPoint = new MountPoint();
			$mountPoint->setId($this->getInt('id', $mount, -1))
					   ->setPath($path)
					   ->setGroups($mount['applicable']['groups'])
					   ->setUsers($mount['applicable']['users'])
					   ->setGlobal((!$mount['personal']));
			$mountPoints[] = $mountPoint;
		}

		return $mountPoints;
	}


	/**
	 * @param int $externalMountId
	 *
	 * @return MountPoint
	 * @throws ExternalMountNotFoundException
	 */
	private function getExternalMountById(int $externalMountId): MountPoint {
		if ($externalMountId === 0) {
			throw new ExternalMountNotFoundException();
		}

		try {
			$mount = $this->globalStoragesService->getStorage($externalMountId);
			$mountPoint = new MountPoint();
			$mountPoint->setId($mount->getId())
					   ->setPath($mount->getMountPoint())
					   ->setGroups($mount->getApplicableGroups())
					   ->setUsers($mount->getApplicableUsers())
					   ->setGlobal(($mount->getType() === StorageConfig::MOUNT_TYPE_ADMIN));
		} catch (Exception $e) {
			throw new ExternalMountNotFoundException();
		}

		return $mountPoint;
	}


	/**
	 * @param IIndex $index
	 */
	public function impersonateOwner(IIndex $index) {
		if ($index->getSource() !== ConfigService::FILES_EXTERNAL) {
			return;
		}

		$groupFolderId = $index->getOptionInt('external_mount_id', 0);
		try {
			$mount = $this->getExternalMountById($groupFolderId);
		} catch (ExternalMountNotFoundException $e) {
			return;
		}

		try {
			$index->setOwnerId($this->getRandomUserFromMountPoint($mount));
		} catch (Exception $e) {
		}
	}


	/**
	 * @param MountPoint $mount
	 *
	 * @return string
	 * @throws ExternalMountWithNoViewerException
	 */
	private function getRandomUserFromMountPoint(MountPoint $mount): string {

		$users = $mount->getUsers();
		if (sizeof($users) > 0) {
			return $users[0];
		}

		$groups = $mount->getGroups();
		if (sizeof($groups) === 0) {
			$groups = ['admin'];
		}

		foreach ($groups as $groupName) {
			$group = $this->groupManager->get($groupName);
			$users = $group->getUsers();
			if (sizeof($users) > 0) {
				return array_keys($users)[0];
			}
		}

		throw new ExternalMountWithNoViewerException(
			'cannot get a valid user for external mount'
		);

	}

}

