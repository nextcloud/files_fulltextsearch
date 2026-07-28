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
		private readonly IMimeTypeDetector $mimeTypeDetector,
		private readonly IURLGenerator $urlGenerator,
		private readonly IAppConfig $appConfig,
		private readonly FilesService $filesService,
		private readonly IConfig $config,
		private readonly ExtensionService $extensionService,
		private readonly LoggerInterface $logger,
	) {
		$user = $userSession->getUser();
		$this->userId = $user?->getUID() ?? '';
	}

	public function improveSearchRequest(ISearchRequest $request): void {
		$request->addWildcardField('title');

		$this->searchQueryInOptions($request);
		$this->searchQueryFiltersExtension($request);
		$this->searchQueryFiltersSource($request);
		if ($this->userId === '') {
			$this->userId = $this->filesService->secureUsername($request->getAuthor());
		}
		$request->addPart('comments');
		$this->extensionService->searchRequest($request);
	}

	private function searchQueryFiltersExtension(ISearchRequest $request): void {
		$extension = $request->getOption('files_extension');
		if ($extension === '') {
			return;
		}

		$this->filesService->secureUsername($request->getAuthor());
		$request->addRegexFilters(
			[
				['title' => '.*\.' . $extension]
			]
		);
	}

	private function searchQueryFiltersSource(ISearchRequest $request): void {
		$local = $request->getOption('files_local');
		$external = $request->getOption('files_external');
		$groupFolders = $request->getOption('files_group_folders');
		$request->getOption('files_federated');

		if (count(array_unique([$local, $external, $groupFolders])) === 1) {
			return;
		}

		$this->addMetaTagToSearchRequest($request, 'files_local', (int)$local);
		$this->addMetaTagToSearchRequest($request, 'files_external', (int)$external);
		$this->addMetaTagToSearchRequest($request, 'files_group_folders', (int)$groupFolders);
	}

	private function searchQueryInOptions(ISearchRequest $request): void {
		$in = $request->getOptionArray('in', []);

		if (in_array('filename', $in)) {
			$request->addLimitField('title');
		}

		if (in_array('content', $in)) {
			$request->addLimitField('content');
		}
	}

	private function addMetaTagToSearchRequest(ISearchRequest $request, string $tag, int $cond): void {
		if ($cond === 1) {
			$request->addMetaTag($tag);
		}
	}

	public function improveSearchResult(ISearchResult $searchResult): void {
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
	 * @throws Exception
	 */
	private function setDocumentInfo(FilesDocument $document): void {
		$document->setInfo('webdav', $this->getWebdavId((int)$document->getId()));

		$file = $this->filesService->getFileFromId($this->userId, (int)$document->getId());

		$this->setDocumentInfoFromFile($document, $file);
	}

	private function setDocumentInfoFromFile(FilesDocument $document, Node $file): void {
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
		} catch (Exception) {
		}
	}

	private function setDocumentTitle(FilesDocument $document): void {
		if ($document->getPath() !== '') {
			$document->setTitle(ltrim(str_replace('//', '/', $document->getPath()), '/'));
		} else {
			$document->setTitle($document->getTitle());
		}
	}

	private function setDocumentLink(FilesDocument $document): void {
		$path = $document->getPath();
		$filename = $document->getInfo('file');
		$dir = substr($path, 0, -strlen($filename));

		$this->setDocumentLinkDir($document, $dir);
		$this->setDocumentLinkFile($document, $dir, $filename);
	}

	private function setDocumentLinkFile(FilesDocument $document, string $dir, string $filename): void {
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

	private function setDocumentLinkDir(FilesDocument $document, string $dir): void {
		if ($document->getInfo('type') !== 'dir') {
			return;
		}

		$document->setLink(
			$this->urlGenerator->linkToRoute(
				'files.view.index', ['dir' => $dir, 'fileid' => $document->getId()]
			)
		);
	}

	private function getWebdavId(int $fileId): string {
		return sprintf('%08s', $fileId) . $this->config->getSystemValue('instanceid');
	}
}
