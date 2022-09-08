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


namespace OCA\Files_FullTextSearch\Service;

use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;

/**
 * Class ExtensionService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class ExtensionService {


	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * ExtensionService constructor.
	 *
	 * @param IEventDispatcher $eventDispatcher
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IEventDispatcher $eventDispatcher, ConfigService $configService, MiscService $miscService
	) {
		$this->eventDispatcher = $eventDispatcher;
		$this->configService = $configService;
		$this->miscService = $miscService;
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
