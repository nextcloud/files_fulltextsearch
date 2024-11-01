<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Db;

use Exception;
use OCP\Files\Node;

class SharesRequest extends SharesRequestBuilder {


	/**
	 * @param Node $file
	 *
	 * @return array
	 */
	public function getFromFile(Node $file): array {
		$shares = [];
		try {
			$qb = $this->getSharesSelectSql();
			$qb->limitToFileSource($file->getId());

			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				$shares[] = $data;
			}
			$cursor->closeCursor();
		} catch (Exception $e) {
			/** issue while doing some DB request */
		}

		return $shares;
	}
}
