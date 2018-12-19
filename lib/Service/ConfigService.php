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


namespace OCA\Files_FullTextSearch\Service;


use OCA\Files_FullTextSearch\AppInfo\Application;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\FullTextSearch\Model\IIndex;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use OCP\Util;


/**
 * Class ConfigService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class ConfigService {

	const FILES_LOCAL = 'files_local';
	const FILES_EXTERNAL = 'files_external';
	const FILES_GROUP_FOLDERS = 'files_group_folders';
	const FILES_ENCRYPTED = 'files_encrypted';
	const FILES_FEDERATED = 'files_federated';
	const FILES_SIZE = 'files_size';
	const FILES_OFFICE = 'files_office';
	const FILES_PDF = 'files_pdf';
	const FILES_ZIP = 'files_zip';
	const FILES_IMAGE = 'files_image';
	const FILES_AUDIO = 'files_audio';

	public $defaults = [
		self::FILES_LOCAL         => '1',
		self::FILES_EXTERNAL      => '0',
		self::FILES_GROUP_FOLDERS => '0',
		self::FILES_ENCRYPTED     => '0',
		self::FILES_FEDERATED     => '0',
		self::FILES_SIZE          => '20',
		self::FILES_PDF           => '1',
		self::FILES_OFFICE        => '1',
		self::FILES_IMAGE         => '0',
		self::FILES_AUDIO         => '0'
	];


	/** @var IConfig */
	private $config;

	/** @var string */
	private $userId;

	/** @var MiscService */
	private $miscService;


	/**
	 * ConfigService constructor.
	 *
	 * @param IConfig $config
	 * @param string $userId
	 * @param MiscService $miscService
	 */
	public function __construct(IConfig $config, $userId, MiscService $miscService) {
		$this->config = $config;
		$this->userId = $userId;
		$this->miscService = $miscService;
	}


	/**
	 * @return array
	 */
	public function getConfig(): array {
		$keys = array_keys($this->defaults);
		$data = [];

		foreach ($keys as $k) {
			$data[$k] = $this->getAppValue($k);
		}

		return $data;
	}


	/**
	 * @param array $save
	 */
	public function setConfig(array $save) {
		$keys = array_keys($this->defaults);

		foreach ($keys as $k) {
			if (array_key_exists($k, $save)) {
				$this->setAppValue($k, $save[$k]);
			}
		}
	}


	/**
	 * Get a value by key
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getAppValue(string $key): string {
		$defaultValue = null;
		if (array_key_exists($key, $this->defaults)) {
			$defaultValue = $this->defaults[$key];
		}

		return (string)$this->config->getAppValue(Application::APP_NAME, $key, $defaultValue);
	}

	/**
	 * Set a value by key
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function setAppValue(string $key, string $value) {
		$this->config->setAppValue(Application::APP_NAME, $key, $value);
	}

	/**
	 * remove a key
	 *
	 * @param string $key
	 */
	public function deleteAppValue(string $key) {
		$this->config->deleteAppValue(Application::APP_NAME, $key);
	}


	/**
	 * return if option is enabled.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function optionIsSelected(string $key): bool {
		return ($this->getAppValue($key) === '1');
	}


	/**
	 * Get a user value by key
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getUserValue(string $key): string {
		$defaultValue = null;
		if (array_key_exists($key, $this->defaults)) {
			$defaultValue = $this->defaults[$key];
		}

		return $this->config->getUserValue(
			$this->userId, Application::APP_NAME, $key, $defaultValue
		);
	}


	/**
	 * Get a user value by key and user
	 *
	 * @param string $userId
	 * @param string $key
	 *
	 * @return string
	 */
	public function getValueForUser(string $userId, string $key): string {
		return $this->config->getUserValue($userId, Application::APP_NAME, $key);
	}

	/**
	 * Set a user value by key
	 *
	 * @param string $userId
	 * @param string $key
	 * @param string $value
	 *
	 * @throws PreConditionNotMetException
	 */
	public function setValueForUser(string $userId, string $key, string $value) {
		$this->config->setUserValue($userId, Application::APP_NAME, $key, $value);
	}


	/**
	 * @param string $key
	 *
	 * @param string $default
	 *
	 * @return string
	 */
	public function getSystemValue(string $key, string $default = ''): string {
		return $this->config->getSystemValue($key, $default);
	}


	/**
	 * @param FilesDocument $document
	 * @param string $option
	 */
	public function setDocumentIndexOption(FilesDocument $document, string $option) {
		$document->getIndex()
				 ->addOption('_' . $option, (string)$this->getAppValue($option));
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
			if (substr($k, 0, 1) === '_'
				&& $options[$k] !== $this->getAppValue(substr($k, 1))) {
				return false;
			}
		}

		return true;
	}

	/**
	 * return the cloud version.
	 *
	 * @return int
	 */
	public function getFullCloudVersion(): int {
		$ver = Util::getVersion();

		return ($ver[0] * 1000000) + ($ver[1] * 1000) + $ver[2];
	}


	/**
	 * @param $major
	 * @param $sub
	 * @param $minor
	 *
	 * @return bool
	 */
	public function isCloudVersionAtLeast($major, $sub, $minor): bool {
		if ($this->getFullCloudVersion() >= (($major * 1000000) + ($sub * 1000) + $minor)) {
			return true;
		}

		return false;
	}

}

