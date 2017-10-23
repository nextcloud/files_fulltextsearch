<?php

/**
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright Maxence Lange 2016
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

namespace OCA\Files_FullNextSearch\Events;

use OCA\Files_FullNextSearch\Model\FilesDocument;
use OCA\Files_FullNextSearch\Service\FilesService;
use OCA\Files_FullNextSearch\Service\MiscService;
use OCA\FullNextSearch\Api\v1\NextSearch;
use OCA\FullNextSearch\Model\Index;

class FilesEvents {


	/** @var string */
	private $userId;

	/** @var FilesService */
	private $filesService;

	/** @var MiscService */
	private $miscService;

	/**
	 * FilesEvents constructor.
	 *
	 * @param string $userId
	 * @param FilesService $filesService
	 * @param MiscService $miscService
	 */
	public function __construct($userId, FilesService $filesService, MiscService $miscService) {

		$this->userId = $userId;
		$this->filesService = $filesService;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $path
	 */
	public function onNewFile($path) {

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		NextSearch::updateIndexStatus('files', $file->getId(), Index::STATUS_INDEX_THIS);
	}


	/**
	 * @param string $path
	 */
	public function onFileUpdate($path) {
		$file = $this->filesService->getFileFromPath($this->userId, $path);
		NextSearch::updateIndexStatus('files', $file->getId(), Index::STATUS_INDEX_THIS);
	}


	/**
	 * @param string $target
	 */
	public function onFileRename($target) {
		$file = $this->filesService->getFileFromPath($this->userId, $target);
		NextSearch::updateIndexStatus('files', $file->getId(), FilesDocument::STATUS_FILE_RENAME);
	}


	/**
	 * @param string $path
	 */
	public function onFileTrash($path) {
		// check if trashbin does not exist. -> onFileDelete
		// we do not index trashbin

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		NextSearch::updateIndexStatus('files', $file->getId(), Index::STATUS_REMOVE_DOCUMENT);


		//$this->miscService->log('> ON FILE TRASH ' . json_encode($path));
	}


	/**
	 * @param string $path
	 */
	public function onFileRestore($path) {
		$file = $this->filesService->getFileFromPath($this->userId, $path);
		NextSearch::updateIndexStatus('files', $file->getId(), Index::STATUS_INDEX_THIS);
	}


	/**
	 * @param string $path
	 */
	public function onFileDelete($path) {
//		$file = $this->filesService->getFileFromPath($this->userId, $path);
//		NextSearch::updateIndexStatus('files', $file->getId(), Index::STATUS_REMOVE_DOCUMENT);
	}


	/**
	 * @param string $fileId
	 */
	public function onFileShare($fileId) {
		NextSearch::updateIndexStatus('files', $fileId, FilesDocument::STATUS_FILE_SHARES);

	}


	/**
	 * @param string $fileId
	 */
	public function onFileUnshare($fileId) {
		NextSearch::updateIndexStatus('files', $fileId, FilesDocument::STATUS_FILE_SHARES);
	}
}




