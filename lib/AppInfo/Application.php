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


namespace OCA\Files_FullTextSearch\AppInfo;


use OCA\Files_FullTextSearch\Hooks\FilesHooks;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;


/**
 * Class Application
 *
 * @package OCA\Files_FullTextSearch\AppInfo
 */
class Application extends App {

	const APP_NAME = 'files_fulltextsearch';


	/** @var IAppManager */
	private $appManager;

	/** @var IFullTextSearchManager */
	private $fullTextSearchManager;


	/** @var IUser */
	private $user;


	/**
	 * Application constructor.
	 *
	 * @param array $params
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_NAME, $params);

		$c = $this->getContainer();

		try {
			$this->appManager = $c->query(IAppManager::class);
			$this->fullTextSearchManager = $c->query(IFullTextSearchManager::class);
		} catch (QueryException $e) {
		}

		$this->registerHooks();
	}


	/**
	 * Register Hooks
	 */
	public function registerHooks() {
		Util::connectHook('OC_Filesystem', 'post_create', FilesHooks::class, 'onNewFile');
		Util::connectHook('OC_Filesystem', 'post_update', FilesHooks::class, 'onFileUpdate');
		Util::connectHook('OC_Filesystem', 'post_rename', FilesHooks::class, 'onFileRename');
		Util::connectHook('OC_Filesystem', 'delete', FilesHooks::class, 'onFileTrash');
		Util::connectHook(
			'\OCA\Files_Trashbin\Trashbin', 'post_restore', FilesHooks::class, 'onFileRestore'
		);
		Util::connectHook('\OCP\Trashbin', 'preDelete', FilesHooks::class, 'onFileDelete');
		Util::connectHook('OCP\Share', 'post_shared', FilesHooks::class, 'onFileShare');
		Util::connectHook('OCP\Share', 'post_unshare', FilesHooks::class, 'onFileUnshare');

//
//		Util::connectHook(
//			'\OC\Files\Cache\Scanner', 'post_scan_file', FilesHooks::class, 'onNewRemoteFile2'
//		);
//
//		Util::connectHook(
//			'Scanner', 'addToCache', FilesHooks::class, 'onNewRemoteFile'
//		);
//		Util::connectHook(
//			'Scanner', 'updateCache', FilesHooks::class, 'onRemoteFileUpdate'
//		);
//		Util::connectHook(
//			'\OC\Files\Cache\Scanner', 'updateCache', FilesHooks::class, 'onRemoteFileRename'
//		);
//		Util::connectHook(
//			'\OC\Files\Cache\Scanner', 'removeFromCache', FilesHooks::class, 'onRemoteFileDelete'
//		);
	}


	/**
	 * @throws QueryException
	 */
	public function registerFilesSearch() {
		$container = $this->getContainer();

		/** @var IUserSession $userSession */
		$userSession = $container->query(IUserSession::class);

		if (!$userSession->isLoggedIn()) {
			return;
		}

		$this->user = $userSession->getUser();

		\OC::$server->getEventDispatcher()
					->addListener(
						'OCA\Files::loadAdditionalScripts', function() {

						if ($this->appManager->isEnabledForUser('fulltextsearch', $this->user)
							&& $this->fullTextSearchManager->isProviderIndexed(
								FilesProvider::FILES_PROVIDER_ID
							)) {
							Util::addStyle(self::APP_NAME, 'fulltextsearch');
							$this->fullTextSearchManager->addJavascriptAPI();
							Util::addScript(self::APP_NAME, 'files');
						}
					}
					);
	}

}

