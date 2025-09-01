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
use Psr\Log\LoggerInterface;

/**
 * Class LocalFilesService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class LocalFilesService {
	public function __construct(
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IManager $shareManager,
		private SharesRequest $sharesRequest,
		private LoggerInterface $logger,
	) {
	}


	/**
	 * @param Node $file
	 * @param string $source
	 *
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, string &$source) {
		$mountType = $file->getMountPoint()->getMountType();
		if ($mountType !== '' && $mountType !== 'shared') {
			return;
		}

		$source = ConfigLexicon::FILES_LOCAL;

		throw new KnownFileSourceException();
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function updateDocumentAccess(FilesDocument $document, Node $file) {
		$ownerId = '';
		if ($file->getOwner() !== null) {
			$ownerId = $file->getOwner()
				->getUID();
		}

		if (!is_string($ownerId)) {
			$ownerId = '';
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


	/**
	 * @param Node $file
	 * @param array $users
	 */
	public function getShareUsersFromFile(Node $file, array &$users) {
		if ($file->getOwner() === null) {
			return;
		}

		try {
			$shares = $this->shareManager->getAccessList($file, true, true);
		} catch (Exception $e) {
			return;
		}

		foreach ($shares['users'] ?? [] as $user => $node) {
			if (!is_string($user)) {
				$this->logger->warning('malformed access list: ' . json_encode($shares));
				continue;
			}
			if (in_array($user, $users) || $this->userManager->get($user) === null) {
				continue;
			}

			$users[] = $user;
		}
	}


	/**
	 * same a getShareUsers, but we do it 'manually'
	 *
	 * @param IDocumentAccess $access
	 * @param array $users
	 */
	public function getSharedUsersFromAccess(IDocumentAccess $access, array &$users) {
		$result = array_merge(
			$access->getUsers(),
			$this->getSharedUsersFromAccessGroups($access),
			$this->getSharedUsersFromAccessCircles($access)
		);

		foreach ($result as $user) {
			if (!in_array($user, $users)) {
				$users[] = $user;
			}
		}
	}


	/**
	 * @param IDocumentAccess $access
	 *
	 * @return array
	 */
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
	 * @param IDocumentAccess $access
	 *
	 * @return array
	 */
	private function getSharedUsersFromAccessCircles(IDocumentAccess $access): array {
		$result = [];

		return $result;
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
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersShares(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== IShare::TYPE_USER) {
			return;
		}

		$fileShares->addUser($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersGroups(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== IShare::TYPE_GROUP) {
			return;
		}

		$fileShares->addGroup($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersCircles(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== IShare::TYPE_CIRCLE) {
			return;
		}

		$fileShares->addCircle($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersLinks(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== IShare::TYPE_LINK) {
			return;
		}

		$fileShares->addLink($share['token']);
	}
}
