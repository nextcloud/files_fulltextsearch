<?php

/**
 * SPDX-FileCopyrightText: 2017-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Files_External\Service;

use OCA\Files_External\Lib\StorageConfig;

/**
 * Service class to manage external storage
 *
 * @psalm-import-type ExternalMountInfo from DBConfigService
 */
abstract class StoragesService {
	/**
	 * Get a storage with status
	 *
	 * @param int $id storage id
	 *
	 * @throws NotFoundException if the storage with the given id was not found
	 */
	public function getStorage(int $id): StorageConfig {
	}
}
