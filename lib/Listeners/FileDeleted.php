<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\Model\IIndex;

/**
 * Class FileDeleted
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class FileDeleted extends ListenersCore implements IEventListener {


	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof NodeDeletedEvent)) {
			return;
		}

		$node = $event->getNode();
		try {
			$this->fullTextSearchManager->updateIndexStatus(
				'files', (string)$node->getId(), IIndex::INDEX_REMOVE, true
			);
		} catch (InvalidPathException|NotFoundException $e) {
			$this->exception($e);
		}
	}
}
