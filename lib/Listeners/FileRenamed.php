<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\Model\IIndex;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class FileRenamed extends ListenersCore implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof NodeRenamedEvent)) {
			return;
		}

		$node = $event->getTarget();
		try {
			$this->fullTextSearchManager->updateIndexStatus(
				'files', (string)$node->getId(), IIndex::INDEX_META
			);
		} catch (InvalidPathException|NotFoundException $e) {
			$this->logger->warning('issue while updating index status', ['exception' => $e]);
		}
	}
}
