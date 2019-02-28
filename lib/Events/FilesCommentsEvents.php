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


namespace OCA\Files_FullTextSearch\Events;


use OCP\AppFramework\QueryException;
use OCP\Comments\CommentsEvent;
use OCP\Comments\ICommentsEventHandler;


class FilesCommentsEvents implements ICommentsEventHandler {


	/** @var FilesEvents */
	private $filesEvents;


	public function __construct(FilesEvents $filesEvents) {
		$this->filesEvents = $filesEvents;
	}

	/**
	 * @param CommentsEvent $event
	 *
	 * @throws QueryException
	 */
	public function handle(CommentsEvent $event) {
		if ($event->getComment()
				  ->getObjectType() !== 'files') {
			return;
		}

		$eventType = $event->getEvent();
		if ($eventType === CommentsEvent::EVENT_ADD) {
			$this->filesEvents->onCommentNew($event);

			return;
		}
	}

}

