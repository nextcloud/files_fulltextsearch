/*
 * FullNextSearch - Full Text Search your Nextcloud.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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
 *
 */

/** global: OC */
/** global: files_elements */
/** global: fns_admin_settings */



var files_settings = {

	config: null,

	refreshSettingPage: function () {

		$.ajax({
			method: 'GET',
			url: OC.generateUrl('/apps/files_fullnextsearch/admin/settings')
		}).done(function (res) {
			files_settings.updateSettingPage(res);
		});

	},

	updateSettingPage: function (result) {
		files_elements.files_external.prop('checked', (result.files_external === '1'));

		fns_admin_settings.tagSettingsAsSaved(files_elements.files_div);
	},


	saveSettings: function () {

		var data = {
			files_external: (files_elements.files_external.is(':checked')) ? 1 : 0
		};
		$.ajax({
			method: 'POST',
			url: OC.generateUrl('/apps/files_fullnextsearch/admin/settings'),
			data: {
				data: data
			}
		}).done(function (res) {
			files_settings.updateSettingPage(res);
		});

	}


};
