<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use Exception;
use OC\FullTextSearch\Model\DocumentAccess;
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
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
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
		private readonly IRootFolder $rootFolder,
		private readonly IAppConfig $appConfig,
		private readonly IUserManager $userManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly ICommentsManager $commentsManager,
		private readonly ISystemTagObjectMapper $systemTagObjectMapper,
		private readonly ISystemTagManager $systemTagManager,
		private readonly ConfigService $configService,
		private readonly LocalFilesService $localFilesService,
		private readonly ExternalFilesService $externalFilesService,
		private readonly GroupFoldersService $groupFoldersService,
		private readonly ExtensionService $extensionService,
		private readonly IFullTextSearchManager $fullTextSearchManager,
		private readonly LoggerInterface $logger,
	) {
	}

	public function setRunner(IRunner $runner): void {
		$this->runner = $runner;
	}

	/**
	 *
	 * @return string[]
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	public function getChunksFromUser(string $userId, IIndexOptions $indexOptions): array {
		$this->initFileSystems($userId);

		try {
			$files = $this->rootFolder->getUserFolder($userId)
				->get($indexOptions->getOption('path', '/'));
		} catch (NotFoundException) {
			return [];
		} catch (Throwable $e) {
			$this->logger->warning('Issue while retrieving rootFolder for ' . $userId, ['exception' => $e]);
			return [];
		}

		if (!$files instanceof Folder) {
			$this->logger->debug('object from getChunksFromUser is not a Folder', ['path' => $files->getPath()]);

			return [$this->getPathFromRoot($files->getPath(), $userId, true)];
		}

		$this->logger->debug('object from getChunksFromUser is a Folder');
		$chunks = array_map(strval(...), $this->getChunksFromDirectory($userId, $files));
		$this->logger->debug('getChunksFromUser result', ['chunks' => $chunks]);

		return $chunks;
	}

	/**
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
			} catch (FileIsNotIndexableException) {
				/** we do nothin' */
			}
		}

		return $result;
	}

	/**
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
		} catch (StorageNotAvailableException) {
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
			} catch (FileIsNotIndexableException) {
				continue;
			}

			if ($file->getType() === FileInfo::TYPE_FOLDER) {
				/** @var Folder $file */
				$documents = array_merge($documents, $this->getFilesFromDirectory($userId, $file));
			}
		}

		return $documents;
	}

	private function initFileSystems(string $userId): void {
		$this->logger->debug('initFileSystems', ['userId' => $userId]);

		if ($userId === '') {
			return;
		}

		if ($this->userManager->get($userId) === null) {
			return;
		}

		$this->groupFoldersService->initGroupSharesForUser($userId);
	}

	private function generateFilesDocumentFromParent(string $userId, Folder $parent): array {
		$documents = [];
		try {
			for ($i = 0; $i < $this->appConfig->getAppValueInt(ConfigLexicon::FILES_CHUNK_SIZE); $i++) {
				$parent = $parent->getParent();
				$documents[] = $this->generateFilesDocumentFromFile($userId, $parent);
			}
		} catch (Exception) {
		}

		return $documents;
	}

	/**
	 *
	 * @throws FileIsNotIndexableException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	private function generateFilesDocumentFromFile(string $viewerId, Node $file): FilesDocument {
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

		$ownerId = '';

		$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, (string)$file->getId());
		$document->setAccess(new DocumentAccess($ownerId));

		try {
			$document->setType($file->getType())
				->setOwnerId($ownerId)
				->setPath($this->getPathFromViewerId($file->getId(), $viewerId))
				->setViewerId($viewerId);
		} catch (Throwable) {
			throw new FileIsNotIndexableException();
		}

		$document->setMimetype($file->getMimetype());

		$document->setModifiedTime($file->getMTime())
			->setSource($source);

		$tagIds = $this->systemTagObjectMapper->getTagIdsForObjects([$file->getId()], 'files');
		if (array_key_exists($file->getId(), $tagIds)) {
			$tags = array_values(
				array_map(fn (ISystemTag $tag): string => $tag->getName(), $this->systemTagManager->getTagsByIds($tagIds[$file->getId()]))
			);
			$document->setTags($tags);
		}

		$document->setModifiedTime($file->getMTime());
		$stat = $file->stat();

		$document->setMore(
			[
				'creationTime' => $this->getInt('ctime', $stat),
				'accessedTime' => $this->getInt('atime', $stat)
			]
		);

		return $document;
	}

	/**
	 * @throws FileIsNotIndexableException
	 */
	private function getFileSource(Node $file): string {
		$source = '';

		try {
			$this->localFilesService->getFileSource($file, $source);
			$this->externalFilesService->getFileSource($file, $source);
			$this->groupFoldersService->getFileSource($file, $source);
		} catch (KnownFileSourceException) {
			/** we know the source, just leave. */
		}

		return $source;
	}

	/**
	 *
	 * @throws NotFoundException
	 */
	public function getFileFromPath(string $userId, string $path): Node {
		return $this->rootFolder->getUserFolder($userId)
			->get($path);
	}

	/**
	 *
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
	 *
	 * @throws EmptyUserException
	 * @throws FilesNotFoundException
	 */
	public function getFileFromIndex(IIndex $index): Node {
		return $this->getFileFromId($index->getOwnerId(), (int)$index->getDocumentId());
	}

	/**
	 *
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
		if ($path === '') {
			throw new FileIsNotIndexableException();
		}

		return rtrim(str_replace('//', '/', $path), '/');
	}

	public function generateDocument(FilesDocument $document): void {
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
	 *
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
		} catch (Exception) {
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
	 *
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
	 * @throws NotFoundException
	 */
	private function updateFilesDocument(FilesDocument $document): void {
		$userFolder = $this->rootFolder->getUserFolder($document->getViewerId());
		$file = $userFolder->get($document->getPath());

		try {
			$this->updateFilesDocumentFromFile($document, $file);
		} catch (FileIsNotIndexableException) {
			$document->getIndex()
				->setStatus(IIndex::INDEX_IGNORE);
		}
	}

	/**
	 *
	 * @throws FileIsNotIndexableException
	 */
	private function updateFilesDocumentFromFile(FilesDocument $document, Node $file): void {
		$document->getIndex()
			->setSource($document->getSource());

		$this->updateDocumentAccess($document, $file);
		$this->updateContentFromFile($document, $file);

		$document->addMetaTag($document->getSource());
	}

	/**
	 *
	 * @throws FileIsNotIndexableException
	 */
	private function updateDocumentAccess(FilesDocument $document, Node $file): void {

		//		$index = $document->getIndex();
		// This should not be needed, let's assume we _need_ to update document access
		//		if (!$index->isStatus(IIndex::INDEX_FULL)
		//			&& !$index->isStatus(IIndex::INDEX_META)) {
		//			return;
		//		}

		$this->localFilesService->updateDocumentAccess($document, $file);
		$this->externalFilesService->updateDocumentAccess($document, $file);
		$this->groupFoldersService->updateDocumentAccess($document, $file);
	}

	private function updateContentFromFile(FilesDocument $document, Node $file): void {
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

		if ($document->getContent() === '') {
			$document->getIndex()
				->unsetStatus(IIndex::INDEX_CONTENT);
		}

		$this->updateCommentsFromFile($document);
	}

	private function updateCommentsFromFile(FilesDocument $document): void {
		$comments = $this->commentsManager->getForObject('files', $document->getId());

		$part = [];
		foreach ($comments as $comment) {
			$part[] = '<' . $comment->getActorId() . '> ' . $comment->getMessage();
		}

		$document->addPart('comments', implode(" \n ", $part));
	}

	private function parseMimeType(string $mimeType, string $extension): string {
		$parsed = '';
		try {
			$this->parseMimeTypeText($mimeType, $extension, $parsed);
			$this->parseMimeTypePDF($mimeType, $parsed);
			$this->parseMimeTypeOffice($mimeType, $parsed);
			$this->parseMimeTypeZip($mimeType, $parsed);
		} catch (KnownFileMimeTypeException) {
		}

		return $parsed;
	}

	/**
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeText(string $mimeType, string $extension, string &$parsed): void {
		if (str_starts_with($mimeType, 'text/')) {
			$parsed = self::MIMETYPE_TEXT;
			throw new KnownFileMimeTypeException();
		}

		if ($mimeType === 'message/rfc822') {
			$parsed = self::MIMETYPE_TEXT;
			throw new KnownFileMimeTypeException();
		}

		// 20220219 Parse XML files as TEXT files
		if (str_starts_with($mimeType, 'application/xml')) {
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
			if (str_starts_with($mimeType, $mime)) {
				$parsed = self::MIMETYPE_TEXT;
				throw new KnownFileMimeTypeException();
			}
		}

		$this->parseMimeTypeTextByExtension($mimeType, $extension, $parsed);
	}

	/**
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeTextByExtension(
		string $mimeType, string $extension, string &$parsed,
	): void {
		$textMimes = [
			'application/octet-stream'
		];
		$textExtension = [
		];

		foreach ($textMimes as $mime) {
			if (str_starts_with($mimeType, $mime)
				&& in_array(
					strtolower($extension), $textExtension
				)) {
				$parsed = self::MIMETYPE_TEXT;
				throw new KnownFileMimeTypeException();
			}
		}
	}

	/**
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypePDF(string $mimeType, string &$parsed): void {
		if ($mimeType === 'application/pdf') {
			$parsed = self::MIMETYPE_PDF;
			throw new KnownFileMimeTypeException();
		}
	}

	/**
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeZip(string $mimeType, string &$parsed): void {
		if ($mimeType === 'application/zip') {
			$parsed = self::MIMETYPE_ZIP;
			throw new KnownFileMimeTypeException();
		}
	}

	/**
	 *
	 * @throws KnownFileMimeTypeException
	 */
	private function parseMimeTypeOffice(string $mimeType, string &$parsed): void {
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
			if (str_starts_with($mimeType, $mime)) {
				$parsed = self::MIMETYPE_OFFICE;
				throw new KnownFileMimeTypeException();
			}
		}
	}

	private function extractContentFromFileText(FilesDocument $document, File $file): void {
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
		} catch (NotPermittedException|LockedException) {
		}
	}

	private function extractContentFromFilePDF(FilesDocument $document, File $file): void {
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
					$diagram_xml = simplexml_load_string((string)$diagram_str);
					$content = $content . ' ' . $this->readDrawioXmlValue($diagram_xml);
				}
			} catch (\Throwable) {
			}

			try {
				$document->setContent(
					// 20220219 Pass content of inflated drawio graph xml
					base64_encode($content), IIndexDocument::ENCODED_BASE64
				);
			} catch (NotPermittedException|LockedException) {
			}
		} else {
			try {
				$document->setContent(
					base64_encode($file->getContent()), IIndexDocument::ENCODED_BASE64
				);
			} catch (NotPermittedException|LockedException) {
			}
		}
	}

	// 20220220 Read Draw.io XML elements and return a space separated
	// strings, stripped of HTML tags, to be indexed.
	/**
	 * @return string
	 */
	private function readDrawioXmlValue(\SimpleXMLElement $element): string|array|null {
		$str = '';
		if ($element['value'] !== null && trim(strval($element['value'])) !== '') {
			$str = $str . ' ' . trim(strval($element['value']));
		}
		if (trim(strval($element)) !== '') {
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

	private function extractContentFromFileZip(FilesDocument $document, File $file): void {
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
		} catch (NotPermittedException|LockedException) {
		}
	}

	/**
	 *
	 * @throws NotPermittedException
	 */
	private function extractContentFromFileOffice(FilesDocument $document, File $file): void {
		if ($this->parseMimeType($document->getMimeType(), $file->getExtension())
			!== self::MIMETYPE_OFFICE) {
			return;
		}

		$this->configService->setDocumentIndexOption($document, ConfigLexicon::FILES_OFFICE);
		if (!$this->isSourceIndexable($document)) {
			return;
		}

		if (str_starts_with($file->getName(), '~$')) {
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
		} catch (NotPermittedException|LockedException) {
		}
	}

	private function isSourceIndexable(FilesDocument $document): bool {
		$this->configService->setDocumentIndexOption($document, $document->getSource());
		if (!$this->configService->getCurrentIndexOptionStatus($document->getSource())) {
			$document->setContent('');

			return false;
		}

		return true;
	}

	private function impersonateOwner(IIndex $index): void {
		if ($index->getOwnerId() !== '') {
			return;
		}

		$this->groupFoldersService->impersonateOwner($index);
		$this->externalFilesService->impersonateOwner($index);
	}

	/**
	 * @param $action
	 *
	 * @throws Exception
	 */
	private function updateRunnerAction(string $action, bool $force = false): void {
		if ($this->runner === null) {
			return;
		}

		$this->runner->updateAction($action, $force);
	}

	private function updateRunnerInfo(array $data): void {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfoArray($data);
	}

	private function manageContentErrorException(IIndexDocument $document, Throwable $t): void {
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

	private function updateDirectoryContentIndex(IIndex $index): void {
		if (!$index->isStatus(IIndex::INDEX_META)) {
			return;
		}

		try {
			$file = $this->getFileFromIndex($index);
			if ($file->getType() === File::TYPE_FOLDER) {
				/** @var Folder $file */
				$this->updateDirectoryMeta($file);
			}
		} catch (Exception) {
		}
	}

	private function updateDirectoryMeta(Folder $node): void {
		try {
			$files = $node->getDirectoryListing();
		} catch (NotFoundException) {
			return;
		}

		foreach ($files as $file) {
			try {
				$this->fullTextSearchManager->updateIndexStatus(
					'files', (string)$file->getId(), IIndex::INDEX_META
				);
			} catch (InvalidPathException|NotFoundException) {
			}
		}
	}

	private function updateNewIndexError(IIndex $index, string $message, string $exception, int $sev,
	): void {
		if ($this->runner === null) {
			return;
		}

		$this->runner->newIndexError($index, $message, $exception, $sev);
	}

	/**
	 * @throws FileIsNotIndexableException
	 */
	private function isNodeIndexable(Node $file): void {
		if ($file instanceof Folder) {
			if ($file->nodeExists('.noindex')) {
				throw new FileIsNotIndexableException();
			}
		}

		try {
			$parent = $file->getParent();
		} catch (NotFoundException) {
			return;
		}
		$parentPath = ltrim(str_replace('//', '/', $parent->getPath()), '/');
		$path = substr((string)$parent->getPath(), 8 + strpos($parentPath, '/'));
		if ($path !== '') {
			$this->isNodeIndexable($parent);
		}
	}

	private function getPathFromRoot(string $path, string $userId, bool $entrySlash = false): string {
		// TODO: better way to do this : we remove the '/userid/files/'
		// TODO: do we need userId, or can we crop the path like in isNodeIndexable()
		$path = substr($path, 8 + strlen($userId));

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
