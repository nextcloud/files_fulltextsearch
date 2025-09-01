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
		files_elements.files_div = $('#files');
		files_elements.files_local = $('#files_local');
		files_elements.files_external = $('#files_external');
		files_elements.files_group_folders = $('#files_group_folders');
		files_elements.files_size = $('#files_size');
		files_elements.files_office = $('#files_office');
		files_elements.files_pdf = $('#files_pdf');
		files_elements.files_open_result_directly = $('#files_open_result_directly');

		files_elements.files_local.on('change', files_elements.updateSettings);
		files_elements.files_external.on('change', files_elements.updateSettings);
		files_elements.files_group_folders.on('change', files_elements.updateSettings);
		files_elements.files_size.on('change', files_elements.updateSettings);
		files_elements.files_office.on('change', files_elements.updateSettings);
		files_elements.files_pdf.on('change', files_elements.updateSettings);
		files_elements.files_open_result_directly.on('change', files_elements.updateSettings);
	},


	updateSettings: function () {
		fts_admin_settings.tagSettingsAsNotSaved($(this));
		files_settings.saveSettings();
	}


};


