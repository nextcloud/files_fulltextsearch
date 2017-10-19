<?php

namespace OCA\Files_FullNextSearch\Hooks;


use OCA\Files_FullNextSearch\AppInfo\Application;
use OCA\Files_FullNextSearch\Events\FilesEvents;

/**
 * init Files_FullNextSearch' Events
 */
class FilesHooks {


	/**
	 * retreive the FilesEvents' Controller
	 *
	 * @return FilesEvents
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
	 *            The hook params
	 */
	public static function onNewFile($params) {
		self::getController()
			->onNewFile($params['path']);
	}


	/**
	 * hook events: file is updated
	 *
	 * @param array $params
	 */
	public static function onFileUpdate($params) {
		self::getController()
			->onFileUpdate($params['path']);
	}


	/**
	 * hook events: file is renamed
	 *
	 * @param array $params
	 */
	public static function onFileRename($params) {
		self::getController()
			->onFileRename($params['newpath']);
	}


	/**
	 * hook event: file is sent to trashbin
	 *
	 * @param array $params
	 */
	public static function onFileTrash($params) {
		self::getController()
			->onFileTrash($params['path']);
	}


	/**
	 * hook event: file is deleted
	 *
	 * @param array $params
	 */
	public static function onFileDelete($params) {
		self::getController()
			->onFileDelete($params);
	}

	/**
	 * hook event: file is restored
	 *
	 * @param array $params
	 */
	public static function onFileRestore($params) {
		self::getController()
			->onFileRestore($params);
	}

	/**
	 * hook event: file is shared
	 *
	 * @param array $params
	 */
	public static function fileShare($params) {
		self::getController()
			->onFileShare($params);
	}

	/**
	 * hook event: file is unshared
	 *
	 * @param array $params
	 */
	public static function fileUnshare($params) {
//		if (key_exists('itemSource', $params)) {
		self::getController()
			->onFileUnshare($params);
//		}
	}
}

