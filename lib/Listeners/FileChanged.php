<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\FullTextSearch\Model\IIndex;
use Psr\Log\LoggerInterface;

/**
 * Class FileChanged
 *
 * @package OCA\Files_FullTextSearch\Listeners
 */
class FileChanged extends ListenersCore implements IEventListener {


	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$this->registerFullTextSearchServices() || !($event instanceof NodeWrittenEvent)) {
			return;
		}

		$node = $event->getNode();

		$logger = \OC::$server->get(LoggerInterface::class);
		$logger->notice('FileChanged event ' . (string)$node->getId());

		try {
			$this->fullTextSearchManager->updateIndexStatus(
				'files', (string)$node->getId(), IIndex::INDEX_CONTENT
			);
		} catch (InvalidPathException|NotFoundException $e) {
			$this->exception($e);
		}
	}
}
