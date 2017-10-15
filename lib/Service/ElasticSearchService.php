<?php
/**
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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
 *
 */

namespace OCA\Files_FullNextSearch\Service;


use OCA\FullNextSearch\INextSearchPlatform;
use OCA\FullNextSearch\Model\IndexDocument;

class ElasticSearchService {

	/** @var MiscService */
	private $miscService;


	/**
	 * ProviderService constructor.
	 *
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	function __construct(MiscService $miscService) {
		$this->miscService = $miscService;
	}


	/**
	 * @param INextSearchPlatform $platform
	 */
	public function onInitializingIndex(INextSearchPlatform $platform) {
		if ($platform->getId() !== 'elastic_search') {
			return;
		}
	}


	/**
	 * @param INextSearchPlatform $platform
	 */
	public function onRemovingIndex(INextSearchPlatform $platform) {
		if ($platform->getId() !== 'elastic_search') {
			return;
		}
	}


	/**
	 * @param INextSearchPlatform $platform
	 * @param array $arr
	 */
	public function onIndexingDocument(INextSearchPlatform $platform, &$arr) {
		if ($platform->getId() !== 'elastic_search') {
			return;
		}
	}


	/**
	 * @param INextSearchPlatform $platform
	 * @param array $arr
	 */
	public function onSearchingQuery(INextSearchPlatform $platform, &$arr) {
		if ($platform->getId() !== 'elastic_search') {
			return;
		}

		array_push(
			$arr['params']['body']['query']['bool']['must']['bool']['should'],
			[
				'match' => [
					'share_names.' . $arr['requester'] => $arr['query']
				]
			]
		);
	}


}
