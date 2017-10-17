<?php
/**
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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
 *
 */

namespace OCA\Files_FullNextSearch\AppInfo;

use OCA\Files_FullNextSearch\Provider\FilesProvider;
use OCA\FullNextSearch\Api\v1\NextSearch;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\IUserSession;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Application extends App {

	const APP_NAME = 'files_fullnextsearch';

	/**
	 * @param array $params
	 */
	public function __construct(array $params = array()) {
		parent::__construct(self::APP_NAME, $params);
	}


	/**
	 *
	 */
	public function registerFilesSearch() {
		$container = $this->getContainer();

		/** @var IUserSession $userSession */
		$userSession = $container->query(IUserSession::class);

		if (!$userSession->isLoggedIn()) {
			return;
		}

		$user = $userSession->getUser();

		if ($container->query(IAppManager::class)
					  ->isEnabledForUser('fullnextsearch', $user->getUID())
			&& (NextSearch::isProviderIndexed(FilesProvider::FILES_PROVIDER_ID))) {

			$this->includeNextSearch();
		}
	}


	/**
	 *
	 */
	private function includeNextSearch() {
		\OC::$server->getEventDispatcher()
					->addListener(
						'OCA\Files::loadAdditionalScripts', function() {
						NextSearch::addJavascriptAPI();
						Util::addScript(Application::APP_NAME, 'files');
					}
					);
	}


	/**
	 *
	 */
	public function registerSettingsAdmin() {
		\OCP\App::registerAdmin(self::APP_NAME, 'lib/admin');
	}


}

