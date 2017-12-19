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


/** global: OCA */

const nextSearch = OCA.NextSearch.api;


var elements = {
	old_files: null,
	old_searchbox: null,
	search_result: null,
	template_entry: null
};


const Files_FullNextSearch = function () {
	this.init();
};


Files_FullNextSearch.prototype = {

	init: function () {
		var self = this;

		elements.old_files = $('#app-content-files');
		elements.old_searchbox = $('FORM.searchbox');
		elements.old_searchbox.hide();

		elements.search_result = $('<div>');
		elements.search_result.insertBefore(elements.old_files);

		elements.search_input = $('#next_search_input');


		//
		// $(document).keypress(function (e) {
		// 	if (e.which === 13) {
		// 		self.initSearch(true);
		// 	}
		// });

		elements.template_entry = self.generateTemplateEntry();
		nextSearch.setEntryTemplateId(elements.template_entry, self);
		nextSearch.setResultContainerId(elements.search_result);
		nextSearch.addSearchBar('files');
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


	searchResult: function (result) {
		elements.old_files.fadeOut(150, function () {
			elements.search_result.fadeIn(150);
		});

		console.log('> ' + JSON.stringify(result));
	},


	onEntryGenerated: function (entry) {
	},



	onResultDisplayed: function () {
		elements.old_files.fadeOut(150, function () {
			elements.search_result.fadeIn(150);
		});
	},


	onSearchReset: function () {
		elements.search_result.fadeOut(150, function () {
			elements.old_files.fadeIn(150);
		});
	}


};


OCA.NextSearch.Files = Files_FullNextSearch;

$(document).ready(function () {
	OCA.NextSearch.navigate = new Files_FullNextSearch();
});



