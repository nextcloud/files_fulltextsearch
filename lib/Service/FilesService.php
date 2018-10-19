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
use OC\App\AppManager;
use OCA\Files_FullTextSearch\Exceptions\EmptyUserException;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\FilesNotFoundException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileMimeTypeException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCA\FullTextSearch\Model\Index;
use OCA\FullTextSearch\Model\IndexDocument;
use OCA\FullTextSearch\Model\IndexOptions;
use OCA\FullTextSearch\Model\Runner;
use OCP\AppFramework\IAppContainer;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\StorageNotAvailableException;
use OCP\IUserManager;
use OCP\Share\IManager;

class FilesService {

	const MIMETYPE_TEXT = 'files_text';
	const MIMETYPE_PDF = 'files_pdf';
	const MIMETYPE_OFFICE = 'files_office';
	const MIMETYPE_IMAGE = 'files_image';
	const MIMETYPE_AUDIO = 'files_audio';


	/** @var IAppContainer */
	private $container;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var AppManager */
	private $appManager;

	/** @var IManager */
	private $shareManager;

	/** @var ConfigService */
	private $configService;

	/** @var LocalFilesService */
	private $localFilesService;

	/** @var ExternalFilesService */
	private $externalFilesService;

	/** @var GroupFoldersService */
	private $groupFoldersService;

	/** @var ExtensionService */
	private $extensionService;

	/** @var MiscService */
	private $miscService;


	/** @var Runner */
	private $runner;

	/** @var int */
	private $sumDocuments;

	/**
	 * FilesService constructor.
	 *
	 * @param IAppContainer $container
	 * @param IRootFolder $rootFolder
	 * @param AppManager $appManager
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param ConfigService $configService
	 * @param LocalFilesService $localFilesService
	 * @param ExternalFilesService $externalFilesService
	 * @param GroupFoldersService $groupFoldersService
	 * @param ExtensionService $extensionService
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	public function __construct(
		IAppContainer $container, IRootFolder $rootFolder, AppManager $appManager,
		IUserManager $userManager, IManager $shareManager,
		ConfigService $configService, LocalFilesService $localFilesService,
		ExternalFilesService $externalFilesService, GroupFoldersService $groupFoldersService,
		ExtensionService $extensionService, MiscService $miscService
	) {
		$this->container = $container;
		$this->rootFolder = $rootFolder;
		$this->appManager = $appManager;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->configService = $configService;
		$this->localFilesService = $localFilesService;
		$this->externalFilesService = $externalFilesService;
		$this->groupFoldersService = $groupFoldersService;
		$this->extensionService = $extensionService;

		$this->miscService = $miscService;
	}


	public function setRunner(Runner $runner) {
		$this->runner = $runner;
	}


	/**
	 * @param string $userId
	 * @param IndexOptions $indexOptions
	 *
	 * @return FilesDocument[]
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getFilesFromUser($userId, $indexOptions) {

		$this->initFileSystems($userId);
		$this->sumDocuments = 0;

		/** @var Folder $files */
		$files = $this->rootFolder->getUserFolder($userId)
								  ->get($indexOptions->getOption('path', '/'));

		if ($files instanceof Folder) {
			$result = $this->getFilesFromDirectory($userId, $files);
		} else {
			$result = [];
			try {
				$result[] = $this->generateFilesDocumentFromFile($userId, $files);
			} catch (FileIsNotIndexableException $e) {
				/** we do nothin' */
			}
		}

		return $result;
	}


	/**
	 * @param string $userId
	 */
	private function initFileSystems($userId) {
		if ($userId === '') {
			return;
		}

		$this->externalFilesService->initExternalFilesForUser($userId);
		$this->groupFoldersService->initGroupSharesForUser($userId);
	}


	/**
	 * @param string $userId
	 * @param Folder $node
	 *
	 * @return FilesDocument[]
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function getFilesFromDirectory($userId, Folder $node) {
		$documents = [];

		$this->updateRunnerAction('generateIndexFiles');
		$this->updateRunnerInfo(
			[
				'info'          => $node->getPath(),
				'documentTotal' => $this->sumDocuments
			]
		);

		try {
			if ($node->nodeExists('.noindex')) {
				return $documents;
			}
		} catch (StorageNotAvailableException $e) {
			return $documents;
		}

		$files = $node->getDirectoryListing();
		foreach ($files as $file) {

			try {
				$documents[] = $this->generateFilesDocumentFromFile($userId, $file);
				$this->sumDocuments++;
			} catch (FileIsNotIndexableException $e) {
				continue;
			}

			if ($file->getType() === FileInfo::TYPE_FOLDER) {
				/** @var $file Folder */
				$documents =
					array_merge($documents, $this->getFilesFromDirectory($userId, $file));
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
	 * @throws Exception
	 */
	private function generateFilesDocumentFromFile($viewerId, Node $file) {

		$source = $this->getFileSource($file);
		$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, $file->getId());

		if ($file->getId() === -1) {
			throw new FileIsNotIndexableException();
		}

		$ownerId = '';
		if ($file->getOwner() !== null) {
			$ownerId = $file->getOwner()
							->getUID();
		}
		$document->setType($file->getType())
				 ->setSource($source)
				 ->setOwnerId($ownerId)
				 ->setPath($this->getPathFromViewerId($file->getId(), $viewerId))
				 ->setViewerId($viewerId)
				 ->setModifiedTime($file->getMTime())
				 ->setMimetype($file->getMimetype());

		return $document;
	}


	/**
	 * @param Node $file
	 *
	 * @return string
	 * @throws FileIsNotIndexableException
	 * @throws NotFoundException
	 */
	private function getFileSource(Node $file) {
		$source = '';

		try {
			$this->localFilesService->getFileSource($file, $source);
			$this->externalFilesService->getFileSource($file, $source);
			$this->groupFoldersService->getFileSource($file, $source);
		} catch (KnownFileSourceException $e) {
			/** we know the source, just leave. */
		}

		return $source;
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
	 * @throws FilesNotFoundException
	 * @throws EmptyUserException
	 */
	public function getFileFromId($userId, $fileId) {

		if ($userId === '') {
			throw new EmptyUserException();
		}

		$files = $this->rootFolder->getUserFolder($userId)
								  ->getById($fileId);
		if (sizeof($files) === 0) {
			throw new FilesNotFoundException();
		}

		$file = array_shift($files);

		return $file;
	}


	/**
	 * @param Index $index
	 *
	 * @return Node
	 * @throws EmptyUserException
	 * @throws FilesNotFoundException
	 */
	public function getFileFromIndex(Index $index) {
		$this->impersonateOwner($index);

		return $this->getFileFromId($index->getOwnerId(), $index->getDocumentId());
	}


	/**
	 * @param int $fileId
	 * @param string $viewerId
	 *
	 * @throws Exception
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
	 * @param FilesDocument $document
	 */
	public function generateDocument(FilesDocument $document) {

		try {
			$this->updateFilesDocument($document);
		} catch (Exception $e) {
			// TODO - update $document with a error status instead of just ignore !
			$document->getIndex()
					 ->setStatus(Index::INDEX_IGNORE);
			echo 'Exception: ' . json_encode($e->getTrace()) . ' - ' . $e->getMessage()
				 . "\n";
		}
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

		try {
			$file = $this->getFileFromIndex($index);
		} catch (Exception $e) {
			$index->setStatus(Index::INDEX_REMOVE);
			$document = new FilesDocument($index->getProviderId(), $index->getDocumentId());
			$document->setIndex($index);

			return $document;
		}

		$document = $this->generateFilesDocumentFromFile($index->getOwnerId(), $file);
		$document->setIndex($index);

		$this->updateFilesDocumentFromFile($document, $file);

		return $document;
	}


	/**
	 * @param IndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate($document) {
		$index = $document->getIndex();

		if (!$this->configService->compareIndexOptions($index)) {
			$index->setStatus(Index::INDEX_CONTENT);
			$document->setIndex($index);

			return false;
		}

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
	 * @throws FileIsNotIndexableException
	 */
	public function updateDocument(Index $index) {
		$this->impersonateOwner($index);
		$this->initFileSystems($index->getOwnerId());

		return $this->generateDocumentFromIndex($index);
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function updateFilesDocument(FilesDocument $document) {
		$userFolder = $this->rootFolder->getUserFolder($document->getViewerId());
		$file = $userFolder->get($document->getPath());

		try {
			$this->updateFilesDocumentFromFile($document, $file);
		} catch (FileIsNotIndexableException $e) {
			$document->getIndex()
					 ->setStatus(Index::INDEX_IGNORE);
		}
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function updateFilesDocumentFromFile(FilesDocument $document, Node $file) {

		$document->getIndex()
				 ->setSource($document->getSource());

		$this->updateDocumentAccess($document, $file);
		$this->updateContentFromFile($document, $file);

		$document->addMetaTag($document->getSource());
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function updateDocumentAccess(FilesDocument $document, Node $file) {

		$index = $document->getIndex();

		if (!$index->isStatus(Index::INDEX_FULL)
			&& !$index->isStatus(FilesDocument::STATUS_FILE_ACCESS)) {
			return;
		}

		$this->localFilesService->updateDocumentAccess($document, $file);
		$this->externalFilesService->updateDocumentAccess($document, $file);
		$this->groupFoldersService->updateDocumentAccess($document, $file);

		$this->updateShareNames($document, $file);
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

			$this->extensionService->fileIndexing($document, $file);
		}

		if ($document->getContent() === null) {
			$document->getIndex()
					 ->unsetStatus(Index::INDEX_CONTENT);
		}
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @return array
	 */
	private function updateShareNames(FilesDocument $document, Node $file) {

		$users = [];

		$this->localFilesService->getShareUsersFromFile($file, $users);
		$this->externalFilesService->getShareUsers($document, $users);
		$this->groupFoldersService->getShareUsers($document, $users);

		$shareNames = [];
		foreach ($users as $username) {
			try {
				$user = $this->userManager->get($username);
				if ($user === null || $user->getLastLogin() === 0) {
					continue;
				}

				$path = $this->getPathFromViewerId($file->getId(), $username);
				$shareNames[MiscService::secureUsername($username)] =
					(!is_string($path)) ? $path = '' : $path;

			} catch (Exception $e) {
				$this->miscService->log(
					'Issue while getting information on documentId:' . $document->getId(), 0
				);
			}
		}

		$document->setInfo('share_names', $shareNames);

		return $shareNames;
	}


	/**
	 * @param string $mimeType
	 *
	 * @return string
	 */
	private function parseMimeType($mimeType) {

		$parsed = '';
		try {
			$this->parseMimeTypeText($mimeType, $parsed);
			$this->parseMimeTypePDF($mimeType, $parsed);
			$this->parseMimeTypeOffice($mimeType, $parsed);
		} catch (KnownFileMimeTypeException $e) {
		}

		return $parsed;
	}


	/**
	 * @param string $mimeType
	 * @param string $parsed
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeText($mimeType, &$parsed) {

		if (substr($mimeType, 0, 5) === 'text/') {
			$parsed = self::MIMETYPE_TEXT;
			throw new KnownFileMimeTypeException();
		}

		$textMimes = [
			'application/epub+zip'
		];

		foreach ($textMimes as $mime) {
			if (strpos($mimeType, $mime) === 0) {
				$parsed = self::MIMETYPE_TEXT;
				throw new KnownFileMimeTypeException();
			}
		}
	}


	/**
	 * @param string $mimeType
	 * @param string $parsed
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypePDF($mimeType, &$parsed) {

		if ($mimeType === 'application/pdf') {
			$parsed = self::MIMETYPE_PDF;
			throw new KnownFileMimeTypeException();
		}
	}


	/**
	 * @param string $mimeType
	 * @param string $parsed
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeOffice($mimeType, &$parsed) {

		$officeMimes = [
			'application/msword',
			'application/vnd.oasis.opendocument',
			'application/vnd.sun.xml',
			'application/vnd.openxmlformats-officedocument',
			'application/vnd.ms-word',
			'application/vnd.ms-powerpoint',
			'application/vnd.ms-excel'
		];

		foreach ($officeMimes as $mime) {
			if (strpos($mimeType, $mime) === 0) {
				$parsed = self::MIMETYPE_OFFICE;
				throw new KnownFileMimeTypeException();
			}
		}
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

		if (!$this->isSourceIndexable($document)) {
			return;
		}

		$document->setContent(
			base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64
		);
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

		$this->configService->setDocumentIndexOption($document, ConfigService::FILES_PDF);
		if (!$this->isSourceIndexable($document)) {
			return;
		}

		if ($this->configService->getAppValue(ConfigService::FILES_PDF) !== '1') {
			$document->setContent('');

			return;
		}

		$document->setContent(
			base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64
		);
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 *
	 * @throws NotPermittedException
	 */
	private function extractContentFromFileOffice(FilesDocument $document, File $file) {
		if ($this->parseMimeType($document->getMimeType()) !== self::MIMETYPE_OFFICE) {
			return;
		}

		$this->configService->setDocumentIndexOption($document, ConfigService::FILES_OFFICE);
		if (!$this->isSourceIndexable($document)) {
			return;
		}

		if ($this->configService->getAppValue(ConfigService::FILES_OFFICE) !== '1') {
			$document->setContent('');

			return;
		}

		$document->setContent(
			base64_encode($file->getContent()), IndexDocument::ENCODED_BASE64
		);
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @return bool
	 */
	private function isSourceIndexable(FilesDocument $document) {
		$this->configService->setDocumentIndexOption($document, $document->getSource());
		if ($this->configService->getAppValue($document->getSource()) !== '1') {
			$document->setContent('');

			return false;
		}

		return true;
	}


	private function impersonateOwner(Index $index) {
		if ($index->getOwnerId() !== '') {
			return;
		}

		$this->groupFoldersService->impersonateOwner($index);
		$this->externalFilesService->impersonateOwner($index);
	}


	/**
	 * @param $action
	 * @param bool $force
	 *
	 * @throws Exception
	 */
	private function updateRunnerAction($action, $force = false) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->updateAction($action, $force);
	}


	/**
	 * @param array $data
	 */
	private function updateRunnerInfo($data) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfoArray($data);
	}


}

