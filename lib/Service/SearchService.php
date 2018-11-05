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


namespace OCA\Files_FullTextSearch\Service;


use daita\MySmallPhpTools\Traits\TPathTools;
use Exception;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCP\Files\Node;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;


/**
 * Class SearchService
 *
 * @package OCA\Files_FullTextSearch\Service
 */
class SearchService {


	use TPathTools;


	/** @var string */
	private $userId;

	/** @var FilesService */
	private $filesService;

	/** @var ConfigService */
	private $configService;

	/** @var ExtensionService */
	private $extensionService;

	/** @var MiscService */
	private $miscService;


	/**
	 * SearchService constructor.
	 *
	 * @param string $userId
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 * @param ExtensionService $extensionService
	 * @param MiscService $miscService
	 *
	 * @internal param IProviderFactory $factory
	 */
	public function __construct(
		$userId, FilesService $filesService, ConfigService $configService,
		ExtensionService $extensionService, MiscService $miscService
	) {
		$this->userId = $userId;
		$this->filesService = $filesService;
		$this->configService = $configService;
		$this->extensionService = $extensionService;
		$this->miscService = $miscService;
	}


	/**
	 * @param ISearchRequest $request
	 */
	public function improveSearchRequest(ISearchRequest $request) {
		$this->searchQueryShareNames($request);
		$this->searchQueryWithinDir($request);
		$this->searchQueryInOptions($request);
		$this->searchQueryFiltersExtension($request);
		$this->searchQueryFiltersSource($request);

		$this->extensionService->searchRequest($request);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryShareNames(ISearchRequest $request) {
		$username = $this->miscService->secureUsername($request->getAuthor());
		$request->addField('share_names.' . $username);

		$request->addWildcardField('title');
		$request->addWildcardField('share_names.' . $username);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryWithinDir(ISearchRequest $request) {

		$currentDir = $request->getOption('files_within_dir');
		if ($currentDir === '') {
			return;
		}

		$username = $this->miscService->secureUsername($request->getAuthor());
		$currentDir = $this->withoutBeginSlash($this->withEndSlash($currentDir));
		$request->addRegexFilters(
			[
				['share_names.' . $username => $currentDir . '.*'],
				['title' => $currentDir . '.*']
			]
		);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryFiltersExtension(ISearchRequest $request) {
		$extension = $request->getOption('files_extension');
		if ($extension === '') {
			return;
		}

		$username = $this->miscService->secureUsername($request->getAuthor());
		$request->addRegexFilters(
			[
				['share_names.' . $username => '.*\.' . $extension],
				['title' => '.*\.' . $extension]
			]
		);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryFiltersSource(ISearchRequest $request) {

		$local = $request->getOption('files_local');
		$external = $request->getOption('files_external');
		$groupFolders = $request->getOption('files_group_folders');
		$federated = $request->getOption('files_federated');

		if (count(array_unique([$local, $external, $groupFolders])) === 1) {
			return;
		}

		$this->addMetaTagToSearchRequest($request, 'files_local', (int)$local);
		$this->addMetaTagToSearchRequest($request, 'files_external', (int)$external);
		$this->addMetaTagToSearchRequest($request, 'files_group_folders', (int)$groupFolders);
	}


	/**
	 * @param ISearchRequest $request
	 */
	private function searchQueryInOptions(ISearchRequest $request) {
		$in = $request->getOptionArray('in', []);

		if (in_array('filename', $in)) {
			$username = $this->miscService->secureUsername($request->getAuthor());
			$request->addLimitField('share_names.' . $username);
			$request->addLimitField('title');
		}

		if (in_array('content', $in)) {
			$request->addLimitField('content');
		}
	}


	/**
	 * @param ISearchRequest $request
	 * @param string $tag
	 * @param int $cond
	 */
	private function addMetaTagToSearchRequest(ISearchRequest $request, string $tag, int $cond) {
		if ($cond === 1) {
			$request->addMetaTag($tag);
		}
	}


	/**
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult) {
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
		$this->extensionService->searchResult($searchResult);
	}


	/**
	 * @param FilesDocument $document
	 *
	 * @throws Exception
	 */
	private function setDocumentInfo(FilesDocument $document) {
		$document->setInfo('webdav', $this->getWebdavId((int)$document->getId()));

		$file = $this->filesService->getFileFromId($this->userId, (int)$document->getId());

		$this->setDocumentInfoFromFile($document, $file);
	}


	/**
	 * @param FilesDocument $document
	 * @param Node $file
	 */
	private function setDocumentInfoFromFile(FilesDocument $document, Node $file) {

		// TODO: better way to do this : we remove the '/userId/files/'
		$path = $this->withoutEndSlash(substr($file->getPath(), 7 + strlen($this->userId)));
		$pathInfo = pathinfo($path);

		$document->setPath($path);
		$document->setInfo('path', $path)
				 ->setInfo('type', $file->getType())
				 ->setInfo('file', $pathInfo['basename'])
				 ->setInfo('dir', $pathInfo['dirname'])
				 ->setInfo('mime', $file->getMimetype())
				 ->setInfoBool('favorite', false); // FIXME: get the favorite status

		try {
			$document->setInfoInt('size', $file->getSize())
					 ->setInfoInt('mtime', $file->getMTime())
					 ->setInfo('etag', $file->getEtag())
					 ->setInfoInt('permissions', $file->getPermissions());
		} catch (Exception $e) {
		}
	}


	/**
	 * @param FilesDocument $document
	 */
	private function setDocumentTitle(FilesDocument $document) {
		if (!is_null($document->getPath()) && $document->getPath() !== '') {
			$document->setTitle($this->withoutBeginSlash($document->getPath()));
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
	 * @param string $dir
	 * @param string $filename
	 */
	private function setDocumentLinkFile(FilesDocument $document, string $dir, string $filename) {
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
	 * @param string $dir
	 */
	private function setDocumentLinkDir(FilesDocument $document, string $dir) {
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
	private function getWebdavId(int $fileId): string {
		$instanceId = $this->configService->getSystemValue('instanceid');

		return sprintf("%08s", $fileId) . $instanceId;
	}


}

