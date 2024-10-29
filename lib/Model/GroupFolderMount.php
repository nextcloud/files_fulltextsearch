<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Model;

use JsonSerializable;

/**
 * Class GroupFolderMount
 *
 * @package OCA\Files_FullTextSearch\Model
 */
class GroupFolderMount implements JsonSerializable {


	/** @var int */
	private $id;

	/** @var string */
	private $path;

	/** @var bool */
	private $global;

	/** @var array */
	private $groups;

	/** @var array */
	private $users;


	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @param int $id
	 *
	 * @return $this
	 */
	public function setId(int $id): GroupFolderMount {
		$this->id = $id;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @param string $path
	 *
	 * @return $this
	 */
	public function setPath(string $path): GroupFolderMount {
		$this->path = $path;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isGlobal(): bool {
		return $this->global;
	}

	/**
	 * @param bool $global
	 *
	 * @return $this
	 */
	public function setGlobal(bool $global): GroupFolderMount {
		$this->global = $global;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getGroups(): array {
		return $this->groups;
	}

	/**
	 * @param array $groups
	 *
	 * @return $this
	 */
	public function setGroups(array $groups): GroupFolderMount {
		$this->groups = $groups;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getUsers(): array {
		return $this->users;
	}

	/**
	 * @param array $users
	 *
	 * @return $this
	 */
	public function setUsers(array $users): GroupFolderMount {
		$this->users = $users;

		return $this;
	}


	/**
	 *
	 */
	public function __destruct() {
		unset($this->id);
		unset($this->path);
		unset($this->global);
		unset($this->groups);
		unset($this->users);
	}


	/**
	 * @return array
	 */
	public function jsonSerialize():array {
		return [
			'id' => $this->getId(),
			'path' => $this->getPath(),
			'global' => $this->isGlobal(),
			'groups' => $this->getGroups(),
			'users' => $this->getUsers()
		];
	}
}
