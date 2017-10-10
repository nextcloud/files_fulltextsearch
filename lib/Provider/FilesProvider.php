<?php
/**
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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
 *
 */

namespace OCA\Files_FullNextSearch\Provider;

use OCA\Files_FullNextSearch\AppInfo\Application;
use OCA\Files_FullNextSearch\Model\FilesDocument;
use OCA\FullNextSearch\Exceptions\NoResultException;
use OCA\FullNextSearch\INextSearchPlatform;
use OCA\FullNextSearch\INextSearchProvider;
use OCA\FullNextSearch\Model\SearchResult;
use OCA\Files_FullNextSearch\Service\FilesService;
use OCA\Files_FullNextSearch\Service\MiscService;

class FilesProvider implements INextSearchProvider {


	/** @var FilesService */
	private $filesService;

	/** @var MiscService */
	private $miscService;

	/** @var FilesDocument[] */
	private $files = [];

	/**
	 * return unique id of the provider
	 */
	public function getId() {
		return 'files';
	}


	/**
	 * return name of the provider
	 */
	public function getName() {
		return 'Files';
	}


	/**
	 * called when loading all providers.
	 *
	 * Loading some containers.
	 */
	public function loadProvider() {
		$app = new Application();

		$container = $app->getContainer();
		$this->filesService = $container->query(FilesService::class);
		$this->miscService = $container->query(MiscService::class);
	}


	/**
	 * Called on the creation of a new Index.
	 *
	 */
	public function initializeIndex(INextSearchPlatform $platform, $userId) {
		$this->files = $this->filesService->getFilesFromUser($userId);

		if ($platform->getId() === 'elastic_search') {
			//$platform->addMapping();
		}
	}


	/**
	 * {@inheritdoc}
	 */
	public function generateDocuments($chunkSize) {

		if (sizeof($this->files) === 0) {
			throw new NoResultException();
		}

		$toIndex = array_splice($this->files, 0, $chunkSize);
		$result = $this->filesService->generateDocuments($toIndex);

		return $result;
	}



	/**
	 * Called when index is over.
	 */
	public function finalizeIndex() {
		$this->files = [];
	}


	/**
	 * not used yet
	 */
	public function unloadProvider() {
	}



	/**
	 */
	public function improveSearchResult(SearchResult $searchResult) {

		foreach ($searchResult->getDocuments() as $document) {
			$this->filesService->setDocumentInfo($document);
			$this->filesService->setDocumentTitle($document);
			$this->filesService->setDocumentLink($document);
		}
	}


}