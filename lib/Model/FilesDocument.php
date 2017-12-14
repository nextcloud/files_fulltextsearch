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

namespace OCA\Files_FullNextSearch\Model;

use OCA\FullNextSearch\Model\IndexDocument;

class FilesDocument extends IndexDocument {

	const STATUS_FILE_ACCESS = 1024;

	/** @var string */
	private $ownerId;

	/** @var string */
	private $viewerId;

	/** @var string */
	private $type;

	/** @var string */
	private $mimetype;

	/** @var string */
	private $path;


	/**
	 * @param string $ownerId
	 *
	 * @return $this
	 */
	public function setOwnerId($ownerId) {
		$this->ownerId = $ownerId;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getOwnerId() {
		return $this->ownerId;
	}


	/**
	 * @param string $viewerId
	 *
	 * @return $this
	 */
	public function setViewerId($viewerId) {
		$this->viewerId = $viewerId;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getViewerId() {
		return $this->viewerId;
	}


	/**
	 * @param string $type
	 *
	 * @return $this
	 */
	public function setType($type) {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}


	/**
	 * @param string $type
	 *
	 * @return $this
	 */
	public function setMimetype($type) {
		$this->mimetype = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getMimetype() {
		return $this->mimetype;
	}


	/**
	 * @param string $path
	 *
	 * @return $this
	 */
	public function setPath($path) {
		$this->path = $path;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}


	public function __destruct() {
		parent::__destruct();

		unset($this->ownerId);
		unset($this->type);
		unset($this->mimetype);
		unset($this->path);
	}
}