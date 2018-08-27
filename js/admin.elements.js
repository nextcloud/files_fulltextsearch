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

/** global: OCA */
/** global: fts_admin_settings */
/** global: files_settings */



var files_elements = {
	files_div: null,
	files_local: null,
	files_external: null,
	files_encrypted: null,
	files_federated: null,
	files_group_folders: null,
	files_size: null,
	files_office: null,
	files_pdf: null,
	files_image: null,
	files_audio: null,

	init: function () {
		files_elements.files_div = $('#files');
		files_elements.files_local = $('#files_local');
		files_elements.files_external = $('#files_external');
		files_elements.files_group_folders = $('#files_group_folders');
		files_elements.files_encrypted = $('#files_encrypted');
		files_elements.files_federated = $('#files_federated');
		files_elements.files_size = $('#files_size');
		files_elements.files_office = $('#files_office');
		files_elements.files_pdf = $('#files_pdf');
		files_elements.files_image = $('#files_image');
		files_elements.files_audio = $('#files_audio');

		files_elements.files_local.on('change', files_elements.updateSettings);
		files_elements.files_external.on('change', files_elements.updateSettings);
		files_elements.files_group_folders.on('change', files_elements.updateSettings);
		files_elements.files_encrypted.on('change', files_elements.updateSettings);
		files_elements.files_federated.on('change', files_elements.updateSettings);
		files_elements.files_size.on('change', files_elements.updateSettings);
		files_elements.files_office.on('change', files_elements.updateSettings);
		files_elements.files_pdf.on('change', files_elements.updateSettings);
		files_elements.files_image.on('change', files_elements.updateSettings);
		files_elements.files_audio.on('change', files_elements.updateSettings);
	},


	updateSettings: function () {
		fts_admin_settings.tagSettingsAsNotSaved($(this));
		files_settings.saveSettings();
	}


};


