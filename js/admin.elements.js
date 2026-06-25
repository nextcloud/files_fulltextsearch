/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: fts_admin_settings */
/** global: files_settings */



var files_elements = {
	files_div: null,
	files_local: null,
	files_external: null,
	files_group_folders: null,
	files_size: null,
	files_office: null,
	files_pdf: null,
	files_open_result_directly: null,

	init: function () {
		files_elements.files_div = document.getElementById('files');
		files_elements.files_local = document.getElementById('files_local');
		files_elements.files_external = document.getElementById('files_external');
		files_elements.files_group_folders = document.getElementById('files_group_folders');
		files_elements.files_size = document.getElementById('files_size');
		files_elements.files_office = document.getElementById('files_office');
		files_elements.files_pdf = document.getElementById('files_pdf');
		files_elements.files_open_result_directly = document.getElementById('files_open_result_directly');

		files_elements.files_local.addEventListener('change', files_elements.updateSettings);
		files_elements.files_external.addEventListener('change', files_elements.updateSettings);
		files_elements.files_group_folders.addEventListener('change', files_elements.updateSettings);
		files_elements.files_size.addEventListener('change', files_elements.updateSettings);
		files_elements.files_office.addEventListener('change', files_elements.updateSettings);
		files_elements.files_pdf.addEventListener('change', files_elements.updateSettings);
		files_elements.files_open_result_directly.addEventListener('change', files_elements.updateSettings);
	},


	updateSettings: function () {
		fts_admin_settings.tagSettingsAsNotSaved(this);
		files_settings.saveSettings();
	}


};

