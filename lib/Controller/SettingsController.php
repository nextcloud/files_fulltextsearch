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


namespace OCA\Files_FullTextSearch\Controller;


use Exception;
use OCA\Files_FullTextSearch\AppInfo\Application;
use OCA\Files_FullTextSearch\Service\ConfigService;
use OCA\Files_FullTextSearch\Service\MiscService;
use OCA\Files_FullTextSearch\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;


/**
 * Class SettingsController
 *
 * @package OCA\Files_FullTextSearch\Controller
 */
class SettingsController extends Controller {


	/** @var IAppManager */
	private $appManager;

	/** @var ConfigService */
	private $configService;

	/** @var SettingsService */
	private $settingsService;

	/** @var MiscService */
	private $miscService;


	/**
	 * SettingsController constructor.
	 *
	 * @param IRequest $request
	 * @param IAppManager $appManager
	 * @param ConfigService $configService
	 * @param SettingsService $settingsService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IRequest $request, IAppManager $appManager, ConfigService $configService,
		SettingsService $settingsService,
		MiscService $miscService
	) {
		parent::__construct(Application::APP_NAME, $request);
		$this->appManager = $appManager;
		$this->configService = $configService;
		$this->settingsService = $settingsService;
		$this->miscService = $miscService;
	}


	/**
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getSettingsAdmin(): DataResponse {
		$data = $this->configService->getConfig();

		return new DataResponse($data, Http::STATUS_OK);
	}


	/**
	 * @param array $data
	 *
	 * @return DataResponse
	 * @throws Exception
	 */
	public function setSettingsAdmin(array $data): DataResponse {

		if ($this->settingsService->checkConfig($data)) {
			$this->configService->setConfig($data);
		}

		return $this->getSettingsAdmin();
	}

}

