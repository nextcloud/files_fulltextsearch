<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Tests\Service;

use OC\FullTextSearch\Model\DocumentAccess;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCA\Files_FullTextSearch\Service\GroupFoldersService;
use OCA\Files_FullTextSearch\Tests\GroupFoldersTestCase;
use OCP\FullTextSearch\Model\IIndex;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class GroupFoldersServiceTest extends GroupFoldersTestCase {
	private GroupFoldersService $service;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createGroupFoldersService();
	}

	public function testGroupFolderIsCrawledOnlyByFirstUser(): void {
		$this->createGroupFolder('ffts_docs');

		$this->service->initGroupSharesForUser(self::ALICE);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_docs'), self::ALICE));
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_docs'), self::ALICE));

		$this->service->initGroupSharesForUser(self::BOB);
		$this->assertTrue($this->service->isCrawledForOtherUser($this->getFolder(self::BOB, 'ffts_docs'), self::BOB));
	}

	public function testSubFolderOfGroupFolderIsNotSkipped(): void {
		$this->createGroupFolder('ffts_docs');
		$this->getFolder(self::ALICE, 'ffts_docs')->newFolder('sub');

		$this->service->initGroupSharesForUser(self::ALICE);
		$this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_docs'), self::ALICE);

		$this->service->initGroupSharesForUser(self::BOB);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::BOB, 'ffts_docs/sub'), self::BOB));
	}

	public function testGroupFolderWithAclIsCrawledForEveryUser(): void {
		$this->createGroupFolder('ffts_docs', acl: true);

		$this->service->initGroupSharesForUser(self::ALICE);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_docs'), self::ALICE));

		$this->service->initGroupSharesForUser(self::BOB);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::BOB, 'ffts_docs'), self::BOB));
	}

	public function testReleasedGroupFolderIsTakenOverByNextUser(): void {
		$this->createGroupFolder('ffts_docs');

		$this->service->initGroupSharesForUser(self::ALICE);
		$this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_docs'), self::ALICE);
		$this->service->releaseCrawledFolders(self::ALICE);

		$this->service->initGroupSharesForUser(self::BOB);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::BOB, 'ffts_docs'), self::BOB));

		$this->service->initGroupSharesForUser(self::ALICE);
		$this->assertTrue($this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_docs'), self::ALICE));
	}

	public function testRegularFolderIsNeverSkipped(): void {
		$this->createGroupFolder('ffts_docs');
		$this->rootFolder->getUserFolder(self::ALICE)->newFolder('ffts_local');
		$this->rootFolder->getUserFolder(self::BOB)->newFolder('ffts_local');

		$this->service->initGroupSharesForUser(self::ALICE);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::ALICE, 'ffts_local'), self::ALICE));

		$this->service->initGroupSharesForUser(self::BOB);
		$this->assertFalse($this->service->isCrawledForOtherUser($this->getFolder(self::BOB, 'ffts_local'), self::BOB));
	}

	public function testGetFileSource(): void {
		$this->createGroupFolder('ffts_docs');
		$this->service->initGroupSharesForUser(self::ALICE);

		$file = $this->getFolder(self::ALICE, 'ffts_docs')->newFile('a.txt', 'content');
		$source = '';
		try {
			$this->service->getFileSource($file, $source);
			$this->fail('A file of a groupfolder must have a known source');
		} catch (KnownFileSourceException) {
		}
		$this->assertSame(ConfigLexicon::FILES_GROUP_FOLDERS, $source);

		$localFile = $this->rootFolder->getUserFolder(self::ALICE)->newFile('ffts_local.txt', 'content');
		$source = '';
		$this->service->getFileSource($localFile, $source);
		$this->assertSame('', $source);
	}

	public function testUpdateDocumentAccessMatchesMountPointOnPathBoundary(): void {
		$this->createGroupFolder('ffts_docs', self::GROUP);
		$secondFolderId = $this->createGroupFolder('ffts_docs2', self::OTHER_GROUP);
		$this->service->initGroupSharesForUser(self::ALICE);

		$file = $this->getFolder(self::ALICE, 'ffts_docs2')->newFile('a.txt', 'content');

		$index = $this->createMock(IIndex::class);
		$index->expects($this->once())
			->method('addOptionInt')
			->with('group_folder_id', $secondFolderId);

		$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, (string)$file->getId());
		$document->setAccess(new DocumentAccess());
		$document->setIndex($index);
		$document->setSource(ConfigLexicon::FILES_GROUP_FOLDERS);

		$this->service->updateDocumentAccess($document, $file);

		$this->assertSame([self::OTHER_GROUP], $document->getAccess()->getGroups());
	}

	public function testImpersonateOwner(): void {
		$folderId = $this->createGroupFolder('ffts_docs');

		$index = $this->createMock(IIndex::class);
		$index->method('getSource')->willReturn(ConfigLexicon::FILES_GROUP_FOLDERS);
		$index->method('getOptionInt')->with('group_folder_id', 0)->willReturn($folderId);
		$index->expects($this->once())
			->method('setOwnerId')
			->with($this->logicalOr(self::ALICE, self::BOB));

		$this->service->impersonateOwner($index);
	}
}
