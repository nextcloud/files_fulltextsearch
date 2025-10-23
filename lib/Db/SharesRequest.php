<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_FullTextSearch\Db;

use Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Node;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class SharesRequest {
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getFromFile(Node $file): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$shares = [];
		try {
			$qb->where($qb->expr()->eq('file_source', $qb->createNamedParameter($file->getId(), IQueryBuilder::PARAM_INT)));
			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				$shares[] = $data;
			}
			$cursor->closeCursor();
		} catch (Exception $e) {
			$this->logger->warning('could not get shares about file', ['exception' => $e]);
		}

		return $shares;
	}
}
