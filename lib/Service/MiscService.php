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

namespace OCA\Files_FullTextSearch\Service;

use OCA\Files_FullTextSearch\AppInfo\Application;
use OCP\ILogger;

class MiscService {

	/** @var ILogger */
	private $logger;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	public function log($message, $level = 2) {
		$data = array(
			'app'   => Application::APP_NAME,
			'level' => $level
		);

		$this->logger->log($level, $message, $data);
	}

	/**
	 * @param $arr
	 * @param $k
	 *
	 * @param string $default
	 *
	 * @return array|string|integer
	 */
	public static function get($arr, $k, $default = '') {
		if (!key_exists($k, $arr)) {
			return $default;
		}

		return $arr[$k];
	}


	/**
	 * @param string $path
	 * @param bool $trim
	 *
	 * @return string
	 */
	public static function endSlash($path, $trim = false) {
		if (substr($path, -1) !== '/') {
			$path .= '/';
		}

		if ($trim) {
			$path = trim($path);
		}

		return $path;
	}


	/**
	 * @param string $path
	 * @param bool $trim
	 *
	 * @return string
	 */
	public static function noEndSlash($path, $trim = false) {
		if (substr($path, -1) === '/') {
			$path = substr($path, 0, -1);
		}

		if ($trim) {
			$path = trim($path);
		}

		return $path;
	}


	/**
	 * @param string $path
	 * @param bool $trim
	 *
	 * @return string
	 */
	public static function noBeginSlash($path, $trim = false) {
		if (substr($path, 0, 1) === '/') {
			$path = substr($path, 1);
		}

		if ($trim) {
			$path = trim($path);
		}

		return $path;
	}


	public static function secureUsername($username) {
		return str_replace('.', '\.', $username);
	}
}

