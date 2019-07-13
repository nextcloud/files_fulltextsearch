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
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\ISearchResult;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;


/**
 * Class ExtensionService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class ExtensionService {


	/** @var EventDispatcher */
	private $eventDispatcher;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * ExtensionService constructor.
	 *
	 * @param EventDispatcher $eventDispatcher
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		EventDispatcher $eventDispatcher, ConfigService $configService, MiscService $miscService
	) {
		$this->eventDispatcher = $eventDispatcher;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	public function fileIndexing(FilesDocument &$document, Node $file) {
		$this->dispatch(
			'\OCA\Files_FullTextSearch::onFileIndexing',
			['file' => $file, 'document' => &$document]
		);
	}


	/**
	 * @param ISearchRequest $request
	 */
	public function searchRequest(ISearchRequest &$request) {
		$this->dispatch(
			'\OCA\Files_FullTextSearch::onSearchRequest',
			['request' => &$request]
		);
	}


	/**
	 * @param ISearchResult $result
	 */
	public function searchResult(ISearchResult &$result) {
		$this->dispatch(
			'\OCA\Files_FullTextSearch::onSearchResult',
			['result' => &$result]
		);
	}


	/**
	 * @param string $context
	 * @param array $arguments
	 */
	private function dispatch(string $context, array $arguments) {
		$this->eventDispatcher->dispatch($context, new GenericEvent(null, $arguments));
	}

}

