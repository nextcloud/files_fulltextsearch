<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Db;

use ArtificialOwl\MySmallPhpTools\Db\ExtendedQueryBuilder;

/**
 * Class CoreQueryBuilder
 *
 * @package OCA\Files_FullTextSearch\Db
 */
class CoreQueryBuilder extends ExtendedQueryBuilder {

	/**
	 * Limit the request to the Id
	 *
	 * @param $fileSource
	 */
	public function limitToFileSource(int $fileSource) {
		$this->limitToDBFieldInt('file_source', $fileSource);
	}
}
