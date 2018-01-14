<?php
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
use OC\Share\Share;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCA\FullTextSearch\Exceptions\InterruptException;
use OCA\FullTextSearch\Exceptions\TickDoesNotExistException;
use OCA\FullTextSearch\Model\DocumentAccess;
use OCA\FullTextSearch\Model\Index;
use OCA\FullTextSearch\Model\IndexDocument;
use OCA\FullTextSearch\Model\Runner;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\Share\IManager;

class FilesService {

	const DOCUMENT_TYPE = 'files';

	const MIMETYPE_TEXT = 'files_text';
	const MIMETYPE_PDF = 'files_pdf';
	const MIMETYPE_OFFICE = 'files_office';
	const MIMETYPE_IMAGE = 'files_image';
	const MIMETYPE_AUDIO = 'files_audio';


	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var ConfigService */
	private $configService;

	/** @var ExternalFilesService */
	private $externalFilesService;

	/** @var MiscService */
	private $miscService;


	/**
	 * FilesService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param ConfigService $configService
	 * @param ExternalFilesService $externalFilesService
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	public function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		ConfigService $configService, ExternalFilesService $externalFilesService,
		MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->configService = $configService;
		$this->externalFilesService = $externalFilesService;
		$this->miscService = $miscService;
	}


	/**
	 * @param Runner $runner
	 * @param string $userId
	 *
	 * @return FilesDocument[]
	 * @throws InterruptException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws TickDoesNotExistException
	 */
	public function getFilesFromUser(Runner $runner, $userId) {

		$this->externalFilesService->initExternalFilesForUser($userId);

		/** @var Folder $files */
		$files = $this->rootFolder->getUserFolder($userId)
								  ->get('/');
		$result = $this->getFilesFromDirectory($runner, $userId, $files);

		return $result;
	}


	/**
	 * @param Runner $runner
	 * @param string $userId
	 * @param Folder $node
	 *
	 * @return FilesDocument[]
	 * @throws InterruptException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws TickDoesNotExistException
	 */
	public function getFilesFromDirectory(Runner $runner, $userId, Folder $node) {
		$documents = [];

		if ($node->nodeExists('.noindex')) {
			return $documents;
		}

		$files = $node->getDirectoryListing();
		foreach ($files as $file) {
			$runner->update('getFilesFromDirectory');
			try {
				$document = $this->generateFilesDocumentFromFile($file, $userId);
				$documents[] = $document;
			} catch (FileIsNotIndexableException $e) {
				/** goto next file */
			}

			if ($file->getType() === FileInfo::TYPE_FOLDER) {
				/** @var $file Folder */
				$documents =
					array_merge($documents, $this->getFilesFromDirectory($runner, $userId, $file));
			}
		}

		return $documents;
	}


	/**
	 * @param Node $file
	 *
	 * @param string $viewerId
	 *
	 * @return FilesDocument
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function generateFilesDocumentFromFile(Node $file, $viewerId = '') {

		$this->fileMustBeIndexable($file);
		$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, $file->getId());

		$ownerId = $file->getOwner()
						->getUID();

		$document->setType($file->getType())
				 ->setOwnerId($ownerId)
				 ->setViewerId($viewerId)
				 ->setModifiedTime($file->getMTime())
				 ->setMimetype($file->getMimetype());

		return $document;
	}


	/**
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 * @throws NotFoundException
	 */
	private function fileMustBeIndexable(Node $file) {
		$this->externalFilesService->externalFileMustBeIndexable($file);
	}


	/**
	 * @param string $userId
	 * @param string $path
	 *
	 * @return Node
	 * @throws NotFoundException
	 */
	public function getFileFromPath($userId, $path) {
		return $this->rootFolder->getUserFolder($userId)
								->get($path);
	}


	/**
	 * @param string $userId
	 * @param int $fileId
	 *
	 * @return Node
	 */
	public function getFileFromId($userId, $fileId) {

		$files = $this->rootFolder->getUserFolder($userId)
								  ->getById($fileId);

		if (sizeof($files) === 0) {
			return null;
		}

		$file = array_shift($files);

		return $file;
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
	 * @param int $fileId
	 *
	 * @return string
	 */
	private function getWebdavId($fileId) {
		$instanceId = $this->configService->getSystemValue('instanceid');

		return sprintf("%08s", $fileId) . $instanceId;
	}


	/**
	 * @param IndexDocument $document
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function setDocumentMore(IndexDocument $document) {

		$access = $document->getAccess();
		$file = $this->getFileFromId($access->getViewerId(), $document->getId());

		if ($file === null) {
			return;
		}

		// TODO: better way to do this : we remove the '/userid/files/'
		$path = MiscService::noEndSlash(substr($file->getPath(), 7 + strlen($access->getViewerId())));

		$more = [
			'webdav'             => $this->getWebdavId($document->getId()),
			'path'               => $path,
			'timestamp'          => $file->getMTime(), // FIXME: get the creation date of the file
			'mimetype'           => $file->getMimetype(),
			'modified_timestamp' => $file->getMTime(),
			'etag'               => $file->getEtag(),
			'permissions'        => $file->getPermissions(),
			'size'               => $file->getSize(),
			'favorite'           => false // FIXME: get the favorite status
		];

		$document->setMore($more);
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

			$document->setPath($this->getPathFromViewerId($document->getId(), $document->getViewerId()));

			try {
				$this->updateDocumentFromFilesDocument($document);
			} catch (Exception $e) {
				// TODO - update $document with a error status instead of just ignore !
				$document->getIndex()
						 ->setStatus(Index::INDEX_IGNORE);
				echo 'Exception: ' . json_encode($e->getTrace()) . ' - ' . $e->getMessage() . "\n";
			}

			$index[] = $document;
		}

		return $index;
	}


	/**
	 * @param IndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate($document) {
		$index = $document->getIndex();

		if ($index->getStatus() !== Index::INDEX_OK) {
			return false;
		}

		if ($index->getLastIndex() >= $document->getModifiedTime()) {
			return true;
		}

		return false;
	}


	/**
	 * @param Index $index
	 *
	 * @return FilesDocument
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function updateDocument(Index $index) {
		try {
			$document = $this->generateDocumentFromIndex($index);

			return $document;
		} catch (FileIsNotIndexableException $e) {
			return null;
		}
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function updateDocumentFromFilesDocument(FilesDocument $document) {
		$userFolder = $this->rootFolder->getUserFolder($document->getViewerId());
		$file = $userFolder->get($document->getPath());

		try {
			$this->updateDocumentFromFile($document, $file);
		} catch (FileIsNotIndexableException $e) {
			$document->getIndex()
					 ->setStatus(Index::INDEX_IGNORE);
		}

	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function updateDocumentFromFile(FilesDocument $document, Node $file) {
		$this->updateAccessFromFile($document, $file);
		$this->updateContentFromFile($document, $file);
	}


	/**
	 * @param Index $index
	 *
	 * @return FilesDocument
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function generateDocumentFromIndex(Index $index) {

		$file = $this->getFileFromId($index->getOwnerId(), $index->getDocumentId());
		if ($file === null) {
			$index->setStatus(Index::INDEX_REMOVE);
			$document = new FilesDocument($index->getProviderId(), $index->getDocumentId());
			$document->setIndex($index);

			return $document;
		}

		$document = $this->generateFilesDocumentFromFile($file, $index->getOwnerId());
		$document->setIndex($index);

		$this->updateDocumentFromFile($document, $file);

		return $document;
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function updateAccessFromFile(FilesDocument $document, Node $file) {

		$index = $document->getIndex();
		if (!$index->isStatus(Index::INDEX_FULL)
			&& !$index->isStatus(FilesDocument::STATUS_FILE_ACCESS)) {
			return;
		}

		$access = $this->getDocumentAccessFromFile($file);
		$document->setAccess($access);
		$document->setInfo('share_names', $this->getShareNamesFromFile($file, $access));
		$document->getIndex()
				 ->setOwnerId(
					 $document->getAccess()
							  ->getOwnerId()
				 );

		$this->updateDocumentWithLocalFiles($document, $file);
		$this->externalFilesService->updateDocumentWithExternalFiles($document, $file);

	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws NotFoundException
	 */
	private function updateDocumentWithLocalFiles(FilesDocument $document, Node $file) {

		if ($file->getStorage()
				 ->isLocal() === false) {
			return;
		}

		$document->addTag('local');
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function updateContentFromFile(FilesDocument $document, Node $file) {

		$document->setTitle($document->getPath());

		if (!$document->getIndex()
					  ->isStatus(Index::INDEX_CONTENT)
			|| $file->getType() !== FileInfo::TYPE_FILE) {
			return;
		}

		/** @var File $file */
		if ($file->getSize() <
			($this->configService->getAppValue(ConfigService::FILES_SIZE) * 1024 * 1024)) {
			$this->extractContentFromFileText($document, $file);
			$this->extractContentFromFileOffice($document, $file);
			$this->extractContentFromFilePDF($document, $file);
		} else {
			echo 'NON !';
		}

		if ($document->getContent() === null) {
			$document->getIndex()
					 ->unsetStatus(Index::INDEX_CONTENT);
		}
	}


	/**
	 * @param string $mimeType
	 *
	 * @return string
	 */
	private function parseMimeType($mimeType) {

		if ($mimeType === 'application/octet-stream'
			|| substr($mimeType, 0, 5) === 'text/') {
			return self::MIMETYPE_TEXT;
		}

		if ($mimeType === 'application/pdf') {
			return self::MIMETYPE_PDF;
		}

		$officeMimes = [
			'application/msword',
			'application/vnd.oasis.opendocument',
			'application/vnd.sun.xml',
			'application/vnd.openxmlformats-officedocument',
			'application/vnd.ms-word',
			'application/vnd.ms-powerpoint',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
		];

		if (in_array($mimeType, $officeMimes)) {
			return self::MIMETYPE_OFFICE;
		}

		return '';
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 *
	 * @throws NotPermittedException
	 */
	private function extractContentFromFileText(FilesDocument $document, File $file) {

		if ($this->parseMimeType($document->getMimeType()) !== self::MIMETYPE_TEXT) {
			return;
		}

		// on simple text file, elastic search+attachment pipeline can still detect language, useful ?
//		$document->setContent($file->getContent(), IndexDocument::NOT_ENCODED);

		// We try to avoid error with some base encoding of the document:
		$document->setContent(base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64);
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 *
	 * @throws NotPermittedException
	 */
	private function extractContentFromFilePDF(FilesDocument $document, File $file) {

		if ($this->parseMimeType($document->getMimeType()) !== self::MIMETYPE_PDF) {
			return;
		}

		if ($this->configService->getAppValue('files_pdf') !== '1') {
			return;
		}

		$document->setContent(base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64);
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 *
	 * @throws NotPermittedException
	 */
	private function extractContentFromFileOffice(FilesDocument $document, File $file) {

		if ($this->configService->getAppValue('files_office') !== '1') {
			return;
		}

		if ($this->parseMimeType($document->getMimeType()) !== self::MIMETYPE_OFFICE) {
			return;
		}

		$document->setContent(base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64);
	}


	/**
	 * @param Node $file
	 *
	 * @return DocumentAccess
	 * @throws InvalidPathException
	 * @throws NotFoundException
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
	 * @param DocumentAccess $access
	 *
	 * @return array
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function getShareNamesFromFile(Node $file, DocumentAccess $access) {
		$shareNames = [];

		if ($file->getStorage()
				 ->isLocal() === false) {
			$shares = $this->externalFilesService->getAllSharesFromExternalFile($access);
		} else {
			$shares = $this->getAllSharesFromFile($file);
		}

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