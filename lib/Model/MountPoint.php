<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Model;

use JsonSerializable;

/**
 * Class MountPoint
 *
 * @package OCA\Files_FullTextSearch\Model
 */
class MountPoint implements JsonSerializable {

	private ?int $id = null;

	private string $path = '';

	private bool $global = false;

	/** @var list<string> */
	private array $groups = [];

	/** @var list<string> */
	private array $users = [];

	public function getId(): int {
		return $this->id;
	}

	public function setId(int $id): MountPoint {
		$this->id = $id;

		return $this;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function setPath(string $path): MountPoint {
		$this->path = $path;

		return $this;
	}

	public function isGlobal(): bool {
		return $this->global;
	}

	public function setGlobal(bool $global): MountPoint {
		$this->global = $global;

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getGroups(): array {
		return $this->groups;
	}

	/**
	 * @param list<string> $groups
	 */
	public function setGroups(array $groups): MountPoint {
		$this->groups = $groups;

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getUsers(): array {
		return $this->users;
	}

	/**
	 * @param list<string> $users
	 */
	public function setUsers(array $users): MountPoint {
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

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'path' => $this->getPath(),
			'global' => $this->isGlobal(),
			'groups' => $this->getGroups(),
			'users' => $this->getUsers()
		];
	}
}
