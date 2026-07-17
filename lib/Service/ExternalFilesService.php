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
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Exceptions\ExternalMountNotFoundException;
use OCA\Files_FullTextSearch\Exceptions\ExternalMountWithNoViewerException;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\MountPoint;
use OCA\Files_FullTextSearch\Tools\Traits\TArrayTools;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndex;
use OCP\IGroupManager;

class ExternalFilesService {
	use TArrayTools;

	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly LocalFilesService $localFilesService,
		private readonly ?GlobalStoragesService $globalStoragesService,
	) {
	}

	/**
	 *
	 *
	 * @throws FileIsNotIndexableException
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, string &$source): void {
		if ($this->globalStoragesService === null
			|| $file->getMountPoint()
				->getMountType() !== 'external') {
			return;
		}

		$this->getMountPoint($file);
		$source = ConfigLexicon::FILES_EXTERNAL;

		throw new KnownFileSourceException();
	}

	public function getShareUsers(FilesDocument $document, array &$users): void {
		if ($document->getSource() !== ConfigLexicon::FILES_EXTERNAL) {
			return;
		}

		$this->localFilesService->getSharedUsersFromAccess($document->getAccess(), $users);
	}

	/**
	 *
	 * @throws FileIsNotIndexableException
	 */
	public function updateDocumentAccess(FilesDocument $document, Node $file): void {
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
		} catch (ExternalMountNotFoundException) {
			throw new FileIsNotIndexableException('issue while getMountPoint');
		}
	}

	/**
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
		} catch (Exception) {
			throw new ExternalMountNotFoundException();
		}

		return $mountPoint;
	}

	public function impersonateOwner(IIndex $index): void {
		if ($index->getSource() !== ConfigLexicon::FILES_EXTERNAL) {
			return;
		}

		$groupFolderId = $index->getOptionInt('external_mount_id', 0);
		try {
			$mount = $this->getExternalMountById($groupFolderId);
		} catch (ExternalMountNotFoundException) {
			return;
		}

		try {
			$index->setOwnerId($this->getRandomUserFromMountPoint($mount));
		} catch (Exception) {
		}
	}

	/**
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
