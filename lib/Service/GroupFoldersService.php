<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use Exception;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\GroupFolderNotFoundException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\MountPoint;
use OCA\Files_FullTextSearch\Tools\Traits\TArrayTools;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndex;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class GroupFoldersService {
	use TArrayTools;

	private ?FolderManager $folderManager = null;
	/** @var MountPoint[] */
	private array $groupFolders = [];

	public function __construct(
		IAppManager $appManager,
		private IGroupManager $groupManager,
		private LocalFilesService $localFilesService,
		IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		if ($appConfig->getAppValueBool(ConfigLexicon::FILES_GROUP_FOLDERS)) {
			try {
				$this->folderManager = \OCP\Server::get(FolderManager::class);
			} catch (Exception) {
			}
		}
	}


	/**
	 * @param string $userId
	 */
	public function initGroupSharesForUser(string $userId): void {
		if ($this->folderManager === null) {
			return;
		}

		$this->logger->debug('initGroupSharesForUser request', ['userId' => $userId]);
		$this->groupFolders = $this->getMountPoints($userId);
		$this->logger->debug('initGroupSharesForUser result', ['groupFolders' => $this->groupFolders]);
	}


	/**
	 * @param Node $file
	 * @param string $source
	 *
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, string &$source): void {
		if ($file->getMountPoint()
			->getMountType() !== 'group'
			|| $this->folderManager === null) {
			return;
		}

		try {
			$this->getMountPoint($file);
		} catch (FileIsNotIndexableException $e) {
			return;
		}

		$source = ConfigLexicon::FILES_GROUP_FOLDERS;
		throw new KnownFileSourceException();
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function updateDocumentAccess(FilesDocument $document, Node $file): void {
		if ($document->getSource() !== ConfigLexicon::FILES_GROUP_FOLDERS) {
			return;
		}

		try {
			$mount = $this->getMountPoint($file);
		} catch (FileIsNotIndexableException $e) {
			return;
		}

		$access = $document->getAccess();
		foreach ($mount->getGroups() as $group) {
			if ($this->groupManager->get($group) === null) {
				$access->addCircle($group);
			} else {
				$access->addGroup($group);
			}
		}

		$document->getIndex()
			->addOptionInt('group_folder_id', $mount->getId());
		$document->setAccess($access);
	}


	/**
	 * @param FilesDocument $document
	 * @param array $users
	 */
	public function getShareUsers(FilesDocument $document, array &$users): void {
		if ($document->getSource() !== ConfigLexicon::FILES_GROUP_FOLDERS) {
			return;
		}

		$this->localFilesService->getSharedUsersFromAccess($document->getAccess(), $users);
	}


	/**
	 * @param Node $file
	 *
	 * @return MountPoint
	 * @throws FileIsNotIndexableException
	 */
	private function getMountPoint(Node $file): MountPoint {
		foreach ($this->groupFolders as $mount) {
			if (str_starts_with($file->getPath(), $mount->getPath())) {
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
		$mounts = $this->folderManager->getAllFolders();

		foreach ($mounts as $path => $mount) {
			$mountPoint = new MountPoint();
			$mount = $mount->toArray();
			$mountPoint->setId($this->getInt('id', $mount, -1))
				->setPath('/' . $userId . '/files/' . $mount['mount_point'])
				->setGroups(array_keys($mount['groups']));
			$mountPoints[] = $mountPoint;
		}

		return $mountPoints;
	}


	/**
	 * @param IIndex $index
	 */
	public function impersonateOwner(IIndex $index): void {
		if ($index->getSource() !== ConfigLexicon::FILES_GROUP_FOLDERS) {
			return;
		}

		if ($this->folderManager === null) {
			return;
		}

		$groupFolderId = $index->getOptionInt('group_folder_id', 0);
		try {
			$mount = $this->getGroupFolderById($groupFolderId);
		} catch (GroupFolderNotFoundException $e) {
			return;
		}

		$index->setOwnerId($this->getRandomUserFromGroups(array_keys($mount['groups'])));
	}


	/**
	 * @param int $groupFolderId
	 *
	 * @return array
	 * @throws GroupFolderNotFoundException
	 */
	private function getGroupFolderById(int $groupFolderId): array {
		if ($groupFolderId === 0) {
			throw new GroupFolderNotFoundException();
		}

		$mounts = $this->folderManager->getAllFolders();
		foreach ($mounts as $path => $mount) {
			if ($mount['id'] === $groupFolderId) {
				return $mount;
			}
		}

		throw new GroupFolderNotFoundException();
	}


	/**
	 * @param array $groups
	 *
	 * @return string
	 */
	private function getRandomUserFromGroups(array $groups): string {
		foreach ($groups as $groupName) {
			$group = $this->groupManager->get($groupName);
			$users = $group->getUsers();
			if (sizeof($users) > 0) {
				return array_keys($users)[0];
			}
		}

		return '';
	}
}
