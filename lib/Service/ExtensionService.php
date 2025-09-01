<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;

class ExtensionService {
	public function __construct(
		private IEventDispatcher $eventDispatcher,
	) {
	}


	/**
	 * @param array $config
	 */
	public function getConfig(array &$config) {
		$this->dispatch('Files_FullTextSearch.onGetConfig', ['config' => &$config]);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function fileIndexing(FilesDocument $document, Node $file) {
		$this->dispatch('Files_FullTextSearch.onFileIndexing', ['file' => $file, 'document' => $document]);
	}


	/**
	 * @param ISearchRequest $request
	 */
	public function searchRequest(ISearchRequest $request) {
		$this->dispatch('Files_FullTextSearch.onSearchRequest', ['request' => $request]);
	}


	/**
	 * @param ISearchResult $result
	 */
	public function searchResult(ISearchResult $result) {
		$this->dispatch('Files_FullTextSearch.onSearchResult', ['result' => $result]);
	}


	/**
	 * @param IIndexDocument $document
	 */
	public function indexComparing(IIndexDocument $document) {
		$this->dispatch('Files_FullTextSearch.onIndexComparing', ['document' => $document]);
	}


	/**
	 * @param string $subject
	 * @param array $arguments
	 */
	private function dispatch(string $subject, array $arguments) {
		$this->eventDispatcher->dispatchTyped(new GenericEvent($subject, $arguments));
	}
}
