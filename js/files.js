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

const fullTextSearch = OCA.FullTextSearch.api;


var elements = {
	old_files: null,
	search_result: null,
	current_dir: ''
};


const Files_FullTextSearch = function () {
	this.init();
};


Files_FullTextSearch.prototype = {

	init: function () {
		var self = this;

		elements.old_files = $('#app-content-files');

		elements.search_result = $('<div>');
		elements.search_result.insertBefore(elements.old_files);

		fullTextSearch.setEntryTemplate(self.generateTemplateEntry());
		fullTextSearch.setResultContainer(elements.search_result);
		fullTextSearch.initFullTextSearch('files', 'files', self);
	},


	generateTemplateEntry: function () {

		var divLeft = $('<div>', {class: 'result_entry_left'});
		divLeft.append($('<div>', {id: 'title'}));
		divLeft.append($('<div>', {id: 'line1'}));
		divLeft.append($('<div>', {id: 'line2'}));

		var divRight = $('<div>', {class: 'result_entry_right'});
		divRight.append($('<div>', {id: 'score'}));

		var divDefault = $('<div>', {class: 'result_entry_default'});
		divDefault.append(divLeft);
		divDefault.append(divRight);

		return $('<div>').append(divDefault);
	},


	onEntryGenerated: function (entry) {
	},


	onResultDisplayed: function () {
		elements.old_files.fadeOut(150, function () {
			elements.search_result.fadeIn(150);
		});
	},


	onSearchRequest: function (data) {
		if (data.options.files_within_dir === '1') {
			var url = new URL(window.location.href);
			data.options.files_within_dir = url.searchParams.get("dir");
		}
	},


	onSearchReset: function () {
		elements.search_result.fadeOut(150, function () {
			elements.old_files.fadeIn(150);
		});
	}

};


OCA.FullTextSearch.Files = Files_FullTextSearch;

$(document).ready(function () {
	OCA.FullTextSearch.navigate = new Files_FullTextSearch();
});



