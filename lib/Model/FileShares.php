<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Model;

use JsonSerializable;

/**
 * Class FileShares
 *
 * @package OCA\Files_FullTextSearch\Model
 */
class FileShares implements JsonSerializable {


	/** @var array */
	private $users = [];

	/** @var array */
	private $groups = [];

	/** @var array */
	private $circles = [];

	/** @var array */
	private $links = [];


	/**
	 * FileShares constructor.
	 *
	 * @param FileShares $currentShares
	 */
	public function __construct(?FileShares $currentShares = null) {
		if ($currentShares === null) {
			return;
		}

		$this->setUsers($currentShares->getUsers());
		$this->setGroups($currentShares->getGroups());
		$this->setCircles($currentShares->getCircles());
		$this->setLinks($currentShares->getLinks());
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
	public function setUsers(array $users): FileShares {
		$this->users = $users;

		return $this;
	}

	/**
	 * @param string $user
	 *
	 * @return $this
	 */
	public function addUser(string $user): FileShares {
		if (!in_array($user, $this->users)) {
			array_push($this->users, $user);
		}

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
	public function setGroups(array $groups): FileShares {
		$this->groups = $groups;

		return $this;
	}

	/**
	 * @param string $group
	 *
	 * @return $this
	 */
	public function addGroup(string $group): FileShares {
		if (!in_array($group, $this->groups)) {
			array_push($this->groups, $group);
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function getCircles(): array {
		return $this->circles;
	}

	/**
	 * @param array $circles
	 *
	 * @return $this
	 */
	public function setCircles(array $circles): FileShares {
		$this->circles = $circles;

		return $this;
	}

	/**
	 * @param string $circle
	 *
	 * @return $this
	 */
	public function addCircle(string $circle): FileShares {
		if (!in_array($circle, $this->circles)) {
			array_push($this->circles, $circle);
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function getLinks(): array {
		return $this->links;
	}

	/**
	 * @param array $links
	 *
	 * @return $this
	 */
	public function setLinks(array $links): FileShares {
		$this->links = $links;

		return $this;
	}

	/**
	 * @param string $link
	 *
	 * @return FileShares
	 */
	public function addLink(string $link): FileShares {
		if (!in_array($link, $this->links)) {
			array_push($this->links, $link);
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'users' => $this->getUsers(),
			'groups' => $this->getGroups(),
			'circles' => $this->getCircles(),
			'links' => $this->getLinks()
		];
	}
}
