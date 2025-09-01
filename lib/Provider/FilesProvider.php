<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Provider;

use OC\FullTextSearch\Model\SearchOption;
use OC\FullTextSearch\Model\SearchTemplate;
use OC\User\NoUserException;
use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\ExtensionService;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Service\SearchService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\FullTextSearch\IFullTextSearchPlatform;
use OCP\FullTextSearch\IFullTextSearchProvider;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IIndexOptions;
use OCP\FullTextSearch\Model\IRunner;
use OCP\FullTextSearch\Model\ISearchOption;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Model\ISearchTemplate;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Class FilesProvider
 *
 * @package OCA\Files_FullTextSearch\Provider
 */
class FilesProvider implements IFullTextSearchProvider {
	public const FILES_PROVIDER_ID = 'files';
	private ?IRunner $runner = null;
	private IIndexOptions $indexOptions;

	public function __construct(
		private IL10N $l10n,
		private readonly IAppConfig $appConfig,
		private ConfigService $configService,
		private FilesService $filesService,
		private SearchService $searchService,
		private ExtensionService $extensionService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * return unique id of the provider
	 */
	public function getId(): string {
		return self::FILES_PROVIDER_ID;
	}

	/**
	 * return name of the provider
	 */
	public function getName(): string {
		return $this->l10n->t('Files');
	}

	/**
	 * @return array
	 */
	public function getConfiguration(): array {
		$this->logger->debug('getConfiguration request');
		$config = $this->configService->getConfig();
		$this->extensionService->getConfig($config);
		$this->logger->debug('getConfiguration result', ['config' => $config]);

		return $config;
	}

	/**
	 * @param IRunner $runner
	 */
	public function setRunner(IRunner $runner) {
		$this->runner = $runner;
		$this->filesService->setRunner($runner);
	}

	/**
	 * @param IIndexOptions $options
	 */
	public function setIndexOptions(IIndexOptions $options) {
		$this->indexOptions = $options;
	}

	/**
	 * @return ISearchTemplate
	 */
	public function getSearchTemplate(): ISearchTemplate {
		$template = new SearchTemplate('icon-fts-files', 'fulltextsearch');

		$template->addPanelOption(
			new SearchOption(
				'files_within_dir', $this->l10n->t('Within current directory'),
				ISearchOption::CHECKBOX
			)
		);

		$template->addPanelOption(
			new SearchOption(
				'files_local', $this->l10n->t('Within local files'),
				ISearchOption::CHECKBOX
			)
		);
		$template->addNavigationOption(
			new SearchOption(
				'files_local', $this->l10n->t('Local files'),
				ISearchOption::CHECKBOX
			)
		);

		if ($this->appConfig->getAppValueInt(ConfigLexicon::FILES_EXTERNAL) === 1) {
			$template->addPanelOption(
				new SearchOption(
					'files_external', $this->l10n->t('Within external files'),
					ISearchOption::CHECKBOX
				)
			);
			$template->addNavigationOption(
				new SearchOption(
					'files_external', $this->l10n->t('External files'), ISearchOption::CHECKBOX
				)
			);
		}

		if ($this->appConfig->getAppValueBool(ConfigLexicon::FILES_GROUP_FOLDERS)) {
			$template->addPanelOption(
				new SearchOption(
					'files_group_folders', $this->l10n->t('Within group folders'),
					ISearchOption::CHECKBOX
				)
			);
			$template->addNavigationOption(
				new SearchOption(
					'files_group_folders', $this->l10n->t('Group folders'),
					ISearchOption::CHECKBOX
				)
			);
		}

		$template->addPanelOption(
			new SearchOption(
				'files_extension', $this->l10n->t('Filter by extension'), ISearchOption::INPUT,
				ISearchOption::INPUT_SMALL, 'txt'
			)
		);
		$template->addNavigationOption(
			new SearchOption(
				'files_extension', $this->l10n->t('Extension'), ISearchOption::INPUT,
				ISearchOption::INPUT_SMALL, 'txt'
			)
		);

		return $template;
	}


	/**
	 *
	 */
	public function loadProvider() {
	}


	/**
	 * @param string $userId
	 *
	 * @return array
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function generateChunks(string $userId): array {
		$this->logger->debug('generateChunks request', ['userId' => $userId, 'options' => $this->indexOptions]);
		$chunks = $this->filesService->getChunksFromUser($userId, $this->indexOptions);
		$this->logger->debug('generateChunks result', $chunks);

		return $chunks;
	}


	/**
	 * @param string $userId
	 *
	 * @param string $chunk
	 *
	 * @return IIndexDocument[]
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function generateIndexableDocuments(string $userId, string $chunk): array {
		$this->logger->debug('generateIndexableDocuments request', ['userId' => $userId, 'chunk' => $chunk]);
		$documents = $this->filesService->getFilesFromUser($userId, $chunk);
		$this->logger->debug('generateIndexableDocuments result', ['documents' => count($documents)]);

		return $documents;
	}


	/**
	 * @param IIndexDocument $document
	 */
	public function fillIndexDocument(IIndexDocument $document) {
		/** @var FilesDocument $document */
		$this->updateRunnerInfoArray(
			[
				'info' => $document->getMimetype(),
				'title' => $document->getPath()
			]
		);
		$this->logger->debug('fillIndexDocument request', ['document' => $document]);
		$this->filesService->generateDocument($document);
		$this->logger->debug('fillIndexDocument result', ['document' => $document]);
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate(IIndexDocument $document): bool {
		$this->logger->debug('isDocumentUpToDate request', ['document' => $document]);
		$result = $this->filesService->isDocumentUpToDate($document);
		$this->logger->debug('isDocumentUpToDate result', ['document' => $document, 'result' => $result]);

		return $result;
	}


	/**
	 * @param IIndex $index
	 *
	 * @return IIndexDocument
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws FileIsNotIndexableException
	 */
	public function updateDocument(IIndex $index): IIndexDocument {
		$this->logger->debug('updateDocument request', ['index' => $index]);
		$document = $this->filesService->updateDocument($index);
		$this->logger->debug('updateDocument result', ['index' => $index, 'document' => $document]);
		$this->updateRunnerInfo('info', $document->getMimetype());

		return $document;
	}


	/**
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onInitializingIndex(IFullTextSearchPlatform $platform) {
	}


	/**
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onResettingIndex(IFullTextSearchPlatform $platform) {
	}


	/**
	 * not used yet
	 */
	public function unloadProvider() {
	}


	/**
	 * before a search, improve the request
	 *
	 * @param ISearchRequest $request
	 */
	public function improveSearchRequest(ISearchRequest $searchRequest) {
		$this->searchService->improveSearchRequest($searchRequest);
	}


	/**
	 * after a search, improve results
	 *
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult) {
		$this->searchService->improveSearchResult($searchResult);
	}


	/**
	 * @param string $info
	 * @param string $value
	 */
	private function updateRunnerInfo(string $info, string $value) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfo($info, $value);
	}

	/**
	 * @param array $info
	 */
	private function updateRunnerInfoArray(array $info) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfoArray($info);
	}
}
