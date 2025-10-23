<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use Exception;
use OC\FullTextSearch\Model\DocumentAccess;
use OC\SystemTag\SystemTagManager;
use OC\SystemTag\SystemTagObjectMapper;
use OC\User\NoUserException;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Exceptions\EmptyUserException;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Exceptions\FilesNotFoundException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileMimeTypeException;
use OCA\Files_FullTextSearch\Exceptions\KnownFileSourceException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCA\Files_FullTextSearch\Tools\Traits\TArrayTools;
use OCP\App\IAppManager;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Comments\ICommentsManager;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\StorageNotAvailableException;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IIndexOptions;
use OCP\FullTextSearch\Model\IRunner;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTag;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class FilesService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class FilesService {
	use TArrayTools;

	public const MIMETYPE_TEXT = 'files_text';
	public const MIMETYPE_PDF = 'files_pdf';
	public const MIMETYPE_OFFICE = 'files_office';
	public const MIMETYPE_ZIP = 'files_zip';

	public const CHUNK_TREE_SIZE = 2;

	private ?IRunner $runner = null;
	private int $sumDocuments;


	public function __construct(
		private IAppContainer $container,
		private IRootFolder $rootFolder,
		private IAppManager $appManager,
		private readonly IAppConfig $appConfig,
		private IUserManager $userManager,
		private IShareManager $shareManager,
		private IURLGenerator $urlGenerator,
		private ICommentsManager $commentsManager,
		private SystemTagObjectMapper $systemTagObjectMapper,
		private SystemTagManager $systemTagManager,
		private ConfigService $configService,
		private LocalFilesService $localFilesService,
		private ExternalFilesService $externalFilesService,
		private GroupFoldersService $groupFoldersService,
		private ExtensionService $extensionService,
		private IFullTextSearchManager $fullTextSearchManager,
		private LoggerInterface $logger,
	) {
	}


	/**
	 * @param IRunner $runner
	 */
	public function setRunner(IRunner $runner) {
		$this->runner = $runner;
	}


	/**
	 * @param string $userId
	 * @param IIndexOptions $indexOptions
	 *
	 * @return FilesDocument[]
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	public function getChunksFromUser(string $userId, IIndexOptions $indexOptions): array {
		$this->initFileSystems($userId);

		/** @var Folder $files */
		try {
			$files = $this->rootFolder->getUserFolder($userId)
				->get($indexOptions->getOption('path', '/'));
		} catch (NotFoundException $e) {
			return [];
		} catch (Throwable $e) {
			$this->logger->warning('Issue while retrieving rootFolder for ' . $userId, ['exception' => $e]);
			return [];
		}

		if ($files instanceof Folder) {
			$this->logger->debug('object from getChunksFromUser is a Folder');
			$chunks = $this->getChunksFromDirectory($userId, $files);
			$this->logger->debug('getChunksFromUser result', ['chunks' => $chunks]);

			return $chunks;
		}

		$this->logger->debug('object from getChunksFromUser is not a Folder', ['path' => $files->getPath()]);

		return [$this->getPathFromRoot($files->getPath(), $userId, true)];
	}


	/**
	 * @param string $userId
	 * @param Folder $node
	 * @param int $level
	 *
	 * @return FilesDocument[]
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function getChunksFromDirectory(string $userId, Folder $node, int $level = 0): array {
		$entries = [];
		$level++;

		$this->logger->debug('getChunksFromDirectory', ['userId' => $userId, 'level' => $level]);
		$files = $node->getDirectoryListing();
		if (empty($files)) {
			$entries[] = $this->getPathFromRoot($node->getPath(), $userId, true);
		}

		foreach ($files as $file) {
			if ($file->getType() === FileInfo::TYPE_FOLDER
				&& $level < $this->appConfig->getAppValueInt(ConfigLexicon::FILES_CHUNK_SIZE)) {
				/** @var Folder $file */
				$entries = array_merge($entries, $this->getChunksFromDirectory($userId, $file, $level));
			} else {
				$entries[] = $this->getPathFromRoot($file->getPath(), $userId, true);
			}
		}

		$this->logger->debug(
			'getChunksFromDirectory result',
			[
				'userId' => $userId,
				'level' => $level,
				'size' => count($entries)
			]
		);

		return $entries;
	}


	/**
	 * @param string $userId
	 * @param string $chunk
	 *
	 * @return FilesDocument[]
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function getFilesFromUser(string $userId, string $chunk): array {
		$this->initFileSystems($userId);
		$this->sumDocuments = 0;

		/** @var Folder $files */
		$files = $this->rootFolder->getUserFolder($userId)
			->get($chunk);

		$result = [];
		if ($files instanceof Folder) {
			$this->logger->debug('object from getFilesFromUser is a Folder', ['chunk' => $chunk]);
			$result = $this->generateFilesDocumentFromParent($userId, $files);

			$result = array_merge($result, $this->getFilesFromDirectory($userId, $files));
		} else {
			$this->logger->debug('object from getFilesFromUser is a File', ['chunk' => $chunk]);
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
	 * @param Folder $node
	 *
	 * @return FilesDocument[]
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function getFilesFromDirectory(string $userId, Folder $node): array {
		$documents = [];

		$this->updateRunnerAction('generateIndexFiles', true);
		$this->updateRunnerInfo(
			[
				'info' => $node->getPath(),
				'title' => '',
				'content' => '',
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

		if (($this->appConfig->getAppValueInt(ConfigLexicon::FILES_EXTERNAL) === 2)
			&& ($node->getMountPoint()->getMountType() === 'external')) {
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
				/** @var Folder $file */
				$documents = array_merge($documents, $this->getFilesFromDirectory($userId, $file));
			}
		}

		return $documents;
	}


	/**
	 * @param string $userId
	 */
	private function initFileSystems(string $userId) {
		$this->logger->debug('initFileSystems', ['userId' => $userId]);

		if ($userId === '') {
			return;
		}

		if ($this->userManager->get($userId) === null) {
			return;
		}

		$this->groupFoldersService->initGroupSharesForUser($userId);
	}


	/**
	 * @param string $userId
	 * @param Folder $parent
	 *
	 * @return array
	 */
	private function generateFilesDocumentFromParent(string $userId, Folder $parent): array {
		$documents = [];
		try {
			for ($i = 0; $i < $this->appConfig->getAppValueInt(ConfigLexicon::FILES_CHUNK_SIZE); $i++) {
				$parent = $parent->getParent();
				$documents[] = $this->generateFilesDocumentFromFile($userId, $parent);
			}
		} catch (Exception $e) {
		}

		return $documents;
	}


	/**
	 * @param string $viewerId
	 * @param Node $file
	 *
	 * @return FilesDocument
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	private function generateFilesDocumentFromFile(string $viewerId, Node $file): FilesDocument {
		if (is_null($file->getId())) {
			throw new NotFoundException();
		}

		$this->isNodeIndexable($file);

		$source = $this->getFileSource($file);
		if ($file->getId() === -1) {
			throw new FileIsNotIndexableException();
		}

		if ($file->getExtension() === 'part') {
			throw new FileIsNotIndexableException('part files are not indexed');
		}

		$ownerId = '';
		if ($file->getOwner() !== null) {
			$ownerId = $file->getOwner()
				->getUID();
		}

		if (!is_string($ownerId)) {
			$ownerId = '';
		}

		$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, (string)$file->getId());
		$document->setAccess(new DocumentAccess($ownerId));

		try {
			$document->setType($file->getType())
				->setOwnerId($ownerId)
				->setPath($this->getPathFromViewerId($file->getId(), $viewerId))
				->setViewerId($viewerId);
		} catch (Throwable $t) {
			throw new FileIsNotIndexableException();
		}

		if (is_string($file->getMimetype())) {
			$document->setMimetype($file->getMimetype());
		}

		$document->setModifiedTime($file->getMTime())
			->setSource($source);

		$tagIds = $this->systemTagObjectMapper->getTagIdsForObjects([$file->getId()], 'files');
		if (array_key_exists($file->getId(), $tagIds)) {
			$tags = array_values(
				array_map(function (ISystemTag $tag): string {
					return $tag->getName();
				}, $this->systemTagManager->getTagsByIds($tagIds[$file->getId()]))
			);
			$document->setTags($tags);
		}

		$document->setModifiedTime($file->getMTime());
		$stat = $file->stat();

		if (is_array($stat)) {
			$document->setMore(
				[
					'creationTime' => $this->getInt('ctime', $stat),
					'accessedTime' => $this->getInt('atime', $stat)
				]
			);
		} else {
			$this->logger->warning('stat() on File #' . $file->getId() . ' is not an array: ' . json_encode($stat));
		}

		return $document;
	}


	/**
	 * @param Node $file
	 *
	 * @return string
	 * @throws FileIsNotIndexableException
	 */
	private function getFileSource(Node $file): string {
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
	public function getFileFromPath(string $userId, string $path): Node {
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
	 * @throws NoUserException
	 */
	public function getFileFromId(string $userId, int $fileId): Node {
		if ($userId === '') {
			throw new EmptyUserException();
		}

		if ($this->userManager->get($userId) === null) {
			throw new NoUserException('User does not exist: ' . $userId);
		}

		$files = $this->rootFolder->getUserFolder($userId)
			->getById($fileId);

		if (sizeof($files) === 0) {
			throw new FilesNotFoundException();
		}

		return array_shift($files);
	}


	/**
	 * @param IIndex $index
	 *
	 * @return Node
	 * @throws EmptyUserException
	 * @throws FilesNotFoundException
	 */
	public function getFileFromIndex(IIndex $index): Node {
		return $this->getFileFromId($index->getOwnerId(), (int)$index->getDocumentId());
	}


	/**
	 * @param int $fileId
	 * @param string $viewerId
	 *
	 * @return string
	 * @throws Exception
	 */
	private function getPathFromViewerId(int $fileId, string $viewerId): string {
		$viewerFiles = $this->rootFolder->getUserFolder($viewerId)
			->getById($fileId);

		if (sizeof($viewerFiles) === 0) {
			return '';
		}

		$file = array_shift($viewerFiles);

		// TODO: better way to do this : we remove the '/userid/files/'
		$path = $this->getPathFromRoot($file->getPath(), $viewerId);
		if (!is_string($path)) {
			throw new FileIsNotIndexableException();
		}

		$path = rtrim(str_replace('//', '/', $path), '/');

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
				->setStatus(IIndex::INDEX_IGNORE);
			$this->logger->warning('Exception while generateDocument', ['exception' => $e]);
		}
	}


	/**
	 * @param IIndex $index
	 *
	 * @return FilesDocument
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function generateDocumentFromIndex(IIndex $index): FilesDocument {
		try {
			$file = $this->getFileFromIndex($index);

			if (($this->appConfig->getAppValueInt(ConfigLexicon::FILES_EXTERNAL) === 2)
				&& ($file->getMountPoint()->getMountType() === 'external')) {
				throw new Exception();
			}
		} catch (Exception $e) {
			$index->setStatus(IIndex::INDEX_REMOVE);
			$document = new FilesDocument($index->getProviderId(), $index->getDocumentId());
			$document->setIndex($index);
			$document->setAccess(new DocumentAccess(''));

			return $document;
		}

		$this->isNodeIndexable($file);

		$document = $this->generateFilesDocumentFromFile($index->getOwnerId(), $file);
		$document->setIndex($index);

		$this->updateFilesDocumentFromFile($document, $file);

		return $document;
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate(IIndexDocument $document): bool {
		$this->extensionService->indexComparing($document);

		$index = $document->getIndex();


		if (!$this->configService->compareIndexOptions($index)) {
			$index->setStatus(IIndex::INDEX_CONTENT);
			$document->setIndex($index);

			return false;
		}

		if ($index->getStatus() !== IIndex::INDEX_OK) {
			return false;
		}

		if ($index->getLastIndex() >= $document->getModifiedTime()) {
			return true;
		}

		return false;
	}


	/**
	 * @param IIndex $index
	 *
	 * @return FilesDocument
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws FileIsNotIndexableException
	 */
	public function updateDocument(IIndex $index): FilesDocument {
		$this->impersonateOwner($index);
		$this->initFileSystems($index->getOwnerId());

		$document = $this->generateDocumentFromIndex($index);
		$this->updateDirectoryContentIndex($index);

		return $document;
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @throws NotFoundException
	 */
	private function updateFilesDocument(FilesDocument $document) {
		$userFolder = $this->rootFolder->getUserFolder($document->getViewerId());
		$file = $userFolder->get($document->getPath());

		try {
			$this->updateFilesDocumentFromFile($document, $file);
		} catch (FileIsNotIndexableException $e) {
			$document->getIndex()
				->setStatus(IIndex::INDEX_IGNORE);
		}
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
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
	 *
	 * @throws FileIsNotIndexableException
	 */
	private function updateDocumentAccess(FilesDocument $document, Node $file) {

		//		$index = $document->getIndex();
		// This should not be needed, let's assume we _need_ to update document access
		//		if (!$index->isStatus(IIndex::INDEX_FULL)
		//			&& !$index->isStatus(IIndex::INDEX_META)) {
		//			return;
		//		}

		$this->localFilesService->updateDocumentAccess($document, $file);
		$this->externalFilesService->updateDocumentAccess($document, $file);
		$this->groupFoldersService->updateDocumentAccess($document, $file);

		$this->updateShareNames($document, $file);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function updateContentFromFile(FilesDocument $document, Node $file) {
		$document->setTitle($document->getPath());
		$document->setLink(
			$this->urlGenerator->linkToRouteAbsolute(
				'files.viewcontroller.showFile',
				['fileid' => $document->getId()]
			)
		);

		if ((!$document->getIndex()
			->isStatus(IIndex::INDEX_CONTENT)
			 && !$document->getIndex()
			 	->isStatus(IIndex::INDEX_META)
		)
		|| $file->getType() !== FileInfo::TYPE_FILE) {
			return;
		}

		try {
			/** @var File $file */
			if ($file->getSize()
				< ($this->appConfig->getAppValueInt(ConfigLexicon::FILES_SIZE) * 1024 * 1024)) {
				$this->extractContentFromFileText($document, $file);
				$this->extractContentFromFileOffice($document, $file);
				$this->extractContentFromFilePDF($document, $file);
				$this->extractContentFromFileZip($document, $file);

				$this->extensionService->fileIndexing($document, $file);
			}
		} catch (Throwable $t) {
			$this->manageContentErrorException($document, $t);
		}

		if ($document->getContent() === null) {
			$document->getIndex()
				->unsetStatus(IIndex::INDEX_CONTENT);
		}

		$this->updateCommentsFromFile($document);
	}


	/**
	 * @param FilesDocument $document
	 */
	private function updateCommentsFromFile(FilesDocument $document) {
		$comments = $this->commentsManager->getForObject('files', $document->getId());

		$part = [];
		foreach ($comments as $comment) {
			$part[] = '<' . $comment->getActorId() . '> ' . $comment->getMessage();
		}

		$document->addPart('comments', implode(" \n ", $part));
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 *
	 * @return array
	 */
	private function updateShareNames(FilesDocument $document, Node $file): array {
		$users = [];

		$this->localFilesService->getShareUsersFromFile($file, $users);
		$this->externalFilesService->getShareUsers($document, $users);
		$this->groupFoldersService->getShareUsers($document, $users);

		$shareNames = [];
		foreach ($users as $username) {
			$username = (string)$username;

			try {
				$user = $this->userManager->get($username);
				if ($user === null || $user->getLastLogin() === 0) {
					continue;
				}

				$path = $this->getPathFromViewerId($file->getId(), $username);
				$shareNames[$this->secureUsername($username)]
					= (!is_string($path)) ? $path = '' : $path;
			} catch (Throwable $e) {
				$this->logger->debug('Issue while getting information on documentId:' . $document->getId(), ['exception' => $e]);
			}
		}

		$document->setInfoArray('share_names', $shareNames);

		return $shareNames;
	}


	/**
	 * @param string $mimeType
	 * @param string $extension
	 *
	 * @return string
	 */
	private function parseMimeType(string $mimeType, string $extension): string {
		$parsed = '';
		try {
			$this->parseMimeTypeText($mimeType, $extension, $parsed);
			$this->parseMimeTypePDF($mimeType, $parsed);
			$this->parseMimeTypeOffice($mimeType, $parsed);
			$this->parseMimeTypeZip($mimeType, $parsed);
		} catch (KnownFileMimeTypeException $e) {
		}

		return $parsed;
	}


	/**
	 * @param string $mimeType
	 * @param string $extension
	 * @param string $parsed
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeText(string $mimeType, string $extension, string &$parsed) {
		if (substr($mimeType, 0, 5) === 'text/') {
			$parsed = self::MIMETYPE_TEXT;
			throw new KnownFileMimeTypeException();
		}

		if ($mimeType === 'message/rfc822') {
			$parsed = self::MIMETYPE_TEXT;
			throw new KnownFileMimeTypeException();
		}

		// 20220219 Parse XML files as TEXT files
		if (substr($mimeType, 0, 15) === 'application/xml') {
			$parsed = self::MIMETYPE_TEXT;
			throw new KnownFileMimeTypeException();
		}

		// 20220219 Parse .drawio file
		if ($extension === 'drawio') {
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

		$this->parseMimeTypeTextByExtension($mimeType, $extension, $parsed);
	}


	/**
	 * @param string $mimeType
	 * @param string $extension
	 * @param string $parsed
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeTextByExtension(
		string $mimeType, string $extension, string &$parsed,
	) {
		$textMimes = [
			'application/octet-stream'
		];
		$textExtension = [
		];

		foreach ($textMimes as $mime) {
			if (strpos($mimeType, $mime) === 0
				&& in_array(
					strtolower($extension), $textExtension
				)) {
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
	private function parseMimeTypePDF(string $mimeType, string &$parsed) {
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
	private function parseMimeTypeZip(string $mimeType, string &$parsed) {
		if ($mimeType === 'application/zip') {
			$parsed = self::MIMETYPE_ZIP;
			throw new KnownFileMimeTypeException();
		}
	}


	/**
	 * @param string $mimeType
	 * @param string $parsed
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeOffice(string $mimeType, string &$parsed) {
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
	 */
	private function extractContentFromFileText(FilesDocument $document, File $file) {
		if ($this->parseMimeType($document->getMimeType(), $file->getExtension())
			!== self::MIMETYPE_TEXT) {
			return;
		}

		if (!$this->isSourceIndexable($document)) {
			return;
		}

		try {
			$document->setContent(
				base64_encode($file->getContent()), IIndexDocument::ENCODED_BASE64
			);
		} catch (NotPermittedException|LockedException $e) {
		}
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 */
	private function extractContentFromFilePDF(FilesDocument $document, File $file) {
		if ($this->parseMimeType($document->getMimeType(), $file->getExtension())
			!== self::MIMETYPE_PDF) {
			return;
		}

		$this->configService->setDocumentIndexOption($document, ConfigLexicon::FILES_PDF);
		if (!$this->isSourceIndexable($document)) {
			return;
		}

		if (!$this->appConfig->getAppValueBool(ConfigLexicon::FILES_PDF)) {
			$document->setContent('');

			return;
		}

		// 20220219 Inflate drawio file
		if ($file->getExtension() === 'drawio') {
			$content = $file->getContent();

			try {
				$xml = simplexml_load_string($content);

				// Initialize $content
				$content = '';

				foreach ($xml->diagram as $child) {
					$deflated_content = (string)$child;
					$base64decoded = base64_decode($deflated_content);
					$urlencoded_content = gzinflate($base64decoded);
					$urldecoded_content = urldecode($urlencoded_content);

					// Remove image tag
					$diagram_str = preg_replace('/style=\"shape=image[^"]*\"/', '', $urldecoded_content);

					// Construct XML
					$diagram_xml = simplexml_load_string($diagram_str);
					$content = $content . ' ' . $this->readDrawioXmlValue($diagram_xml);
				}
			} catch (\Throwable $t) {
			}

			try {
				$document->setContent(
					// 20220219 Pass content of inflated drawio graph xml
					base64_encode($content), IIndexDocument::ENCODED_BASE64
				);
			} catch (NotPermittedException|LockedException $e) {
			}
		} else {
			try {
				$document->setContent(
					base64_encode($file->getContent()), IIndexDocument::ENCODED_BASE64
				);
			} catch (NotPermittedException|LockedException $e) {
			}
		}
	}

	// 20220220 Read Draw.io XML elements and return a space separated
	// strings, stripped of HTML tags, to be indexed.
	/**
	 * @param SimpleXMLElement $element
	 *
	 * @return string
	 */
	private function readDrawioXmlValue(\SimpleXMLElement $element) {
		$str = '';
		if ($element['value'] !== null && trim(strval($element['value'])) !== '') {
			$str = $str . ' ' . trim(strval($element['value']));
		}
		if ($element !== null && trim(strval($element)) !== '') {
			$str = $str . ' ' . trim(strval($element));
		}

		try {
			foreach ($element->children() as $child) {
				$str = $str . ' ' . $this->readDrawioXmlValue($child);
			}
		} finally {
		}

		// Strip HTML tags
		$str_without_tags = preg_replace('/<[^>]*>/', ' ', $str);

		return $str_without_tags;
	}

	/**
	 * @param FilesDocument $document
	 * @param File $file
	 */
	private function extractContentFromFileZip(FilesDocument $document, File $file) {
		if ($this->parseMimeType($document->getMimeType(), $file->getExtension())
			!== self::MIMETYPE_ZIP) {
			return;
		}

		$this->configService->setDocumentIndexOption($document, ConfigLexicon::FILES_ZIP);
		if (!$this->isSourceIndexable($document)) {
			return;
		}

		if (!$this->appConfig->getAppValueBool(ConfigLexicon::FILES_ZIP)) {
			$document->setContent('');

			return;
		}

		try {
			$document->setContent(
				base64_encode($file->getContent()), IIndexDocument::ENCODED_BASE64
			);
		} catch (NotPermittedException|LockedException $e) {
		}
	}


	/**
	 * @param FilesDocument $document
	 * @param File $file
	 *
	 * @throws NotPermittedException
	 */
	private function extractContentFromFileOffice(FilesDocument $document, File $file) {
		if ($this->parseMimeType($document->getMimeType(), $file->getExtension())
			!== self::MIMETYPE_OFFICE) {
			return;
		}

		$this->configService->setDocumentIndexOption($document, ConfigLexicon::FILES_OFFICE);
		if (!$this->isSourceIndexable($document)) {
			return;
		}

		if (substr($file->getName(), 0, 2) === '~$') {
			return;
		}

		if (!$this->appConfig->getAppValueBool(ConfigLexicon::FILES_OFFICE)) {
			$document->setContent('');

			return;
		}

		try {
			$document->setContent(
				base64_encode($file->getContent()), IIndexDocument::ENCODED_BASE64
			);
		} catch (NotPermittedException|LockedException $e) {
		}
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @return bool
	 */
	private function isSourceIndexable(FilesDocument $document): bool {
		$this->configService->setDocumentIndexOption($document, $document->getSource());
		if (!$this->configService->getCurrentIndexOptionStatus($document->getSource())) {
			$document->setContent('');

			return false;
		}

		return true;
	}


	/**
	 * @param IIndex $index
	 */
	private function impersonateOwner(IIndex $index) {
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
	private function updateRunnerAction(string $action, bool $force = false) {
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

	/**
	 * @param IIndexDocument $document
	 * @param Throwable $t
	 */
	private function manageContentErrorException(IIndexDocument $document, Throwable $t) {
		$document->getIndex()
			->addError(
				'Error while getting file content',
				$t->getMessage(),
				IIndex::ERROR_SEV_3
			);
		$this->updateNewIndexError(
			$document->getIndex(),
			'Error while getting file content',
			$t->getMessage(),
			IIndex::ERROR_SEV_3
		);

		$this->logger->debug('content error', ['exception' => $t]);
	}


	/**
	 * @param IIndex $index
	 */
	private function updateDirectoryContentIndex(IIndex $index) {
		if (!$index->isStatus(IIndex::INDEX_META)) {
			return;
		}

		try {
			$file = $this->getFileFromIndex($index);
			if ($file->getType() === File::TYPE_FOLDER) {
				/** @var Folder $file */
				$this->updateDirectoryMeta($file);
			}
		} catch (Exception $e) {
		}
	}


	/**
	 * @param Folder $node
	 */
	private function updateDirectoryMeta(Folder $node) {
		try {
			$files = $node->getDirectoryListing();
		} catch (NotFoundException $e) {
			return;
		}

		foreach ($files as $file) {
			try {
				$this->fullTextSearchManager->updateIndexStatus(
					'files', (string)$file->getId(), IIndex::INDEX_META
				);
			} catch (InvalidPathException $e) {
			} catch (NotFoundException $e) {
			}
		}
	}


	/**
	 * @param IIndex $index
	 * @param string $message
	 * @param string $exception
	 * @param int $sev
	 */
	private function updateNewIndexError(IIndex $index, string $message, string $exception, int $sev,
	) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->newIndexError($index, $message, $exception, $sev);
	}


	/**
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 */
	private function isNodeIndexable(Node $file) {
		if ($file->getType() === File::TYPE_FOLDER) {
			/** @var Folder $file */
			if ($file->nodeExists('.noindex')) {
				throw new FileIsNotIndexableException();
			}
		}

		try {
			$parent = $file->getParent();
		} catch (NotFoundException $e) {
			return;
		}
		$parentPath = ltrim(str_replace('//', '/', $parent->getPath()), '/');
		$path = substr($parent->getPath(), 8 + strpos($parentPath, '/'));
		if (is_string($path)) {
			$this->isNodeIndexable($parent);
		}
	}


	/**
	 * @param string $path
	 * @param string $userId
	 * @param bool $entrySlash
	 *
	 * @return string
	 */
	private function getPathFromRoot(string $path, string $userId, bool $entrySlash = false): string {
		// TODO: better way to do this : we remove the '/userid/files/'
		// TODO: do we need userId, or can we crop the path like in isNodeIndexable()
		$path = substr($path, 8 + strlen($userId));
		if (!is_string($path)) {
			$path = '';
		}

		$result = (($entrySlash) ? '/' : '') . $path;
		$this->logger->debug(
			'getPathFromRoot', [
				'path' => $path,
				'userId' => $userId,
				'entrySlash' => $entrySlash,
				'result' => $result
			]
		);

		return $result;
	}

	public function secureUsername(string $username): string {
		return str_replace('.', '\.', $username);
	}
}
