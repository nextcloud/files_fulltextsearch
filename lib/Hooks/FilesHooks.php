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


namespace OCA\Files_FullTextSearch\Hooks;


use OCA\Files_FullTextSearch\AppInfo\Application;
use OCA\Files_FullTextSearch\Events\FilesEvents;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;


/**
 * Class FilesHooks
 *
 * @package OCA\Files_FullTextSearch\Hooks
 */
class FilesHooks {


	/**
	 * retrieve the FilesEvents' Controller
	 *
	 * @return FilesEvents
	 * @throws QueryException
	 */
	protected static function getController(): FilesEvents {
		$app = new Application();

		return $app->getContainer()
				   ->query(FilesEvents::class);
	}


	/**
	 * hook events: file is created
	 *
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public static function onNewFile(array $params) {
		self::getController()
			->onNewFile($params);
	}


	/**
	 * hook events: file is updated
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public static function onFileUpdate(array $params) {
		self::getController()
			->onFileUpdate($params);
	}


	/**
	 * hook events: file is renamed
	 *
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @throws QueryException
	 * @throws InvalidPathException
	 */
	public static function onFileRename(array $params) {
		self::getController()
			->onFileRename($params);
	}


	/**
	 * hook event: file is sent to trashbin
	 *
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public static function onFileTrash(array $params) {
		self::getController()
			->onFileTrash($params);
	}


	/**
	 * hook event: file is deleted
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onFileDelete(array $params) {
		self::getController()
			->onFileDelete($params);
	}


	/**
	 * hook event: file is restored
	 *
	 * @param array $params
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public static function onFileRestore(array $params) {
		self::getController()
			->onFileRestore($params);
	}


	/**
	 * hook event: file is shared
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onFileShare(array $params) {
		self::getController()
			->onFileShare($params);
	}


	/**
	 * hook event: file is unshared
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onFileUnshare(array $params) {
		self::getController()
			->onFileUnshare($params);
	}


	/**
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onNewRemoteFile2(array $params) {
		self::getController()
			->onNewScannedFile2($params);
	}


	/**
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onNewRemoteFile(array $params) {
		self::getController()
			->onNewScannedFile($params);
	}


	/**
	 * @param array $params
	 */
	public static function onRemoteFileUpdate(array $params) {
		\OC::$server->getLogger()
					->log(2, 'onRemoteFileUpdate ' . json_encode($params));
	}

	/**
	 * @param array $params
	 */
	public static function onRemoteFileRename(array $params) {
		\OC::$server->getLogger()
					->log(2, 'onRemoteFileRename ' . json_encode($params));
	}


	/**
	 * @param array $params
	 */
	public static function onRemoteFileDelete(array $params) {
		\OC::$server->getLogger()
					->log(2, 'onRemoteFileDelete ' . json_encode($params));
	}


}

