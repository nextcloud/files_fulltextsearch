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
	searchTimeout: null,
	old_files: null,
	old_searchbox: null,
	search_input: null,
	search_result: null,
	template_entry: null
};


const Files = function () {
	this.init();
};


Files.prototype = {

	init: function () {
		var self = this;

		elements.old_files = $('#app-content-files');
		elements.old_searchbox = $('#searchbox');
		elements.old_searchbox.hide();

		elements.search_result = $('<div>');
		elements.search_result.insertBefore(elements.old_files);
		elements.search_input = $('<input>');
		elements.search_input.insertBefore(elements.old_searchbox);

		elements.search_input.on('input', function () {

			self.resetSearch();
			if (elements.searchTimeout === null && self.initSearch()) {
				elements.searchTimeout = _.delay(function () {
					self.initSearch();
					elements.searchTimeout = null;
				}, 1000);
			}
		});

		elements.template_entry = self.generateTemplateEntry();
		nextSearch.setEntryTemplateId(elements.template_entry, self);
		nextSearch.setResultContainerId(elements.search_result);
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


	resetSearch: function () {
		if (elements.search_input.val() !== '') {
			return;
		}

		elements.search_result.fadeOut(150, function () {
			elements.old_files.fadeIn(150);
		});

	},


	initSearch: function () {
		var search = elements.search_input.val();
		if (search.length < 3) {
			return false;
		}

		nextSearch.search('files', search, this.searchResult);

		return true;
	},


	searchResult: function (result) {
		elements.old_files.fadeOut(150, function () {
			elements.search_result.fadeIn(150);
		});

		console.log('ok');
	},


	onEntryGenerated: function (entry) {
		this.deleteEmptyDiv(entry, '#line1');
		this.deleteEmptyDiv(entry, '#line2');
	},


	deleteEmptyDiv: function (entry, divId) {
		var div = entry.find(divId);
		if (div.text() === '') {
			div.remove();
		}
	}
};


OCA.NextSearch.Files = Files;

$(document).ready(function () {
	OCA.NextSearch.navigate = new Files();
});



