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


namespace OCA\Files_FullTextSearch\Provider;

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc22\TNC22Logger;
use OC\FullTextSearch\Model\SearchOption;
use OC\FullTextSearch\Model\SearchTemplate;
use OC\User\NoUserException;
use OCA\Files_FullTextSearch\AppInfo\Application;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\ExtensionService;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Service\MiscService;
use OCA\Files_FullTextSearch\Service\SearchService;
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

/**
 * Class FilesProvider
 *
 * @package OCA\Files_FullTextSearch\Provider
 */
class FilesProvider implements IFullTextSearchProvider {
	use TNC22Logger;

	public const FILES_PROVIDER_ID = 'files';


	/** @var IL10N */
	private $l10n;

	/** @var ConfigService */
	private $configService;

	/** @var FilesService */
	private $filesService;

	/** @var SearchService */
	private $searchService;

	/** @var ExtensionService */
	private $extensionService;

	/** @var MiscService */
	private $miscService;

	/** @var IRunner */
	private $runner;

	/** @var IIndexOptions */
	private $indexOptions;


	public function __construct(
		IL10N $l10n, ConfigService $configService, FilesService $filesService,
		SearchService $searchService, ExtensionService $extensionService, MiscService $miscService
	) {
		$this->l10n = $l10n;
		$this->configService = $configService;
		$this->filesService = $filesService;
		$this->searchService = $searchService;
		$this->extensionService = $extensionService;
		$this->miscService = $miscService;

		$this->setup('app', Application::APP_ID);
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
		return 'Files';
	}


	/**
	 * @return array
	 */
	public function getConfiguration(): array {
		$this->debug('getConfiguration request');
		$config = $this->configService->getConfig();
		$this->extensionService->getConfig($config);
		$this->debug('getConfiguration result', $config);

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

		if ($this->configService->getAppValue(ConfigService::FILES_EXTERNAL) === '1') {
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

		if ($this->configService->getAppValue(ConfigService::FILES_GROUP_FOLDERS) === '1') {
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
		$this->debug('generateChunks request', ['userId' => $userId, 'options' => $this->indexOptions]);
		$chunks = $this->filesService->getChunksFromUser($userId, $this->indexOptions);
		$this->debug('generateChunks result', $chunks);

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
		$this->debug('generateIndexableDocuments request', ['userId' => $userId, 'chunk' => $chunk]);
		$documents = $this->filesService->getFilesFromUser($userId, $chunk);
		$this->debug('generateIndexableDocuments result', ['documents' => count($documents)]);

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
		$this->debug('fillIndexDocument request', ['document' => $document]);
		$this->filesService->generateDocument($document);
		$this->debug('fillIndexDocument result', ['document' => $document]);
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate(IIndexDocument $document): bool {
		$this->debug('isDocumentUpToDate request', ['document' => $document]);
		$result = $this->filesService->isDocumentUpToDate($document);
		$this->debug('isDocumentUpToDate result', ['document' => $document, 'result' => $result]);

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
		$this->debug('updateDocument request', ['index' => $index]);
		$document = $this->filesService->updateDocument($index);
		$this->debug('updateDocument result', ['index' => $index, 'document' => $document]);
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
	public function improveSearchRequest(ISearchRequest $request) {
		$this->searchService->improveSearchRequest($request);
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
