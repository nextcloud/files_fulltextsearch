<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\AppInfo;

use OCA\Files_FullTextSearch\ConfigLexicon;
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
		$context->registerConfigLexicon(ConfigLexicon::class);
	}


	/**
	 * @param IBootContext $context
	 *
	 * @throws Throwable
	 */
	public function boot(IBootContext $context): void {
	}
}
