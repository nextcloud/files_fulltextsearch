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

	private ?int $id = null;

	private ?string $path = null;

	private ?bool $global = null;

	private ?array $groups = null;

	private ?array $users = null;

	public function getId(): int {
		return $this->id;
	}

	public function setId(int $id): GroupFolderMount {
		$this->id = $id;

		return $this;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function setPath(string $path): GroupFolderMount {
		$this->path = $path;

		return $this;
	}

	public function isGlobal(): bool {
		return $this->global;
	}

	public function setGlobal(bool $global): GroupFolderMount {
		$this->global = $global;

		return $this;
	}

	public function getGroups(): array {
		return $this->groups;
	}

	public function setGroups(array $groups): GroupFolderMount {
		$this->groups = $groups;

		return $this;
	}

	public function getUsers(): array {
		return $this->users;
	}

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
