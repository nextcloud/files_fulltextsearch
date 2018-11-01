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


namespace OCA\Files_FullTextSearch\Db;


use Doctrine\DBAL\Query\QueryBuilder;
use OCA\Files_FullTextSearch\Service\MiscService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;


/**
 * Class CoreRequestBuilder
 *
 * @package OCA\Files_FullTextSearch\Db
 */
class CoreRequestBuilder {


	const TABLE_SHARES = 'share';


	/** @var IDBConnection */
	protected $dbConnection;

	/** @var MiscService */
	protected $miscService;

	/** @var string */
	protected $defaultSelectAlias;


	/**
	 * CoreRequestBuilder constructor.
	 *
	 * @param IDBConnection $connection
	 * @param MiscService $miscService
	 */
	public function __construct(
		IDBConnection $connection, MiscService $miscService
	) {
		$this->dbConnection = $connection;
		$this->miscService = $miscService;
	}


	/**
	 * Limit the request to the Id
	 *
	 * @param IQueryBuilder $qb
	 * @param $fileSource
	 */
	protected function limitToFileSource(IQueryBuilder &$qb, int $fileSource) {
		$this->limitToDBFieldInt($qb, 'file_source', $fileSource);
	}


	/**
	 * @param IQueryBuilder $qb
	 * @param string $field
	 * @param string $value
	 */
	private function limitToDBField(IQueryBuilder &$qb, string $field, string $value) {
		$expr = $qb->expr();
		$pf = ($qb->getType() === QueryBuilder::SELECT) ? $this->defaultSelectAlias . '.' : '';
		$qb->andWhere($expr->eq($pf . $field, $qb->createNamedParameter($value)));
	}


	/**
	 * @param IQueryBuilder $qb
	 * @param string $field
	 * @param int $value
	 */
	private function limitToDBFieldInt(IQueryBuilder &$qb, string $field, int $value) {
		$expr = $qb->expr();
		$pf = ($qb->getType() === QueryBuilder::SELECT) ? $this->defaultSelectAlias . '.' : '';
		$qb->andWhere($expr->eq($pf . $field, $qb->createNamedParameter($value)));
	}


}

