<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\Model\IIndex;
use Psr\Log\LoggerInterface;

/**
 * Class FileCreated
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class FileCreated extends ListenersCore implements IEventListener {


	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof NodeCreatedEvent)) {
			return;
		}

		$node = $event->getNode();
		$user = $this->userSession->getUser();

		$userId = ($user === null) ? 'null' : $user->getUID();
		$logger = \OC::$server->get(LoggerInterface::class);
		$logger->notice('FileCreated event ' . (string)$node->getId() . ' by ' . $userId);

		if ($user === null) {
			return;
		}

		try {
			$this->fullTextSearchManager->createIndex(
				'files', (string)$node->getId(), $user->getUID(), IIndex::INDEX_FULL
			);
		} catch (InvalidPathException|NotFoundException $e) {
			$this->logger->warning('issue while updating index status', ['exception' => $e]);
		}
	}
}
