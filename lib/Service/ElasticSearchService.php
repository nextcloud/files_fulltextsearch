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
use OCA\FullNextSearch\Model\SearchRequest;

class ElasticSearchService {

	/** @var MiscService */
	private $miscService;


	/**
	 * ElasticSearchService constructor.
	 *
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	public function __construct(MiscService $miscService) {
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
	 * @param SearchRequest $request
	 * @param array $arr
	 */
	public function onSearchingQuery(INextSearchPlatform $platform, SearchRequest $request, &$arr) {
		if ($platform->getId() !== 'elastic_search') {
			return;
		}

		$this->searchQueryShareNames($request, $arr);
		$this->searchQueryShareOptions($request, $arr);


		$this->miscService->log('>>>>>> ' . json_encode($arr));
	}


	/**
	 * @param SearchRequest $request
	 * @param array $arr
	 */
	private function searchQueryShareNames(SearchRequest $request, &$arr) {
		$query = [];
		$words = explode(' ', $request->getSearch());
		foreach ($words as $word) {
			array_push(
				$query, ['wildcard' => ['share_names.' . $request->getAuthor() => '*' . $word . '*']]
			);
		}

		array_push($arr['params']['body']['query']['bool']['must']['bool']['should'], $query);
	}


	/**
	 * @param SearchRequest $request
	 * @param array $arr
	 */
	private function searchQueryShareOptions(SearchRequest $request, &$arr) {
		$this->searchQueryShareOptionsExtension($request, $arr);
	}


	private function searchQueryShareOptionsExtension(SearchRequest $request, &$arr) {
		$extension = $request->getOption('files_extension');
		if ($extension === '') {
			return;
		}

		$query = [
			['wildcard' => ['share_names.' . $request->getAuthor() => '*' . $extension]],
			['wildcard' => ['title' => '*' . $extension]]
		];

		$arr['params']['body']['query']['bool']['filter'][]['bool']['should'] = $query;
	}


}
