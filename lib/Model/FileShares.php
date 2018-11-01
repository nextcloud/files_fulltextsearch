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
	public function __construct(FileShares $currentShares = null) {
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
			'users'   => $this->getUsers(),
			'groups'  => $this->getGroups(),
			'circles' => $this->getCircles(),
			'links'   => $this->getLinks()
		];
	}

}

