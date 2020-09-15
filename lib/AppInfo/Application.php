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


use Closure;
use OCA\Files_FullTextSearch\Hooks\FilesHooks;
use OCA\FullTextSearch\Service\IndexService;
use OCA\FullTextSearch\Service\ProviderService;
use OCA\FullTextSearch\Service\SearchService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IServerContainer;
use OCP\Util;
use Throwable;


/**
 * Class Application
 *
 * @package OCA\Files_FullTextSearch\AppInfo
 */
class Application extends App implements IBootstrap {


	const APP_NAME = 'files_fulltextsearch';


	/**
	 * Application constructor.
	 *
	 * @param array $params
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_NAME, $params);
	}


	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		// TODO: check that files' event are migrated to the last version of event dispatcher
		// $context->registerEventListener();
	}


	/**
	 * @param IBootContext $context
	 *
	 * @throws Throwable
	 */
	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerHooks']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCommentsHooks']));
	}


	/**
	 * Register Hooks
	 *
	 * @param IServerContainer $container
	 */
	public function registerHooks(IServerContainer $container) {
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
	}


	public function registerCommentsHooks(IServerContainer $container) {
		// TODO: needed ?
//		OC::$server->getCommentsManager()
//				   ->registerEventHandler(
//					   function() {
//						   return $this->getContainer()
//									   ->query(FilesCommentsEvents::class);
//					   }
//				   );
	}

}

