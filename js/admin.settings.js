/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */
/** global: files_elements */
/** global: fts_admin_settings */



var files_settings = {

	config: null,

	refreshSettingPage: function () {

		$.ajax({
			method: 'GET',
			url: OC.generateUrl('/apps/files_fulltextsearch/admin/settings')
		}).done(function (res) {
			files_settings.updateSettingPage(res);
		});

	},

	updateSettingPage: function (result) {
		files_elements.files_local.prop('checked', (result.files_local === true));
		files_elements.files_external.val(result.files_external);
		files_elements.files_group_folders.prop('checked', (result.files_group_folders === true));
		files_elements.files_size.val(result.files_size);
		files_elements.files_office.prop('checked', (result.files_office === true));
		files_elements.files_pdf.prop('checked', (result.files_pdf === true));
    	files_elements.files_open_result_directly.prop('checked', (result.files_open_result_directly === true));

		fts_admin_settings.tagSettingsAsSaved(files_elements.files_div);
	},


	saveSettings: function () {
		var data = {
			files_local: (files_elements.files_local.is(':checked')) ? 1 : 0,
			files_external: files_elements.files_external.val(),
			files_group_folders: (files_elements.files_group_folders.is(':checked')) ? 1 : 0,
			files_size: files_elements.files_size.val(),
			files_office: (files_elements.files_office.is(':checked')) ? 1 : 0,
			files_pdf: (files_elements.files_pdf.is(':checked')) ? 1 : 0,
			files_open_result_directly: (files_elements.files_open_result_directly.is(':checked')) ? 1 : 0
		};
		$.ajax({
			method: 'POST',
			url: OC.generateUrl('/apps/files_fulltextsearch/admin/settings'),
			data: {
				data: data
			}
		}).done(function (res) {
			files_settings.updateSettingPage(res);
		});

	}


};
