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


use Exception;
use OC\Share\Constants;
use OCA\Files_FullTextSearch\Db\SharesRequest;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Model\FileShares;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\DocumentAccess;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;


/**
 * Class LocalFilesService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class LocalFilesService {


	/** @var IRootFolder */
	private $rootFolder;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var SharesRequest */
	private $sharesRequest;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * LocalFilesService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IGroupManager $groupManager
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param SharesRequest $sharesRequest
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IRootFolder $rootFolder, IGroupManager $groupManager, IUserManager $userManager,
		IManager $shareManager, SharesRequest $sharesRequest, ConfigService $configService,
		MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->sharesRequest = $sharesRequest;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param Node $file
	 * @param string $source
	 *
	 * @throws KnownFileSourceException
	 */
	public function getFileSource(Node $file, string &$source) {
		if ($file->getMountPoint()
				 ->getMountType() !== '') {
			return;
		}

		$source = ConfigService::FILES_LOCAL;

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

		if (!array_key_exists('users', $shares)) {
			return;
		}

		foreach ($shares['users'] as $user => $node) {
			if (in_array($user, $users) || $this->userManager->get($user) === null) {
				continue;
			}

			array_push($users, $user);
		}

	}


	/**
	 * same a getShareUsers, but we do it 'manually'
	 *
	 * @param DocumentAccess $access
	 * @param array $users
	 */
	public function getSharedUsersFromAccess(DocumentAccess $access, array &$users) {

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
	 * @param DocumentAccess $access
	 *
	 * @return array
	 */
	private function getSharedUsersFromAccessGroups(DocumentAccess $access): array {

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
	 * @param DocumentAccess $access
	 *
	 * @return array
	 */
	private function getSharedUsersFromAccessCircles(DocumentAccess $access): array {
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
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_USER) {
			return;
		}

		$fileShares->addUser($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersGroups(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_GROUP) {
			return;
		}

		$fileShares->addGroup($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersCircles(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_CIRCLE) {
			return;
		}

		$fileShares->addCircle($share['share_with']);
	}


	/**
	 * @param array $share
	 * @param FileShares $fileShares
	 */
	private function parseUsersLinks(array $share, FileShares $fileShares) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_LINK) {
			return;
		}

		$fileShares->addLink($share['token']);
	}

}

