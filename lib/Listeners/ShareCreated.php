<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\FullTextSearch\Model\IIndex;
use OCP\Share\Events\ShareCreatedEvent;

/**
 * Class ShareCreated
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class ShareCreated extends ListenersCore implements IEventListener {


	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof ShareCreatedEvent)) {
			return;
		}

		$share = $event->getShare();
		try {
			$node = $share->getNode();
			$this->fullTextSearchManager->updateIndexStatus(
				'files', (string)$node->getId(), IIndex::INDEX_META
			);
		} catch (Exception $e) {
		}
	}
}
