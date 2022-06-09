<?php

declare(strict_types=1);


/**
 * Files_FullTextSearch - Index the content of your files
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2020
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
