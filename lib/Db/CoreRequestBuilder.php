<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Db;

use OC;
use OC\SystemConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Class CoreRequestBuilder
 *
 * @package OCA\Files_FullTextSearch\Db
 */
class CoreRequestBuilder {
	public const TABLE_SHARES = 'share';

	protected string $defaultSelectAlias;

	public function __construct() {
	}

	/**
	 * @return CoreQueryBuilder
	 */
	public function getQueryBuilder(): CoreQueryBuilder {
		return new CoreQueryBuilder(
			OC::$server->get(IDBConnection::class),
			OC::$server->get(SystemConfig::class),
			OC::$server->get(LoggerInterface::class)
		);
	}
}
