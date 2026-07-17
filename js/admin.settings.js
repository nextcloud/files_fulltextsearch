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

		files_settings.request('GET', '/apps/files_fulltextsearch/admin/settings').then(function (res) {
			files_settings.updateSettingPage(res);
		});

	},

	updateSettingPage: function (result) {
		files_elements.files_local.checked = (result.files_local === true);
		files_elements.files_external.value = result.files_external;
		files_elements.files_group_folders.checked = (result.files_group_folders === true);
		files_elements.files_size.value = result.files_size;
		files_elements.files_office.checked = (result.files_office === true);
		files_elements.files_pdf.checked = (result.files_pdf === true);
		files_elements.files_open_result_directly.checked = (result.files_open_result_directly === true);

		fts_admin_settings.tagSettingsAsSaved(files_elements.files_div);
	},


	saveSettings: function () {
		var data = {
			files_local: files_elements.files_local.checked ? 1 : 0,
			files_external: files_elements.files_external.value,
			files_group_folders: files_elements.files_group_folders.checked ? 1 : 0,
			files_size: files_elements.files_size.value,
			files_office: files_elements.files_office.checked ? 1 : 0,
			files_pdf: files_elements.files_pdf.checked ? 1 : 0,
			files_open_result_directly: files_elements.files_open_result_directly.checked ? 1 : 0
		};
		files_settings.request('POST', '/apps/files_fulltextsearch/admin/settings', data).then(function (res) {
			files_settings.updateSettingPage(res);
		});

	},


	request: function (method, route, data) {
		var options = {
			method: method,
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'requesttoken': window.OC ? window.OC.requestToken : ''
			}
		};

		if (method === 'POST') {
			options.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
			options.body = files_settings.encodeData(data);
		}

		return fetch(window.OC.generateUrl(route), options).then(function (response) {
			if (!response.ok) {
				throw new Error('Request failed: ' + response.status);
			}

			return response.json();
		});
	},


	encodeData: function (data) {
		var params = new URLSearchParams();
		Object.keys(data).forEach(function (key) {
			params.append('data[' + key + ']', data[key]);
		});

		return params.toString();
	}


};
