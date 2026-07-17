<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Model;

use OCP\Files_FullTextSearch\Model\AFilesDocument;
use OCP\FullTextSearch\Model\IIndexDocument;

/**
 * Class FilesDocument
 *
 * @package OCA\Files_FullTextSearch\Model
 */
class FilesDocument extends AFilesDocument {

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

	public function setOwnerId(string $ownerId): FilesDocument {
		$this->ownerId = $ownerId;

		return $this;
	}

	public function getOwnerId(): string {
		return $this->ownerId;
	}

	public function setViewerId(string $viewerId): FilesDocument {
		$this->viewerId = $viewerId;

		return $this;
	}

	public function getViewerId(): string {
		return $this->viewerId;
	}

	public function setType(string $type): FilesDocument {
		$this->type = $type;

		return $this;
	}

	public function getType(): string {
		return $this->type;
	}

	public function setMimetype(string $type): FilesDocument {
		$this->mimetype = $type;

		return $this;
	}

	public function getMimetype(): string {
		return $this->mimetype;
	}

	public function setPath(string $path): FilesDocument {
		$this->path = $path;

		return $this;
	}

	public function getPath(): string {
		return $this->path;
	}

	public static function fromIndexDocument(IIndexDocument $indexDocument): FilesDocument {
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
