<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use Exception;
use OC\FullTextSearch\Model\DocumentAccess;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Db\SharesRequest;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\FileShares;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IDocumentAccess;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Class LocalFilesService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class LocalFilesService {
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly IManager $shareManager,
		private readonly SharesRequest $sharesRequest,
	) {
	}

	/**
	 *
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, string &$source): void {
		$mountType = $file->getMountPoint()->getMountType();
		if ($mountType !== '' && $mountType !== 'shared') {
			return;
		}

		$source = ConfigLexicon::FILES_LOCAL;

		throw new KnownFileSourceException();
	}

	public function updateDocumentAccess(FilesDocument $document, Node $file): void {
		$ownerId = '';
		if ($file->getOwner() !== null) {
			$ownerId = $file->getOwner()
				->getUID();
		}

		$access = new DocumentAccess($ownerId);

		$fileShares = new FileShares();
		$this->getSharesFromFile($file, $fileShares);
		$access->setUsers($fileShares->getUsers());
		$access->setGroups($fileShares->getGroups());
		$access->setCircles($fileShares->getCircles());
		$access->setLinks($fileShares->getLinks());

		$document->setAccess($access);
	}

	public function getShareUsersFromFile(Node $file, array &$users): void {
		if ($file->getOwner() === null) {
			return;
		}

		try {
			$shares = $this->shareManager->getAccessList($file, true, true);
		} catch (Exception) {
			return;
		}

		foreach ($shares['users'] ?? [] as $user => $node) {
			if (in_array($user, $users) || $this->userManager->get($user) === null) {
				continue;
			}

			$users[] = $user;
		}
	}

	/**
	 * same a getShareUsers, but we do it 'manually'
	 */
	public function getSharedUsersFromAccess(IDocumentAccess $access, array &$users): void {
		$result = array_merge(
			$access->getUsers(),
			$this->getSharedUsersFromAccessGroups($access),
			$this->getSharedUsersFromAccessCircles()
		);

		foreach ($result as $user) {
			if (!in_array($user, $users)) {
				$users[] = $user;
			}
		}
	}

	private function getSharedUsersFromAccessGroups(IDocumentAccess $access): array {
		$result = [];
		$users = [];
		foreach ($access->getGroups() as $groupName) {
			$group = $this->groupManager->get($groupName);
			if ($group === null) {
				// TODO: set a warning
				continue;
			}
			$users = array_merge($users, $group->getUsers());
		}

		foreach ($users as $user) {
			$result[] = $user->getUID();
		}

		return $result;
	}

	/**
	 * // TODO: get users from circles.
	 *
	 *
	 */
	private function getSharedUsersFromAccessCircles(): array {
		return [];
	}

	private function getSharesFromFile(Node $file, FileShares $fileShares): void {
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

	private function parseUsersShares(array $share, FileShares $fileShares): void {
		if ((int)$share['share_type'] !== IShare::TYPE_USER) {
			return;
		}

		$fileShares->addUser($share['share_with']);
	}

	private function parseUsersGroups(array $share, FileShares $fileShares): void {
		if ((int)$share['share_type'] !== IShare::TYPE_GROUP) {
			return;
		}

		$fileShares->addGroup($share['share_with']);
	}

	private function parseUsersCircles(array $share, FileShares $fileShares): void {
		if ((int)$share['share_type'] !== IShare::TYPE_CIRCLE) {
			return;
		}

		$fileShares->addCircle($share['share_with']);
	}

	private function parseUsersLinks(array $share, FileShares $fileShares): void {
		if ((int)$share['share_type'] !== IShare::TYPE_LINK) {
			return;
		}

		$fileShares->addLink($share['token']);
	}
}
