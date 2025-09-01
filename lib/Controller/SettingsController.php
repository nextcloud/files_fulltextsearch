<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Controller;

use Exception;
use OCA\Files_FullTextSearch\AppInfo\Application;
use OCA\Files_FullTextSearch\Service\ConfigService;
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
	public function __construct(
		IRequest $request,
		private ConfigService $configService,
	) {
		parent::__construct(Application::APP_ID, $request);
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
		if ($this->configService->checkConfig($data)) {
			$this->configService->setConfig($data);
		}

		return $this->getSettingsAdmin();
	}
}
