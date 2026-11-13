<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Tests\Service;

use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Service\LocalFilesService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\Files\ISetupManager;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;
use Test\Traits\UserTrait;

#[Group('DB')]
class FilesServiceMissingFileTest extends TestCase {
	use UserTrait;

	private const USER = 'ffts_missing_user';

	private IRootFolder $rootFolder;
	private FilesService $service;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->createUser(self::USER, 'test');
		$this->rootFolder = Server::get(IRootFolder::class);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueInt')
			->willReturnCallback(static fn (string $key): int => match ($key) {
				ConfigLexicon::FILES_CHUNK_SIZE => FilesService::CHUNK_TREE_SIZE,
				default => 0,
			});

		$this->service = $this->createInstanceWithMocks(FilesService::class, [
			'rootFolder' => $this->rootFolder,
			'appConfig' => $appConfig,
			'userManager' => Server::get(IUserManager::class),
			'localFilesService' => Server::get(LocalFilesService::class),
		]);
	}

	#[\Override]
	protected function tearDown(): void {
		Server::get(ISetupManager::class)->tearDown();
		parent::tearDown();
	}

	public function testFileMissingFromStorageIsSkipped(): void {
		$folder = $this->rootFolder->getUserFolder(self::USER)->newFolder('ffts_missing');
		$folder->newFile('kept.txt', 'content');
		$missing = $folder->newFile('missing.txt', 'content');

		// delete the file on disk only, so the file cache still lists it
		unlink($missing->getStorage()->getLocalFile($missing->getInternalPath()));
		$this->assertFalse($missing->stat());

		$paths = array_map(
			static fn (FilesDocument $document): string => $document->getPath(),
			$this->service->getFilesFromUser(self::USER, '/ffts_missing')
		);

		$this->assertContains('ffts_missing/kept.txt', $paths);
		$this->assertNotContains('ffts_missing/missing.txt', $paths);
	}
}
