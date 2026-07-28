<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\Model\IIndex;
use OCP\SystemTag\TagUnassignedEvent;

/**
 * @template-implements IEventListener<TagUnassignedEvent>
 */
class TagUnassigned extends ListenersCore implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof TagUnassignedEvent)) {
			return;
		}

		if ($event->getObjectType() !== 'files') {
			return;
		}

		foreach ($event->getObjectIds() as $objectId) {
			try {
				$this->fullTextSearchManager->updateIndexStatus(
					'files', $objectId, IIndex::INDEX_META
				);
			} catch (InvalidPathException|NotFoundException $e) {
				$this->logger->warning('issue while updating index status', ['exception' => $e]);
			}
		}
	}
}
