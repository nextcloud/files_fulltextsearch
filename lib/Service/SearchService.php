<?php
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


use OCA\FullTextSearch\Model\SearchRequest;

class SearchService {


	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * SearchService constructor.
	 *
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	public function __construct(ConfigService $configService, MiscService $miscService) {
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param SearchRequest $request
	 */
	public function improveSearchRequest(SearchRequest $request) {
		$this->searchQueryShareNames($request);
		$this->searchQueryFiltersExtension($request);
		$this->searchQueryFiltersSource($request);
		$this->searchQueryWithinDir($request);
	}


	private function searchQueryWithinDir(SearchRequest $request) {

		$currentDir = $request->getOption('files_within_dir');
		if ($currentDir === '') {
			return;
		}

		$username = MiscService::secureUsername($request->getAuthor());
		$currentDir = MiscService::noBeginSlash(MiscService::endSlash($currentDir));
		$request->addWildcardFilters(
			[
				['share_names.' . $username => $currentDir . '*'],
				['title' => $currentDir . '*']
			]
		);
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryShareNames(SearchRequest $request) {
		$query = [];
		$words = explode(' ', $request->getSearch());
		foreach ($words as $word) {
			$username = MiscService::secureUsername($request->getAuthor());
			array_push($query, ['share_names.' . $username => '*' . $word . '*']);
		}
		$request->addWildcardQueries($query);
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryFiltersExtension(SearchRequest $request) {
		$extension = $request->getOption('files_extension');
		if ($extension === '') {
			return;
		}

		$username = MiscService::secureUsername($request->getAuthor());
		$request->addRegexFilters(
			[
				['share_names.' . $username => '.*\.' . $extension],
				['title' => '.*\.' . $extension]
			]
		);
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryFiltersSource(SearchRequest $request) {

		$local = $request->getOption('files_local');
		$external = $request->getOption('files_external');
		$groupFolders = $request->getOption('files_group_folders');
		$federated = $request->getOption('files_federated');

		if (count(array_unique([$local, $external, $groupFolders])) === 1) {
			return;
		}

		$this->addTagToSearchRequest($request, 'files_local', $local);
		$this->addTagToSearchRequest($request, 'files_external', $external);
		$this->addTagToSearchRequest($request, 'files_group_folders', $groupFolders);
	}


	/**
	 * @param SearchRequest $request
	 * @param string $tag
	 * @param mixed $cond
	 */
	private function addTagToSearchRequest(SearchRequest $request, $tag, $cond) {
		if ($cond === 1 || $cond === '1') {
			$request->addTag($tag);
		}
	}


}