/*
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
		files_elements.files_local.prop('checked', (result.files_local === '1'));
		files_elements.files_external.prop('checked', (result.files_external === '1'));
		files_elements.files_group_folders.prop('checked', (result.files_group_folders === '1'));
		files_elements.files_encrypted.prop('checked', (result.files_encrypted === '1'));
		files_elements.files_federated.prop('checked', (result.files_federated === '1'));
		files_elements.files_size.val(result.files_size);
		files_elements.files_office.prop('checked', (result.files_office === '1'));
		files_elements.files_pdf.prop('checked', (result.files_pdf === '1'));
		files_elements.files_image.prop('checked', (result.files_image === '1'));
		files_elements.files_audio.prop('checked', (result.files_audio === '1'));

		fts_admin_settings.tagSettingsAsSaved(files_elements.files_div);
	},


	saveSettings: function () {
		var data = {
			files_local: (files_elements.files_local.is(':checked')) ? 1 : 0,
			files_external: (files_elements.files_external.is(':checked')) ? 1 : 0,
			files_encrypted: (files_elements.files_encrypted.is(':checked')) ? 1 : 0,
			files_federated: (files_elements.files_federated.is(':checked')) ? 1 : 0,
			files_group_folders: (files_elements.files_group_folders.is(':checked')) ? 1 : 0,
			files_size: files_elements.files_size.val(),
			files_office: (files_elements.files_office.is(':checked')) ? 1 : 0,
			files_pdf: (files_elements.files_pdf.is(':checked')) ? 1 : 0,
			files_image: (files_elements.files_image.is(':checked')) ? 1 : 0,
			files_audio: (files_elements.files_audio.is(':checked')) ? 1 : 0
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
