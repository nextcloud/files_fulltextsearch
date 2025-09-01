<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\Model\IIndex;
use OCP\Share\Events\ShareDeletedEvent;

/**
 * Class ShareDeleted
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class ShareDeleted extends ListenersCore implements IEventListener {


	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof ShareDeletedEvent)) {
			return;
		}

		$share = $event->getShare();
		try {
			$node = $share->getNode();
			$this->fullTextSearchManager->updateIndexStatus(
				'files',
				(string)$node->getId(),
				IIndex::INDEX_META
			);
		} catch (InvalidPathException|NotFoundException $e) {
			$this->exception($e);
		}
	}
}
