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
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\FullTextSearch\Model\Index;
use OCA\FullTextSearch\Model\SearchRequest;
use OCA\FullTextSearch\Model\SearchResult;
use OCP\Files\Node;

class SearchService {

	/** @var string */
	private $userId;

	/** @var FilesService */
	private $filesService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * SearchService constructor.
	 *
	 * @param string $userId
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	public function __construct(
		$userId, FilesService $filesService, ConfigService $configService, MiscService $miscService
	) {
		$this->userId = $userId;
		$this->filesService = $filesService;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param SearchRequest $request
	 */
	public function improveSearchRequest(SearchRequest $request) {
		$this->searchQueryShareNames($request);
		$this->searchQueryWithinDir($request);
		$this->searchQueryInOptions($request);
		$this->searchQueryFiltersExtension($request);
		$this->searchQueryFiltersSource($request);
		$this->searchQueryFiltersTags($request);
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryShareNames(SearchRequest $request) {
		$username = MiscService::secureUsername($request->getAuthor());
		$request->addField('share_names.' . $username);

		$request->addWildcardField('title');
		$request->addWildcardField('share_names.' . $username);
	}


	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryWithinDir(SearchRequest $request) {

		$currentDir = $request->getOption('files_within_dir');
		if ($currentDir === '') {
			return;
		}

		$username = MiscService::secureUsername($request->getAuthor());
		$currentDir = MiscService::noBeginSlash(MiscService::endSlash($currentDir));
		$request->addRegexFilters(
			[
				['share_names.' . $username => $currentDir . '*'],
				['title' => $currentDir . '*']
			]
		);
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

	private function searchQueryFiltersTags(Searchrequest $request) {
		$tags = $request->getOption('tag');
		foreach ($tags as $tag) {
			$this->addTagToSearchRequest($request, 'usertag_' . strtolower($tag), 1);
		}
	}

	/**
	 * @param SearchRequest $request
	 */
	private function searchQueryInOptions(SearchRequest $request) {
		$in = $request->getOption('in');

		if (!is_array($in)) {
			return;
		}

		if (in_array('filename', $in)) {
			$username = MiscService::secureUsername($request->getAuthor());
			$request->limitToField('share_names.' . $username);
			$request->limitToField('title');
		}

		if (in_array('content', $in)) {
			$request->limitToField('content');
		}
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


	/**
	 * @param SearchResult $searchResult
	 */
	public function improveSearchResult(SearchResult $searchResult) {
		$indexDocuments = $searchResult->getDocuments();
		$filesDocuments = [];
		foreach ($indexDocuments as $indexDocument) {

			try {
				$filesDocument = FilesDocument::fromIndexDocument($indexDocument);
				$this->setDocumentInfo($filesDocument);
				$this->setDocumentTitle($filesDocument);
				$this->setDocumentLink($filesDocument);

				$filesDocuments[] = $filesDocument;
			} catch (Exception $e) {
			}
		}

		$searchResult->setDocuments($filesDocuments);
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @throws Exception
	 */
	private function setDocumentInfo(FilesDocument $document) {
		$index = new Index('files', $document->getId());
		$index->setOwnerId($this->userId);

		$document->setInfo('webdav', $this->getWebdavId($document->getId()));

		$file = $this->filesService->getFileFromIndex($index);
		$this->setDocumentInfoFromFile($document, $file);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function setDocumentInfoFromFile(FilesDocument $document, Node $file) {

		// TODO: better way to do this : we remove the '/userId/files/'
		$path = MiscService::noEndSlash(substr($file->getPath(), 7 + strlen($this->userId)));
		$pathInfo = pathinfo($path);

		$document->setPath($path);
		$document->setInfo('type', $file->getType())
				 ->setInfo('file', $pathInfo['basename'])
				 ->setInfo('path', $pathInfo['dirname'])
				 ->setInfo('mime', $file->getMimetype())
				 ->setInfo('favorite', false); // FIXME: get the favorite status

		try {
			$document->setInfo('size', $file->getSize())
					 ->setInfo('mtime', $file->getMTime())
					 ->setInfo('etag', $file->getEtag())
					 ->setInfo('permissions', $file->getPermissions());
		} catch (Exception $e) {
		}
	}


	/**
	 * @param FilesDocument $document
	 */
	private function setDocumentTitle(FilesDocument $document) {
		if (!is_null($document->getPath()) && $document->getPath() !== '') {
			$document->setTitle(MiscService::noBeginSlash($document->getPath()));
		} else {
			$document->setTitle($document->getTitle());
		}
	}


	/**
	 * @param FilesDocument $document
	 */
	private function setDocumentLink(FilesDocument $document) {

		$path = $document->getPath();
		$filename = $document->getInfo('file');
		$dir = substr($path, 0, -strlen($filename));

		$this->setDocumentLinkDir($document, $dir);
		$this->setDocumentLinkFile($document, $dir, $filename);
	}


	/**
	 * @param FilesDocument $document
	 * @param $dir
	 * @param $filename
	 */
	private function setDocumentLinkFile(FilesDocument $document, $dir, $filename) {
		if ($document->getInfo('type') !== 'file') {
			return;
		}

		$document->setLink(
			\OC::$server->getURLGenerator()
						->linkToRoute(
							'files.view.index',
							[
								'dir'      => $dir,
								'scrollto' => $filename,
							]
						)
		);
	}


	/**
	 * @param FilesDocument $document
	 * @param $dir
	 */
	private function setDocumentLinkDir(FilesDocument $document, $dir) {
		if ($document->getInfo('type') !== 'dir') {
			return;
		}

		$document->setLink(
			\OC::$server->getURLGenerator()
						->linkToRoute(
							'files.view.index',
							['dir' => $dir, 'fileid' => $document->getId()]
						)
		);
	}

	/**
	 * @param int $fileId
	 *
	 * @return string
	 */
	private function getWebdavId($fileId) {
		$instanceId = $this->configService->getSystemValue('instanceid');

		return sprintf("%08s", $fileId) . $instanceId;
	}


}