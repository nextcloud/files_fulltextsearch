/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: files_elements */
/** global: files_settings */


$(document).ready(function () {


	/**
	 * @constructs Fts_Files
	 */
	var Fts_Files = function () {
		$.extend(Fts_Files.prototype, files_elements);
		$.extend(Fts_Files.prototype, files_settings);

		files_elements.init();
		files_settings.refreshSettingPage();
	};

	OCA.FullTextSearchAdmin.files = Fts_Files;
	OCA.FullTextSearchAdmin.files.settings = new Fts_Files();

});
