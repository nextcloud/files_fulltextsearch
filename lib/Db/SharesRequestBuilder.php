<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Db;

class SharesRequestBuilder extends CoreRequestBuilder {

	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return CoreQueryBuilder
	 */
	protected function getSharesSelectSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->select('s.*')
		   ->from(self::TABLE_SHARES, 's');

		$qb->setDefaultSelectAlias('s');

		return $qb;
	}
}
