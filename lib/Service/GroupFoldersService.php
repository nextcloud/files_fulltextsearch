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
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndex;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-type GroupFoldersApplicable = array{
 *      displayName: string,
 *      permissions: int,
 *      type: 'group'|'circle',
 *  }
 *
 * @phpstan-type GroupFoldersAclManage = array{
 *      displayname: string,
 *      id: string,
 *      type: 'user'|'group'|'circle',
 *  }
 */
class GroupFoldersService {
	use TArrayTools;

	private ?FolderManager $folderManager = null;
	/** @var list<MountPoint> */
	private array $groupFolders = [];

	public function __construct(
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

	public function initGroupSharesForUser(string $userId): void {
		if ($this->folderManager === null) {
			return;
		}

		$this->logger->debug('initGroupSharesForUser request', ['userId' => $userId]);
		$this->groupFolders = $this->getMountPoints($userId);
		$this->logger->debug('initGroupSharesForUser result', ['groupFolders' => $this->groupFolders]);
	}

	/**
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
		} catch (FileIsNotIndexableException) {
			return;
		}

		$source = ConfigLexicon::FILES_GROUP_FOLDERS;
		throw new KnownFileSourceException();
	}

	public function updateDocumentAccess(FilesDocument $document, Node $file): void {
		if ($document->getSource() !== ConfigLexicon::FILES_GROUP_FOLDERS) {
			return;
		}

		try {
			$mount = $this->getMountPoint($file);
		} catch (FileIsNotIndexableException) {
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

	public function getShareUsers(FilesDocument $document, array &$users): void {
		if ($document->getSource() !== ConfigLexicon::FILES_GROUP_FOLDERS) {
			return;
		}

		$this->localFilesService->getSharedUsersFromAccess($document->getAccess(), $users);
	}

	/**
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
	 * @return list<MountPoint>
	 */
	private function getMountPoints(string $userId): array {
		$mountPoints = [];
		$mounts = $this->folderManager->getAllFolders();

		foreach ($mounts as $mount) {
			$mountPoint = new MountPoint();
			$mount = $mount->toArray();
			$mountPoint->setId($this->getInt('id', $mount, -1))
				->setPath('/' . $userId . '/files/' . $mount['mount_point'])
				->setGroups(array_keys($mount['groups']));
			$mountPoints[] = $mountPoint;
		}

		return $mountPoints;
	}

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
		} catch (GroupFolderNotFoundException) {
			return;
		}

		$index->setOwnerId($this->getRandomUserFromGroups(array_keys($mount['groups'])));
	}

	/**
	 * @throws GroupFolderNotFoundException
	 * @return array{
	 *      id: int,
	 *      mount_point: string,
	 *      quota: int,
	 *      acl: bool,
	 *      acl_default_no_permission: bool,
	 *      storage_id: int,
	 *      root_id: int,
	 *      groups: array<string, GroupFoldersApplicable>,
	 *      manage: list<GroupFoldersAclManage>,
	 *  }
	 */
	private function getGroupFolderById(int $groupFolderId): array {
		if ($groupFolderId === 0) {
			throw new GroupFolderNotFoundException();
		}

		$mount = $this->folderManager->getFolder($groupFolderId);
		if ($mount === null) {
			throw new GroupFolderNotFoundException();
		}

		return $mount->toArray();
	}

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
