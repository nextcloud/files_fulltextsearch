<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Tests;

use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Service\GroupFoldersService;
use OCA\Files_FullTextSearch\Service\LocalFilesService;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\ISetupManager;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Two users, both members of two groups, with groupfolders from the groupfolders app.
 */
abstract class GroupFoldersTestCase extends TestCase {
	use UserTrait;

	protected const ALICE = 'ffts_alice';
	protected const BOB = 'ffts_bob';
	protected const GROUP = 'ffts_group';
	protected const OTHER_GROUP = 'ffts_other_group';

	protected FolderManager $folderManager;
	protected IRootFolder $rootFolder;
	/** @var list<int> */
	private array $folderIds = [];

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$alice = $this->createUser(self::ALICE, 'test');
		$bob = $this->createUser(self::BOB, 'test');

		$groupManager = Server::get(IGroupManager::class);
		foreach ([self::GROUP, self::OTHER_GROUP] as $groupId) {
			$group = $groupManager->createGroup($groupId);
			$group->addUser($alice);
			$group->addUser($bob);
		}

		$this->folderManager = Server::get(FolderManager::class);
		$this->rootFolder = Server::get(IRootFolder::class);
	}

	#[\Override]
	protected function tearDown(): void {
		foreach ($this->folderIds as $folderId) {
			$this->folderManager->removeFolder($folderId);
		}

		$groupManager = Server::get(IGroupManager::class);
		foreach ([self::GROUP, self::OTHER_GROUP] as $groupId) {
			$groupManager->get($groupId)?->delete();
		}

		Server::get(ISetupManager::class)->tearDown();
		parent::tearDown();
	}

	protected function createGroupFoldersService(): GroupFoldersService {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueBool')
			->with(ConfigLexicon::FILES_GROUP_FOLDERS)
			->willReturn(true);

		return new GroupFoldersService(
			Server::get(IGroupManager::class),
			Server::get(LocalFilesService::class),
			$appConfig,
			$this->createMock(LoggerInterface::class),
		);
	}

	protected function createGroupFolder(string $mountPoint, string $group = self::GROUP, bool $acl = false): int {
		$folderId = $this->folderManager->createFolder($mountPoint);
		$this->folderIds[] = $folderId;
		$this->folderManager->addApplicableGroup($folderId, $group);
		if ($acl) {
			$this->folderManager->setFolderACL($folderId, true);
		}

		return $folderId;
	}

	protected function getFolder(string $userId, string $path): Folder {
		// a full setup, as a setup for a single path does not always know about new groupfolders
		Server::get(ISetupManager::class)->setupForUser(Server::get(IUserManager::class)->get($userId));
		$folder = $this->rootFolder->getUserFolder($userId)->get($path);
		$this->assertInstanceOf(Folder::class, $folder);
		return $folder;
	}
}
