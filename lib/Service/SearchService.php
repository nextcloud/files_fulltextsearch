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


use Exception;
use OC\Share\Constants;
use OC\Share\Share;
use OCA\Files_FullTextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCA\FullTextSearch\Exceptions\InterruptException;
use OCA\FullTextSearch\Exceptions\TickDoesNotExistException;
use OCA\FullTextSearch\Model\DocumentAccess;
use OCA\FullTextSearch\Model\Index;
use OCA\FullTextSearch\Model\IndexDocument;
use OCA\FullTextSearch\Model\Runner;
use OCA\FullTextSearch\Model\SearchRequest;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\Share\IManager;

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


	public function improveSearchRequest(SearchRequest $request) {

		$local = $request->getOption('files_local');
		$external = $request->getOption('files_external');
		$groupFolders = $request->getOption('group_folders');
		$federated = $request->getOption('files_federated');
		$withinDir = $request->getOption('files_withindir');

		// current dir ? files_withindir
		// filter on file extension ?

		$this->searchQueryShareNames($request);
		$this->searchQueryShareOptions($request);

		if (count(array_unique([$local, $external, $groupFolders])) === 1) {
			return;
		}

		if ($local === '1') {
			$request->addTag('local');
		}

		if ($external === '1') {
			$request->addTag('external');
		}

		if ($federated === '1') {
			$request->addTag('federated');
		}

		if ($groupFolders === '1') {
			$request->addTag('group_folders');
		}
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryShareNames(SearchRequest $request) {
		$query = [];
		$words = explode(' ', $request->getSearch());
		foreach ($words as $word) {
			array_push(
				$query, ['share_names.' . $request->getAuthor() => '*' . $word . '*']
			);
		}
		$request->addWildcardQueries($query);
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryShareOptions(SearchRequest $request) {
		$this->searchQueryShareOptionsExtension($request);
	}


	private function searchQueryShareOptionsExtension(SearchRequest $request) {
		$extension = $request->getOption('files_extension');
		if ($extension === '') {
			return;
		}

		$request->addWildcardFilters(
			[
				['share_names.' . $request->getAuthor() => '*\.' . $extension],
				['title' => '*\.' . $extension]
			]
		);


//		$query = [
//			['wildcard' => ['share_names.' . $request->getAuthor() => '*\.' . $extension]],
//			['wildcard' => ['title' => '*\.' . $extension]]
//		];
//
//		$arr['params']['body']['query']['bool']['filter'][]['bool']['should'] = $query;
	}


}