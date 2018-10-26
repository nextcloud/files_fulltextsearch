<?php

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
use OCA\FullTextSearch\Api\v1\FullTextSearch;
use OCP\FullTextSearch\Model\IIndex;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

class FilesEvents {


	use TArrayTools;


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
	 * @param array $params
	 *
	 * @throws QueryException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function onNewFile(array $params) {
		$path = $this->get('path', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		FullTextSearch::createIndex('files', $file->getId(), $this->userId, IIndex::INDEX_FULL);
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public function onFileUpdate(array $params) {
		$path = $this->get('path', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		FullTextSearch::updateIndexStatus('files', $file->getId(), IIndex::INDEX_FULL);
	}


	/**
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @throws QueryException
	 * @throws InvalidPathException
	 */
	public function onFileRename(array $params) {
		$target = $this->get('newpath', $params, '');
		if ($target === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $target);
		FullTextSearch::updateIndexStatus('files', $file->getId(), IIndex::INDEX_META);
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public function onFileTrash(array $params) {
		// check if trashbin does not exist. -> onFileDelete
		// we do not index trashbin

		$path = $this->get('path', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		FullTextSearch::updateIndexStatus('files', $file->getId(), IIndex::INDEX_REMOVE, true);

		//$this->miscService->log('> ON FILE TRASH ' . json_encode($path));
	}


	/**
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public function onFileRestore(array $params) {
		$path = $this->get('filePath', $params, '');
		if ($path === '') {
			return;
		}

		$file = $this->filesService->getFileFromPath($this->userId, $path);
		FullTextSearch::updateIndexStatus('files', $file->getId(), IIndex::INDEX_FULL);
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
//		FullTextSearch::updateIndexStatus('files', $file->getId(), Index::INDEX_REMOVE);
	}


	/**
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public function onFileShare(array $params) {
		$fileId = $this->get('itemSource', $params, '');
		if ($fileId === '') {
			return;
		}

		FullTextSearch::updateIndexStatus('files', $fileId, FilesDocument::STATUS_FILE_ACCESS);

	}


	/**
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public function onFileUnshare(array $params) {
		$fileId = $this->get('itemSource', $params, '');
		if ($fileId === '') {
			return;
		}

		FullTextSearch::updateIndexStatus('files', $fileId, FilesDocument::STATUS_FILE_ACCESS);
	}


	public function onNewScannedFile2($params) {


	}


	public function onNewScannedFile($params) {
		$this->miscService->log('___ !!! ' . json_encode($params));

		if (!array_key_exists('parent', $params)) {
			return;
		}

		$rootFolder = \OC::$server->getRootFolder();
		$nodes = $rootFolder->getById(7);
		$this->miscService->log('___ !!! ' . json_encode($nodes));
		$node = $nodes[0];

		$this->miscService->log(
			'___ >>>> ' . $node->getOwner()
							   ->getUID()
		);
//$this->miscService->log('___ >>>> ' . $node);


		// si parent n'existe pas, on oublie
		// si parent exist, on recupere le userId
		// avec le userId et le path, on recuperer l'Id
		// on genere l'entree dans la table d'index

		// verifier ce qu'il se passe dans du SMB
		// verifier qu'on ignore la mise a jour des index si il n;y a pas eu un index integral (du cote de la cron ?)ftf

//		$this->filesService->getFileFromId(7);
		$this->miscService->log(
			'______USERID: ' . $this->userId . '    ______PARAMS: ' . json_encode(
				$params, JSON_PRETTY_PRINT
			)
		);
	}
}




