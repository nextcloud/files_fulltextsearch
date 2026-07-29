<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

class FolderManager {
	public const SPACE_DEFAULT = -4;

	/**
	 * @throws Exception
	 */
	public function getFolder(int $id): ?FolderDefinitionWithMappings {
	}

	/**
	 * @return array<int, FolderDefinitionWithMappings>
	 * @throws Exception
	 */
	public function getAllFolders(): array {
	}
}
