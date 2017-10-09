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
	 * {@inheritdoc}
	 */
	public function getId() {
		return 'files';
	}


	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'Files';
	}


	/**
	 * {@inheritdoc}
	 */
	public function load() {
		$app = new Application();

		$container = $app->getContainer();
		$this->filesService = $container->query(FilesService::class);
		$this->miscService = $container->query(MiscService::class);
	}

	/**
	 * {@inheritdoc}
	 */
	public function initUser($userId) {
		$this->files = $this->filesService->getFilesFromUser($userId);
	}


	/**
	 * {@inheritdoc}
	 */
	public function end() {
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
	 * {@inheritdoc}
	 */
	public function parseSearchResult(SearchResult $searchResult) {
		
		foreach ($searchResult->getDocuments() as $document) {
			$this->filesService->setDocumentInfo($document);
			$this->filesService->setDocumentTitle($document);
			$this->filesService->setDocumentLink($document);
		}
	}


	/**
	 * Called when user is not needed anymore.
	 */
	public function endUser() {
		$this->files = [];
	}


	/**
	 * {@inheritdoc}
	 */
	public function unload() {
	}


	/**
	 * this method is only call when using elastic search platform
	 *
	 * @param array $map
	 *
	 * @return array
	 */
	public function improveMappingForElasticSearch($map) {
		return $map;
	}
}