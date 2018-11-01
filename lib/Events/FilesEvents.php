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


namespace OCA\Files_FullTextSearch\Events;


use daita\MySmallPhpTools\Traits\TArrayTools;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Service\FilesService;
use OCA\Files_FullTextSearch\Service\MiscService;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\FullTextSearch\Model\IIndex;


/**
 * Class FilesEvents
 *
 * @package OCA\Files_FullTextSearch\Events
 */
class FilesEvents {


	use TArrayTools;


	/** @var string */
	private $userId;

	/** @var IFullTextSearchManager */
	private $fullTextSearchManager;

	/** @var FilesService */
	private $filesService;

	/** @var MiscService */
	private $miscService;


	/**
	 * FilesEvents constructor.
	 *
	 * @param string $userId
	 * @param IFullTextSearchManager $fullTextSearchManager
	 * @param FilesService $filesService
	 * @param MiscService $miscService
	 */
	public function __construct(
		$userId, IFullTextSearchManager $fullTextSearchManager, FilesService $filesService,
		MiscService $miscService
	) {
		$this->userId = $userId;
		$this->fullTextSearchManager = $fullTextSearchManager;
		$this->filesService = $filesService;
		$this->miscService = $miscService;
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function onNewFile(array $params) {
		$path = $this->get('path', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		$this->fullTextSearchManager->createIndex(
			'files', (string)$file->getId(), $this->userId, IIndex::INDEX_FULL
		);
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function onFileUpdate(array $params) {
		$path = $this->get('path', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		$this->fullTextSearchManager->updateIndexStatus(
			'files', (string)$file->getId(), IIndex::INDEX_FULL
		);
	}


	/**
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	public function onFileRename(array $params) {
		$target = $this->get('newpath', $params, '');
		if ($target === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $target);
		$this->fullTextSearchManager->updateIndexStatus(
			'files', (string)$file->getId(), IIndex::INDEX_META
		);
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function onFileTrash(array $params) {
		// check if trashbin does not exist. -> onFileDelete
		// we do not index trashbin

		$path = $this->get('path', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		$this->fullTextSearchManager->updateIndexStatus(
			'files', (string)$file->getId(), IIndex::INDEX_REMOVE, true
		);
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function onFileRestore(array $params) {
		$path = $this->get('filePath', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		$this->fullTextSearchManager->updateIndexStatus(
			'files', (string)$file->getId(), IIndex::INDEX_FULL
		);
	}


	/**
	 * @param array $params
	 */
	public function onFileDelete(array $params) {
//		$path = $this->get('path', $params, '');
//		if ($path === '') {
//			return;
//		}

//		$file = $this->filesService->getFileFromPath($this->userId, $path);
//		$this->fullTextSearchManager->updateIndexStatus('files', (string) $file->getId(), Index::INDEX_REMOVE);
	}


	/**
	 * @param array $params
	 */
	public function onFileShare(array $params) {
		$fileId = $this->get('itemSource', $params, '');
		if ($fileId === '') {
			return;
		}

		$this->fullTextSearchManager->updateIndexStatus(
			'files', $fileId, FilesDocument::STATUS_FILE_ACCESS
		);
	}


	/**
	 * @param array $params
	 */
	public function onFileUnshare(array $params) {
		$fileId = $this->get('itemSource', $params, '');
		if ($fileId === '') {
			return;
		}

		$this->fullTextSearchManager->updateIndexStatus(
			'files', $fileId, FilesDocument::STATUS_FILE_ACCESS
		);
	}


	public function onNewScannedFile2(array $params) {
	}


	public function onNewScannedFile(array $params) {
	}
}




