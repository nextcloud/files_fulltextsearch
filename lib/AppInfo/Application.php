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


namespace OCA\Files_FullTextSearch\AppInfo;

use OCA\Files_FullTextSearch\Listeners\FileChanged;
use OCA\Files_FullTextSearch\Listeners\FileCreated;
use OCA\Files_FullTextSearch\Listeners\FileDeleted;
use OCA\Files_FullTextSearch\Listeners\FileRenamed;
use OCA\Files_FullTextSearch\Listeners\ShareCreated;
use OCA\Files_FullTextSearch\Listeners\ShareDeleted;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';


/**
 * Class Application
 *
 * @package OCA\Files_FullTextSearch\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'files_fulltextsearch';


	/**
	 * Application constructor.
	 *
	 * @param array $params
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_ID, $params);
	}


	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(NodeCreatedEvent::class, FileCreated::class);
		$context->registerEventListener(NodeWrittenEvent::class, FileChanged::class);
		$context->registerEventListener(NodeRenamedEvent::class, FileRenamed::class);
		$context->registerEventListener(NodeDeletedEvent::class, FileDeleted::class);

		$context->registerEventListener(ShareCreatedEvent::class, ShareCreated::class);
		$context->registerEventListener(ShareDeletedEvent::class, ShareDeleted::class);
	}


	/**
	 * @param IBootContext $context
	 *
	 * @throws Throwable
	 */
	public function boot(IBootContext $context): void {
	}
}
