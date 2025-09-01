<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use Exception;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Class SearchService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class SearchService {
	private string $userId;

	public function __construct(
		IUserSession $userSession,
		private IMimeTypeDetector $mimeTypeDetector,
		private IUrlGenerator $urlGenerator,
		private readonly IAppConfig $appConfig,
		private FilesService $filesService,
		private IConfig $config,
		private ExtensionService $extensionService,
		private LoggerInterface $logger,
	) {
		$user = $userSession->getUser();
		$this->userId = $user?->getUID() ?? '';
	}


	/**
	 * @param ISearchRequest $request
	 */
	public function improveSearchRequest(ISearchRequest $request) {
		$this->searchQueryShareNames($request);
		$this->searchQueryWithinDir($request);
		$this->searchQueryInOptions($request);
		$this->searchQueryFiltersExtension($request);
		$this->searchQueryFiltersSource($request);
		if ($this->userId === '') {
			$this->userId = $this->filesService->secureUsername($request->getAuthor());
		}
		$request->addPart('comments');
		$this->extensionService->searchRequest($request);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryShareNames(ISearchRequest $request) {
		$username = $this->filesService->secureUsername($request->getAuthor());
		$request->addField('share_names.' . $username);

		$request->addWildcardField('title');
		$request->addWildcardField('share_names.' . $username);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryWithinDir(ISearchRequest $request) {
		$currentDir = $request->getOption('files_within_dir');
		if ($currentDir === '') {
			return;
		}

		$username = $this->filesService->secureUsername($request->getAuthor());
		$currentDir = trim(str_replace('//', '/', $currentDir), '/') . '/'; // we want the format 'folder/'
		$request->addRegexFilters(
			[
				['share_names.' . $username => $currentDir . '.*'],
				['title' => $currentDir . '.*']
			]
		);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryFiltersExtension(ISearchRequest $request) {
		$extension = $request->getOption('files_extension');
		if ($extension === '') {
			return;
		}

		$username = $this->filesService->secureUsername($request->getAuthor());
		$request->addRegexFilters(
			[
				['share_names.' . $username => '.*\.' . $extension],
				['title' => '.*\.' . $extension]
			]
		);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryFiltersSource(ISearchRequest $request) {
		$local = $request->getOption('files_local');
		$external = $request->getOption('files_external');
		$groupFolders = $request->getOption('files_group_folders');
		$federated = $request->getOption('files_federated');

		if (count(array_unique([$local, $external, $groupFolders])) === 1) {
			return;
		}

		$this->addMetaTagToSearchRequest($request, 'files_local', (int)$local);
		$this->addMetaTagToSearchRequest($request, 'files_external', (int)$external);
		$this->addMetaTagToSearchRequest($request, 'files_group_folders', (int)$groupFolders);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryInOptions(ISearchRequest $request) {
		$in = $request->getOptionArray('in', []);

		if (in_array('filename', $in)) {
			$username = $this->filesService->secureUsername($request->getAuthor());
			$request->addLimitField('share_names.' . $username);
			$request->addLimitField('title');
		}

		if (in_array('content', $in)) {
			$request->addLimitField('content');
		}
	}


	/**
	 * @param ISearchRequest $request
	 * @param string $tag
	 * @param int $cond
	 */
	private function addMetaTagToSearchRequest(ISearchRequest $request, string $tag, int $cond) {
		if ($cond === 1) {
			$request->addMetaTag($tag);
		}
	}


	/**
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult) {
		$indexDocuments = $searchResult->getDocuments();
		$filesDocuments = [];
		foreach ($indexDocuments as $indexDocument) {
			try {
				$filesDocument = FilesDocument::fromIndexDocument($indexDocument);
				$this->setDocumentInfo($filesDocument);
				$this->setDocumentTitle($filesDocument);
				$this->setDocumentLink($filesDocument);

				if ($filesDocument->getType() === FileInfo::TYPE_FOLDER) {
					$icon = 'icon-folder';
				} else {
					$icon = $this->mimeTypeDetector->mimeTypeIcon($filesDocument->getInfo('mime'));
				}

				$filesDocument->setInfoArray(
					'unified',
					[
						'thumbUrl' => '',
						'icon' => $icon
					]
				);

				$filesDocuments[] = $filesDocument;
			} catch (Exception $e) {
				$this->logger->warning('Exception while improving searchresult', ['exception' => $e]);
			}
		}

		$searchResult->setDocuments($filesDocuments);
		$this->extensionService->searchResult($searchResult);
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @throws Exception
	 */
	private function setDocumentInfo(FilesDocument $document) {
		$document->setInfo('webdav', $this->getWebdavId((int)$document->getId()));

		$file = $this->filesService->getFileFromId($this->userId, (int)$document->getId());

		$this->setDocumentInfoFromFile($document, $file);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function setDocumentInfoFromFile(FilesDocument $document, Node $file) {
		if ($this->userId === '') {
			return;
		}

		// TODO: better way to do this : we remove the '/userId/files/'
		$path = substr($file->getPath(), 7 + strlen($this->userId));
		$path = rtrim(str_replace('//', '/', $path), '/');
		$pathInfo = pathinfo($path);

		$document->setPath($path);
		$document->setInfo('path', $path)
			->setInfo('type', $file->getType())
			->setInfo('file', $pathInfo['basename'])
			->setInfo('dir', $pathInfo['dirname'])
			->setInfo('mime', $file->getMimetype())
			->setInfoBool('favorite', false); // FIXME: get the favorite status

		try {
			$document->setInfoInt('size', $file->getSize())
				->setInfoInt('mtime', $file->getMTime())
				->setInfo('etag', $file->getEtag())
				->setInfoInt('permissions', $file->getPermissions());
		} catch (Exception $e) {
		}
	}


	/**
	 * @param FilesDocument $document
	 */
	private function setDocumentTitle(FilesDocument $document) {
		if (!is_null($document->getPath()) && $document->getPath() !== '') {
			$document->setTitle(ltrim(str_replace('//', '/', $document->getPath()), '/'));
		} else {
			$document->setTitle($document->getTitle());
		}
	}


	/**
	 * @param FilesDocument $document
	 */
	private function setDocumentLink(FilesDocument $document) {
		$path = $document->getPath();
		$filename = $document->getInfo('file');
		$dir = substr($path, 0, -strlen($filename));

		$this->setDocumentLinkDir($document, $dir);
		$this->setDocumentLinkFile($document, $dir, $filename);
	}


	/**
	 * @param FilesDocument $document
	 * @param string $dir
	 * @param string $filename
	 */
	private function setDocumentLinkFile(FilesDocument $document, string $dir, string $filename) {
		if ($document->getInfo('type') !== 'file') {
			return;
		}


		if (!$this->appConfig->getAppValueBool(ConfigLexicon::FILES_OPEN_RESULT_DIRECTLY)) {
			$link = $this->urlGenerator->linkToRoute('files.view.index', ['dir' => $dir, 'scrollto' => $filename]);
		} else {
			$link = $this->urlGenerator->linkToRoute('files.View.showFile', ['fileid' => $document->getId()]);
		}

		$document->setLink($link);
	}


	/**
	 * @param FilesDocument $document
	 * @param string $dir
	 */
	private function setDocumentLinkDir(FilesDocument $document, string $dir) {
		if ($document->getInfo('type') !== 'dir') {
			return;
		}

		$document->setLink(
			$this->urlGenerator->linkToRoute(
				'files.view.index', ['dir' => $dir, 'fileid' => $document->getId()]
			)
		);
	}

	/**
	 * @param int $fileId
	 *
	 * @return string
	 */
	private function getWebdavId(int $fileId): string {
		return sprintf('%08s', $fileId) . $this->config->getSystemValue('instanceid');
	}
}
