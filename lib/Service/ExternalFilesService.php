<?php
/**
 * Created by PhpStorm.
 * User: maxence
 * Date: 12/13/17
 * Time: 4:11 PM
 */

namespace OCA\Files_FullNextSearch\Service;


use OCA\Files_FullNextSearch\Db\MountRequest;
use OCA\Files_FullNextSearch\Exceptions\ExternalMountNotFoundException;
use OCA\Files_FullNextSearch\Exceptions\FileIsNotIndexableException;
use OCA\Files_FullNextSearch\Model\ExternalMount;
use OCA\FullNextSearch\Model\DocumentAccess;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserManager;
use OCP\Share\IManager;

class ExternalFilesService {


	const DOCUMENT_TYPE = 'external_files';

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var MountRequest */
	private $mountRequest;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/** @var ExternalMount[] */
	private $externalMounts = [];


	/**
	 * ProviderService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param MountRequest $mountRequest
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		MountRequest $mountRequest, ConfigService $configService, MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->mountRequest = $mountRequest;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $userId
	 */
	public function initExternalFilesForUser($userId) {
		$this->externalMounts = [];
		if (!\OCP\App::isEnabled('files_external')) {
			return;
		}

		if ($this->configService->getAppValue(ConfigService::INDEX_NON_LOCAL) !== '1') {
			return;
		}

		$this->externalMounts = $this->getExternalMountsForUser($userId);
	}


	/**
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 */
	public function externalFileMustBeIndexable(Node $file) {

		if ($file->getStorage()
				 ->isLocal() === true) {
			return;
		}

		$this->getExternalMount($file);
	}


	/**
	 * @param DocumentAccess $access
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 */
	public function completeDocumentAccessWithMountShares(DocumentAccess &$access, Node $file) {

		if ($file->getStorage()
				 ->isLocal() === true) {
			return;
		}

		$mount = $this->getExternalMount($file);

		$access->addUsers($mount->getUsers());
		$access->addGroups($mount->getGroups());

		// twist 'n tweak.
		if (!$mount->isGlobal()) {
			$access->setOwnerId($mount->getUsers()[0]);
		}
	}


	/**
	 * @param Node $file
	 *
	 * @return ExternalMount
	 * @throws FileIsNotIndexableException
	 */
	private function getExternalMount(Node $file) {

		foreach ($this->externalMounts as $mount) {
			if (strpos($file->getPath(), $mount->getPath()) === 0) {
				return $mount;
			}
		}

		throw new FileIsNotIndexableException();
	}


	/**
	 * @param $userId
	 *
	 * @return ExternalMount[]
	 */
	private function getExternalMountsForUser($userId) {

		$externalMounts = [];

		// TODO: deprecated - use UserGlobalStoragesService::getStorages() and UserStoragesService::getStorages()
		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($userId);
		foreach ($mounts as $mountPoint => $mount) {
			$externalMount = new ExternalMount();
			$externalMount->setId($mount['id'])
						  ->setPath($mountPoint)
						  ->setGroups($mount['applicable']['groups'])
						  ->setUsers($mount['applicable']['users'])
						  ->setGlobal((!$mount['personal']));
			$externalMounts[] = $externalMount;
		}

		return $externalMounts;
	}

}