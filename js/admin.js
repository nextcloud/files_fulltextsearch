/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: files_elements */
/** global: files_settings */

function ready(callback) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', callback);
	} else {
		callback();
	}
}


ready(function () {


	/**
	 * @constructs Fts_Files
	 */
	var Fts_Files = function () {
		Object.assign(Fts_Files.prototype, files_elements, files_settings);

		files_elements.init();
		files_settings.refreshSettingPage();
	};

	window.OCA = window.OCA || {};
	window.OCA.FullTextSearchAdmin = window.OCA.FullTextSearchAdmin || {};
	window.OCA.FullTextSearchAdmin.files = Fts_Files;
	window.OCA.FullTextSearchAdmin.files.settings = new Fts_Files();

});
