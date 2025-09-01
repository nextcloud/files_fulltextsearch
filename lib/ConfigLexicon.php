<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch;

use OCA\Files_FullTextSearch\Service\FilesService;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

/**
 * Config Lexicon for fulltextsearch_elasticsearch.
 *
 * Please Add & Manage your Config Keys in that file and keep the Lexicon up to date!
 */
class ConfigLexicon implements ILexicon {
	public const FILES_LOCAL = 'files_local';
	public const FILES_EXTERNAL = 'files_external';
	public const FILES_GROUP_FOLDERS = 'files_group_folders';
	public const FILES_SIZE = 'files_size';
	public const FILES_OFFICE = 'files_office';
	public const FILES_PDF = 'files_pdf';
	public const FILES_ZIP = 'files_zip';
	public const FILES_CHUNK_SIZE = 'files_chunk_size';
	public const FILES_OPEN_RESULT_DIRECTLY = 'files_open_result_directly';

	public function getStrictness(): Strictness {
		return Strictness::NOTICE;
	}

	public function getAppConfigs(): array {
		return [
			new Entry(key: self::FILES_LOCAL, type: ValueType::BOOL, defaultRaw: true, definition: 'index local filesystem'),
			new Entry(key: self::FILES_EXTERNAL, type: ValueType::INT, defaultRaw: 0, definition: 'index external filesystem'),
			new Entry(key: self::FILES_GROUP_FOLDERS, type: ValueType::BOOL, defaultRaw: false, definition: 'index team folders'),
			new Entry(key: self::FILES_SIZE, type: ValueType::INT, defaultRaw: 20, definition: 'file size limit (in MB)'),
			new Entry(key: self::FILES_OFFICE, type: ValueType::BOOL, defaultRaw: true, definition: 'index Office file'),
			new Entry(key: self::FILES_PDF, type: ValueType::BOOL, defaultRaw: true, definition: 'index pdf file'),
			new Entry(key: self::FILES_ZIP, type: ValueType::BOOL, defaultRaw: false, definition: 'index zip file'),
			new Entry(key: self::FILES_CHUNK_SIZE, type: ValueType::INT, defaultRaw: FilesService::CHUNK_TREE_SIZE, definition: 'depth of subfolders per chunks'),
			new Entry(key: self::FILES_OPEN_RESULT_DIRECTLY, type: ValueType::BOOL, defaultRaw: false, definition: 'open result directly, instead of the containing folder'),
		];
	}

	public function getUserConfigs(): array {
		return [];
	}
}
