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

use OCA\Files_FullNextSearch\Service\MiscService;

class FilesEvents {


	/** @var string */
	private $userId;

	/** @var MiscService */
	private $miscService;

	public function __construct($userId, MiscService $miscService) {
		$this->userId = $userId;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $path
	 */
	public function onNewFile($path) {

		$this->miscService->log('> ON FILE CREATE ' . json_encode($path));

	}


	/**
	 * @param string $path
	 */
	public function onFileUpdate($path) {
		$this->miscService->log('> ON FILE UPDATE' . json_encode($path));

	}


	/**
	 * @param string $target
	 */
	public function onFileRename($target) {

		$this->miscService->log('> ON FILE RENAME ' . json_encode($target));

	}


	/**
	 * @param string $path
	 */
	public function onFileTrash($path) {
		// check if trashbin does not exist. -> onFileDelete
		$this->miscService->log('> ON FILE TRASH ' . json_encode($path));
	}


	/**
	 * @param string $path
	 */
	public function onFileDelete($path) {
		$this->miscService->log('> ON FILE DELETE' . json_encode($path));
	}


	/**
	 * @param string $path
	 */
	public function onFileRestore($path) {
		$this->miscService->log('> ON FILE RESTORE ' . json_encode($path));

	}

	/**
	 * @param string $fileId
	 */
	public function onFileShare($fileId) {
		$this->miscService->log('> ON FILE SHARE' . json_encode($fileId));
	}

	/**
	 * @param string $fileId
	 */
	public function onFileUnshare($fileId) {
		$this->miscService->log('> ON FILE UNSHARE' . json_encode($fileId));
	}
}




