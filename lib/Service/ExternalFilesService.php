<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use Exception;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Exceptions\ExternalMountNotFoundException;
use OCA\Files_FullTextSearch\Exceptions\ExternalMountWithNoViewerException;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\MountPoint;
use OCA\Files_FullTextSearch\Tools\Traits\TArrayTools;
use OCP\App\IAppManager;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndex;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

class ExternalFilesService {
	use TArrayTools;

	public function __construct(
		private IRootFolder $rootFolder,
		private IAppManager $appManager,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IManager $shareManager,
		private LocalFilesService $localFilesService,
		private ?UserGlobalStoragesService $userGlobalStoragesService,
		private ?GlobalStoragesService $globalStoragesService,
		private ConfigService $configService,
		private LoggerInterface $logger,
	) {
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
		$source = ConfigLexicon::FILES_EXTERNAL;

		throw new KnownFileSourceException();
	}


	/**
	 * @param FilesDocument $document
	 * @param array $users
	 */
	public function getShareUsers(FilesDocument $document, array &$users) {
		if ($document->getSource() !== ConfigLexicon::FILES_EXTERNAL) {
			return;
		}

		$this->localFilesService->getSharedUsersFromAccess($document->getAccess(), $users);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 */
	public function updateDocumentAccess(FilesDocument $document, Node $file) {
		if ($document->getSource() !== ConfigLexicon::FILES_EXTERNAL) {
			return;
		}

		$mount = $this->getMountPoint($file);
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

		if (sizeof($mount->getUsers()) === 0) {
			return $mount->isGlobal();
		}

		if (sizeof($mount->getUsers()) > 1) {
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
		try {
			if ($file->getMountPoint()->getMountId() === null) {
				throw new FileIsNotIndexableException('getMountId is null');
			}

			return $this->getExternalMountById(
				$file->getMountPoint()
					->getMountId()
			);
		} catch (ExternalMountNotFoundException $e) {
			throw new FileIsNotIndexableException('issue while getMountPoint');
		}
	}


	/**
	 * @param int $externalMountId
	 *
	 * @return MountPoint
	 * @throws ExternalMountNotFoundException
	 */
	private function getExternalMountById(int $externalMountId): MountPoint {
		if ($this->globalStoragesService === null
			|| $externalMountId === 0) {
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
		if ($index->getSource() !== ConfigLexicon::FILES_EXTERNAL) {
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

		throw new ExternalMountWithNoViewerException('cannot get a valid user for external mount');
	}
}
