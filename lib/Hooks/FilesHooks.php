<?php

namespace OCA\Files_FullNextSearch\Hooks;


use OCA\Files_FullNextSearch\AppInfo\Application;
use OCA\Files_FullNextSearch\Events\FilesEvents;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

/**
 * init Files_FullNextSearch' Events
 */
class FilesHooks {


	/**
	 * retreive the FilesEvents' Controller
	 *
	 * @return FilesEvents
	 * @throws QueryException
	 */
	static protected function getController() {
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
	public static function onNewFile($params) {
		self::getController()
			->onNewFile($params['path']);
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
	public static function onFileUpdate($params) {
		self::getController()
			->onFileUpdate($params['path']);
	}


	/**
	 * hook events: file is renamed
	 *
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public static function onFileRename($params) {
		self::getController()
			->onFileRename($params['newpath']);
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
	public static function onFileTrash($params) {
		self::getController()
			->onFileTrash($params['path']);
	}


	/**
	 * hook event: file is deleted
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onFileDelete($params) {
		self::getController()
			->onFileDelete($params['path']);
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
	public static function onFileRestore($params) {
		self::getController()
			->onFileRestore($params['filePath']);
	}

	/**
	 * hook event: file is shared
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onFileShare($params) {
		self::getController()
			->onFileShare($params['itemSource']);
	}

	/**
	 * hook event: file is unshared
	 *
	 * @param array $params
	 *
	 * @throws QueryException
	 */
	public static function onFileUnshare($params) {
//		if (key_exists('itemSource', $params)) {
		self::getController()
			->onFileUnshare($params['itemSource']);
//		}
	}
}

