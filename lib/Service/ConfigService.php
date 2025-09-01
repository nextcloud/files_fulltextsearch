<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Service;

use OCA\Files_FullTextSearch\ConfigLexicon;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\AppFramework\Services\IAppConfig;
use OCP\FullTextSearch\Model\IIndex;

/**
 * Class ConfigService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class ConfigService {
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	public function getConfig(): array {
		return [
			ConfigLexicon::FILES_LOCAL => $this->appConfig->getAppValueBool(ConfigLexicon::FILES_LOCAL),
			ConfigLexicon::FILES_EXTERNAL => $this->appConfig->getAppValueInt(ConfigLexicon::FILES_EXTERNAL),
			ConfigLexicon::FILES_GROUP_FOLDERS => $this->appConfig->getAppValueBool(ConfigLexicon::FILES_GROUP_FOLDERS),
			ConfigLexicon::FILES_SIZE => $this->appConfig->getAppValueInt(ConfigLexicon::FILES_SIZE),
			ConfigLexicon::FILES_OFFICE => $this->appConfig->getAppValueBool(ConfigLexicon::FILES_OFFICE),
			ConfigLexicon::FILES_PDF => $this->appConfig->getAppValueBool(ConfigLexicon::FILES_PDF),
			ConfigLexicon::FILES_ZIP => $this->appConfig->getAppValueBool(ConfigLexicon::FILES_ZIP),
			ConfigLexicon::FILES_CHUNK_SIZE => $this->appConfig->getAppValueInt(ConfigLexicon::FILES_CHUNK_SIZE),
			ConfigLexicon::FILES_OPEN_RESULT_DIRECTLY => $this->appConfig->getAppValueBool(ConfigLexicon::FILES_OPEN_RESULT_DIRECTLY),
		];
	}

	public function setConfig(array $save): void {
		foreach (array_keys($save) as $k) {
			switch ($k) {
				case ConfigLexicon::FILES_EXTERNAL:
				case ConfigLexicon::FILES_SIZE:
				case ConfigLexicon::FILES_CHUNK_SIZE:
					$this->appConfig->setAppValueInt($k, $save[$k]);
					break;

				case ConfigLexicon::FILES_LOCAL:
				case ConfigLexicon::FILES_GROUP_FOLDERS:
				case ConfigLexicon::FILES_OFFICE:
				case ConfigLexicon::FILES_PDF:
				case ConfigLexicon::FILES_ZIP:
				case ConfigLexicon::FILES_OPEN_RESULT_DIRECTLY:
					$this->appConfig->setAppValueBool($k, $save[$k]);
					break;
			}
		}
	}

	public function setDocumentIndexOption(FilesDocument $document, string $option) {
		$document->getIndex()->addOption('_' . $option, $this->getCurrentIndexOptionStatus($option) ? '1' : '0');
	}

	/**
	 * @param IIndex $index
	 *
	 * @return bool
	 */
	public function compareIndexOptions(IIndex $index): bool {
		$options = $index->getOptions();

		$ak = array_keys($options);
		foreach ($ak as $k) {
			if (!str_starts_with($k, '_')) {
				continue;
			}

			$currentValue = $this->getCurrentIndexOptionStatus(substr($k, 1)) ? '1' : '0';
			if ($options[$k] !== $currentValue) {
				return false;
			}
		}

		return true;
	}

	public function getCurrentIndexOptionStatus(string $option): bool {
		if ($option === ConfigLexicon::FILES_EXTERNAL) {
			if ($this->appConfig->getAppValueInt(ConfigLexicon::FILES_EXTERNAL) === 1) {
				return true;
			}
		} elseif ($this->appConfig->getAppValueBool($option)) {
			return true;
		}

		return false;
	}

	public function checkConfig(array &$data): bool {
		// convert to bool
		foreach (
			[
				ConfigLexicon::FILES_LOCAL,
				ConfigLexicon::FILES_GROUP_FOLDERS,
				ConfigLexicon::FILES_OFFICE,
				ConfigLexicon::FILES_PDF,
				ConfigLexicon::FILES_ZIP,
				ConfigLexicon::FILES_OPEN_RESULT_DIRECTLY,
			] as $k
		) {
			if (is_string($data[$k] ?? false)) {
				$data[$k] = in_array($data[$k], ['1', 'yes', 'on', 'true'], true);
			}
		}

		foreach (
			[
				ConfigLexicon::FILES_SIZE,
				ConfigLexicon::FILES_EXTERNAL,
				ConfigLexicon::FILES_CHUNK_SIZE,
			] as $k
		) {
			if (is_string($data[$k] ?? false)) {
				$data[$k] = (int)$data[$k];
			}
		}

		return true;
	}
}
