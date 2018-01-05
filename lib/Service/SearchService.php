<?php
/**
 * Files_FullNextSearch - Index your files
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
 */

namespace OCA\Files_FullNextSearch\Service;


use Exception;
use OC\Share\Constants;
use OC\Share\Share;
use OCA\Files_FullNextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullNextSearch\Model\FilesDocument;
use OCA\Files_FullNextSearch\Provider\FilesProvider;
use OCA\FullNextSearch\Exceptions\InterruptException;
use OCA\FullNextSearch\Exceptions\TickDoesNotExistException;
use OCA\FullNextSearch\Model\DocumentAccess;
use OCA\FullNextSearch\Model\Index;
use OCA\FullNextSearch\Model\IndexDocument;
use OCA\FullNextSearch\Model\Runner;
use OCA\FullNextSearch\Model\SearchRequest;
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
		$extension = $request->getOption('files_extension');
		$withinDir = $request->getOption('files_withindir');

		// current dir ? files_withindir
		// filter on file extension ?

		if (count(array_unique([$local, $external])) === 1) {
			return;
		}

		if ($local === '1') {
			$request->addTag('local');
		}

		if ($external === '1') {
			$request->addTag('external');
		}
	}

}