<?php
/**
 * Created by PhpStorm.
 * User: maxence
 * Date: 12/13/17
 * Time: 4:11 PM
 */

namespace OCA\Files_FullNextSearch\Service;


use OCA\Files_FullNextSearch\Exceptions\FileIsNotIndexableException;
use OCA\FullNextSearch\Model\DocumentAccess;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserManager;
use OCP\Share\IManager;

class ExternalFilesService {


	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * ProviderService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	function __construct(
		IRootFolder $rootFolder, IUserManager $userManager, IManager $shareManager,
		ConfigService $configService, MiscService $miscService
	) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;

		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param DocumentAccess $access
	 * @param Node $file
	 *
	 * @throws FileIsNotIndexableException
	 */
	public function completeDocumentAccessWithMountShares(DocumentAccess &$access, Node $file) {

//		if ($file->getStorage()
//				 ->isLocal() === true) {
//			return;
//		}
//
//		$mountId = $file->getMountPoint()
//						->getMountId();
//
//		echo 'GET INFO !??? ' . $mountId;
//		if ($mountId === null) {
//			echo '############### NOT INDEXEABLE !!' . "
//		\n";
//			throw new FileIsNotIndexableException();
//		}
		echo ' GET INFO FROM DB' . "\n";

		//	$this->request

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

		if ($this->configService->getAppValue(ConfigService::INDEX_NON_LOCAL) !== '1') {
			throw new FileIsNotIndexableException();
		}

		$mountId = $file->getMountPoint()
						->getMountId();
		if ($mountId === null) {
			throw new FileIsNotIndexableException();
		}
	}



//	private function initUserExternalMountPoints()
//	{
//		if ($this->configService->getAppValue('index_files_external') !== '1')
//			return false;
//
//		if (! \OCP\App::isEnabled('files_external'))
//			return false;
//
//		$data = array();
//		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($this->userId);
//		foreach ($mounts as $mountPoint => $mount) {
//			$data[] = array(
//				'id' => $mount['id'],
//				'path' => $mountPoint,
//				'shares' => $mount['applicable'],
//				'personal' => $mount['personal']
//			);
//		}
//
//		$this->externalMountPoint = $data;
//	}


//	private static function getShareRightsFromExternalMountPoint($mountPoints, $path, &$data, &$entry)
//	{
//		if (! $entry->isExternal())
//			return false;
//
//		if (! key_exists('share_users', $data))
//			$data['share_users'] = array();
//		if (! key_exists('share_groups', $data))
//			$data['share_groups'] = array();
//
//		$edited = false;
//		foreach ($mountPoints as $mount) {
//			if ($mount['path'] !== $path)
//				continue;
//
//			$edited = true;
//			if (! $mount['personal']) {
//				$entry->setOwner('__global');
//				if (sizeof($mount['shares']['users']) == 1 && sizeof($mount['shares']['groups']) == 0 && $mount['shares']['users'][0] == 'all' && (! in_array('__all', $data['share_groups']))) {
//					array_push($data['share_groups'], '__all');
//					continue;
//				}
//			}
//
//			foreach ($mount['shares']['users'] as $share_user) {
//				if ($share_user != $entry->getOwner() && ! in_array($share_user, $data['share_users']))
//					array_push($data['share_users'], $share_user);
//			}
//
//			foreach ($mount['shares']['groups'] as $share_group) {
//				if (! in_array($share_group, $data['share_groups']))
//					array_push($data['share_groups'], $share_group);
//			}
//		}
//
//		return $edited;
//	}

}