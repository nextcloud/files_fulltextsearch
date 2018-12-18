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


namespace OCA\Files_FullTextSearch\Model;


use OCP\Files_FullTextSearch\Model\AFilesDocument;
use OCP\FullTextSearch\Model\IndexDocument;


/**
 * Class FilesDocument
 *
 * @package OCA\Files_FullTextSearch\Model
 */
class FilesDocument extends AFilesDocument {


	const STATUS_FILE_ACCESS = 1024;


	/** @var string */
	private $ownerId = '';

	/** @var string */
	private $viewerId = '';

	/** @var string */
	private $type = '';

	/** @var string */
	private $mimetype = '';

	/** @var string */
	private $path = '';


	/**
	 * @param string $ownerId
	 *
	 * @return $this
	 */
	public function setOwnerId(string $ownerId): FilesDocument {
		$this->ownerId = $ownerId;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getOwnerId(): string {
		return $this->ownerId;
	}


	/**
	 * @param string $viewerId
	 *
	 * @return FilesDocument
	 */
	public function setViewerId(string $viewerId): FilesDocument {
		$this->viewerId = $viewerId;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getViewerId(): string {
		return $this->viewerId;
	}


	/**
	 * @param string $type
	 *
	 * @return FilesDocument
	 */
	public function setType(string $type): FilesDocument {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}


	/**
	 * @param string $type
	 *
	 * @return FilesDocument
	 */
	public function setMimetype(string $type): FilesDocument {
		$this->mimetype = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getMimetype(): string {
		return $this->mimetype;
	}


	/**
	 * @param string $path
	 *
	 * @return $this
	 */
	public function setPath(string $path): FilesDocument {
		$this->path = $path;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}


	/**
	 * @param IndexDocument $indexDocument
	 *
	 * @return FilesDocument
	 */
	public static function fromIndexDocument(IndexDocument $indexDocument): FilesDocument {
		$document = new FilesDocument($indexDocument->getProviderId(), $indexDocument->getId());

		foreach (get_object_vars($indexDocument) as $key => $name) {
			$document->$key = $name;
		}

		return $document;
	}


	/**
	 *
	 */
	public function __destruct() {
		parent::__destruct();

		unset($this->ownerId);
		unset($this->viewerId);
		unset($this->type);
		unset($this->mimetype);
		unset($this->path);
	}

}

