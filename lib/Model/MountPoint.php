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


/**
 * Class MountPoint
 *
 * @package OCA\Files_FullTextSearch\Model
 */
class MountPoint implements \JsonSerializable {


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
	public function setId(int $id): MountPoint {
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
	public function setPath(string $path): MountPoint {
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
	public function setGlobal(bool $global): MountPoint {
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
	public function setGroups(array $groups): MountPoint {
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


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id'     => $this->getId(),
			'path'   => $this->getPath(),
			'global' => $this->isGlobal(),
			'groups' => $this->getGroups(),
			'users'  => $this->getUsers()
		];
	}

}

