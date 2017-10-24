<?php
/**
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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
 *
 */

namespace OCA\Files_FullNextSearch\Service;


use OC\Share\Constants;
use OC\Share\Share;
use OCA\Files_FullNextSearch\Model\FilesDocument;
use OCA\Files_FullNextSearch\Provider\FilesProvider;
use OCA\FullNextSearch\Exceptions\FilesNotFoundException;
use OCA\FullNextSearch\Model\DocumentAccess;
use OCA\FullNextSearch\Model\Index;
use OCA\FullNextSearch\Model\IndexDocument;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserManager;
use OCP\Share\IManager;

class FilesService {

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var MiscService */
	private $miscService;


	/**
	 * ProviderService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->miscService = $miscService;
	}


	/**
	 * @param string $userId
	 *
	 * @return FilesDocument[]
	 */
	public function getFilesFromUser($userId) {
		/** @var Folder $root */
		$root = \OC::$server->getUserFolder($userId)
							->get('/');

		$result = $this->getFilesFromDirectory($userId, $root);

		return $result;
	}


	/**
	 * @param string $userId
	 * @param Folder $node
	 *
	 * @return FilesDocument[]
	 */
	public function getFilesFromDirectory($userId, Folder $node) {
		$documents = [];

		if ($node->nodeExists('.noindex')) {
			return $documents;
		}

		$files = $node->getDirectoryListing();
		foreach ($files as $file) {
			$document = $this->generateFilesDocumentFromFile($file);
			if ($document !== null) {
				$documents[] = $document;
			}

			if ($file->getType() === FileInfo::TYPE_FOLDER) {
				/** @var $file Folder */
				$documents = array_merge($documents, $this->getFilesFromDirectory($userId, $file));
			}
		}

		return $documents;
	}


	/**
	 * @param Node $file
	 *
	 * @return FilesDocument
	 */
	private function generateFilesDocumentFromFile(Node $file) {
		if ($file->getStorage()
				 ->isLocal() === false) {
			return null;
		}

		$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, $file->getId());

		$document->setType($file->getType())
				 ->setOwnerId(
					 $file->getOwner()
						  ->getUID()
				 )
				 ->setModifiedTime($file->getMTime())
				 ->setMimetype($file->getMimetype());

		return $document;
	}


	/**
	 * @param $userId
	 * @param $path
	 *
	 * @return Node
	 */
	public function getFileFromPath($userId, $path) {
		return $this->rootFolder->getUserFolder($userId)
								->get($path);
	}


	/**
	 * @param int $fileId
	 * @param string $viewerId
	 *
	 * @return string
	 */
	private function getPathFromViewerId($fileId, $viewerId) {

		$viewerFiles = $this->rootFolder->getUserFolder($viewerId)
										->getById($fileId);

		if (sizeof($viewerFiles) === 0) {
			return '';
		}

		$file = array_shift($viewerFiles);

		// TODO: better way to do this : we remove the '/userid/files/'
		$path = MiscService::noEndSlash(substr($file->getPath(), 8 + strlen($viewerId)));

		return $path;
	}


	/**
	 * @param IndexDocument $document
	 */
	public function setDocumentInfo(IndexDocument $document) {

		$viewerId = $document->getAccess()
							 ->getViewerId();

		$viewerFiles = $this->rootFolder->getUserFolder($viewerId)
										->getById($document->getId());

		if (sizeof($viewerFiles) === 0) {
			return;
		}
		// we only take the first file
		$file = array_shift($viewerFiles);

		// TODO: better way to do this : we remove the '/userId/files/'
		$path = MiscService::noEndSlash(substr($file->getPath(), 7 + strlen($viewerId)));
		$document->setInfo('path', $path);
		$document->setInfo('filename', $file->getName());
	}


	/**
	 * @param IndexDocument $document
	 */
	public function setDocumentTitle(IndexDocument $document) {
		$document->setTitle($document->getInfo('path'));
	}


	/**
	 * @param IndexDocument $document
	 */
	public function setDocumentLink(IndexDocument $document) {

		$path = $document->getInfo('path');
		$dir = substr($path, 0, -strlen($document->getInfo('filename')));
		$filename = $document->getInfo('filename');

		$document->setLink('/index.php/apps/files/?dir=' . $dir . '&scrollto=' . $filename);
	}


	/**
	 * @param FilesDocument[] $documents
	 *
	 * @return FilesDocument[]
	 */
	public function generateDocuments($documents) {

		$index = [];
		foreach ($documents as $document) {
			if (!($document instanceof FilesDocument)) {
				continue;
			}

			$document->setPath($this->getPathFromViewerId($document->getId(), $document->getOwnerId()));

			$this->updateDocumentFromFilesDocument($document);
			$index[] = $document;
		}

		return $index;
	}


	/**
	 * @param Index $index
	 *
	 * @return FilesDocument
	 */
	public function updateDocument(Index $index) {
		return $this->generateDocumentFromIndex($index);
	}


	/**
	 * @param FilesDocument $document
	 */
	private function updateDocumentFromFilesDocument(FilesDocument $document) {
		$userFolder = $this->rootFolder->getUserFolder($document->getOwnerId());
		$file = $userFolder->get($document->getPath());

		$this->updateDocumentFromFile($document, $file);
	}


	private function updateDocumentFromFile(FilesDocument $document, Node $file) {
		$this->updateAccessFromFile($document, $file);
		$this->updateContentFromFile($document, $file);
	}


	/**
	 * @param Index $index
	 *
	 * @return FilesDocument
	 * @throws FilesNotFoundException
	 */
	private function generateDocumentFromIndex(Index $index) {
		$files = $this->rootFolder->getUserFolder($index->getOwnerId())
								  ->getById($index->getDocumentId());

		if (sizeof($files) === 0) {
			throw new FilesNotFoundException();
		}
		$file = array_shift($files);

		$document = $this->generateFilesDocumentFromFile($file);
		$document->setIndex($index);

		$this->updateDocumentFromFile($document, $file);

		return $document;
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function updateAccessFromFile(FilesDocument $document, Node $file) {

		$index = $document->getIndex();
		if (!$index->isStatus(Index::STATUS_INDEX_THIS)
			&& !$index->isStatus(FilesDocument::STATUS_FILE_ACCESS)) {
			return;
		}

		$document->setAccess($this->getDocumentAccessFromFile($file));
		$document->setInfo('share_names', $this->getShareNamesFromFile($file));
		$document->getIndex()
				 ->setOwnerId(
					 $document->getAccess()
							  ->getOwnerId()
				 );
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function updateContentFromFile(FilesDocument $document, Node $file) {

		if (!$document->getIndex()
					  ->isStatus(Index::STATUS_INDEX_THIS)
			|| $file->getType() !== FileInfo::TYPE_FILE) {
			return;
		}

		$document->setTitle($document->getPath());

		/** @var File $file */
		$this->extractContentFromFileText($document, $file);
		$this->extractContentFromFilePDF($document, $file);
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 */
	private function extractContentFromFileText(FilesDocument $document, File $file) {

		if ($document->getMimeType() !== 'application/octet-stream'
			&& substr($document->getMimetype(), 0, 5) !== 'text/') {
			return;
		}

		// on simple text file, elastic search+attachment pipeline can still detect language, useful ?
		$document->setContent($file->getContent(), IndexDocument::NOT_ENCODED);
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 */
	private function extractContentFromFilePDF(FilesDocument $document, File $file) {
		if ($document->getMimetype() !== 'application/pdf') {
			return;
		}

		$document->setContent(base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64);
	}


	/**
	 * @param Node $file
	 *
	 * @return DocumentAccess
	 */
	private function getDocumentAccessFromFile(Node $file) {
		$access = new DocumentAccess(
			$file->getOwner()
				 ->getUID()
		);

		list($users, $groups, $circles, $links) = $this->getSharesFromFileId($file->getId());
		$access->setUsers($users);
		$access->setGroups($groups);
		$access->setCircles($circles);
		$access->setLinks($links);

		return $access;
	}


	/**
	 * @param Node $file
	 *
	 * @return array
	 */
	private function getShareNamesFromFile(Node $file) {
		$shareNames = [];


		$shares = $this->getAllSharesFromFile($file);
		foreach ($shares as $user) {
			$shareNames[$user] = $this->getPathFromViewerId($file->getId(), $user);
		}

		return $shareNames;
	}


	/**
	 * @param Node $file
	 *
	 * @return array
	 */
	private function getAllSharesFromFile(Node $file) {
		$result = [];

		$shares = $this->shareManager->getAccessList($file);

		if (!array_key_exists('users', $shares)) {
			return $result;
		}

		foreach ($shares['users'] as $user) {
			if (in_array($user, $result) || $this->userManager->get($user) === null) {
				continue;
			}

			array_push($result, $user);
		}

		return $result;
	}


	/**
	 * @param $fileId
	 *
	 * @return array
	 */
	private function getSharesFromFileId($fileId) {

		$users = $groups = $circles = $links = [];
		$shares = Share::getAllSharesForFileId($fileId);
		foreach ($shares as $share) {
			if ($share['parent'] !== null) {
				continue;
			}

			$this->parseUsersShares($share, $users);
			$this->parseUsersGroups($share, $groups);
			$this->parseUsersCircles($share, $circles);
			$this->parseUsersLinks($share, $links);
		}

		return [$users, $groups, $circles, $links];
	}


	/**
	 * @param array $share
	 * @param array $users
	 */
	private function parseUsersShares($share, &$users) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_USER) {
			return;
		}

		if (!in_array($share['share_with'], $users)) {
			$users[] = $share['share_with'];
		}
	}


	/**
	 * @param array $share
	 * @param array $groups
	 */
	private function parseUsersGroups($share, &$groups) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_GROUP) {
			return;
		}

		if (!in_array($share['share_with'], $groups)) {
			$groups[] = $share['share_with'];
		}
	}


	/**
	 * @param array $share
	 * @param array $circles
	 */
	private function parseUsersCircles($share, &$circles) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_CIRCLE) {
			return;
		}

		if (!in_array($share['share_with'], $circles)) {
			$circles[] = $share['share_with'];
		}
	}


	/**
	 * @param array $share
	 * @param array $links
	 */
	private function parseUsersLinks($share, &$links) {
		if ((int)$share['share_type'] !== Constants::SHARE_TYPE_LINK) {
			return;
		}

		if (!in_array($share['share_with'], $links)) {
			$links[] = $share['share_with'];
		}
	}


}