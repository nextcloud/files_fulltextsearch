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

namespace OCA\Files_FullTextSearch\Model;

class FileShares implements \JsonSerializable {

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
	public function getUsers() {
		return $this->users;
	}

	/**
	 * @param array $users
	 *
	 * @return $this
	 */
	public function setUsers($users) {
		$this->users = $users;

		return $this;
	}

	/**
	 * @param string $user
	 *
	 * @return $this
	 */
	public function addUser($user) {
		if (!in_array($user, $this->users)) {
			array_push($this->users, $user);
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function getGroups() {
		return $this->groups;
	}

	/**
	 * @param array $groups
	 *
	 * @return $this
	 */
	public function setGroups($groups) {
		$this->groups = $groups;

		return $this;
	}

	/**
	 * @param string $group
	 *
	 * @return $this
	 */
	public function addGroup($group) {
		if (!in_array($group, $this->groups)) {
			array_push($this->groups, $group);
		}

		return $this;
	}

	/**
	 * @return array
	 */
	public function getCircles() {
		return $this->circles;
	}

	/**
	 * @param array $circles
	 *
	 * @return $this
	 */
	public function setCircles($circles) {
		$this->circles = $circles;

		return $this;
	}

	/**
	 * @param string $circle
	 *
	 * @return $this
	 */
	public function addCircle($circle) {
		if (!in_array($circle, $this->circles)) {
			array_push($this->circles, $circle);
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function getLinks() {
		return $this->links;
	}

	/**
	 * @param array $links
	 *
	 * @return $this
	 */
	public function setLinks($links) {
		$this->links = $links;

		return $this;
	}

	/**
	 * @param string $link
	 *
	 * @return FileShares
	 */
	public function addLink($link) {
		if (!in_array($link, $this->links)) {
			array_push($this->links, $link);
		}

		return $this;
	}


	public function jsonSerialize() {
		return [
			'users'   => $this->getUsers(),
			'groups'  => $this->getGroups(),
			'circles' => $this->getCircles(),
			'links'   => $this->getLinks()
		];
	}

}