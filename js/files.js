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
	search_icon_close: null,
	search_icon: null,
	search_input: null,
	search_form: null,
	search_result: null,
	template_entry: null
};


var settings = {
	lock_searchbox: false
}
const Files = function () {
	this.init();
};


Files.prototype = {

	init: function () {
		var self = this;

		elements.old_files = $('#app-content-files');
		elements.old_searchbox = $('FORM.searchbox');
		elements.old_searchbox.hide();

		var divHeaderRight = $('div.header-right');

		elements.search_div = $('<div>', {class: 'next_search_div'});
		divHeaderRight.prepend(elements.search_div);

		elements.search_result = $('<div>');
		elements.search_result.insertBefore(elements.old_files);

		elements.search_icon = $('<div>', {class: 'icon-fullnextsearch'});
		elements.search_icon.css('background-image',
			"url('/apps/fullnextsearch/img/fullnextsearch.svg')");
		elements.search_icon.fadeTo(0, 0.7);
		elements.search_div.append(elements.search_icon);


		elements.search_form = $('<div>');
		elements.search_form.fadeTo(0, 0);

		elements.search_input = $('<input>', {
			class: 'search_input',
			placeholder: 'Search'
		});
		elements.search_form.append(elements.search_input);


		elements.search_icon_close = $('<div>', {class: 'icon-close-white icon-close-fullnextsearch'});
		elements.search_icon_close.fadeTo(0, 0);
		elements.search_icon_close.on('click', function () {
			settings.lock_searchbox = false;
			elements.search_icon_close.stop().fadeTo(100, 0);
			elements.search_input.val('');
		});
		elements.search_form.append(elements.search_icon_close);

		elements.search_div.append(elements.search_form);

		elements.search_div.hover(function () {
			elements.search_icon.stop().fadeTo(100, 0);
			elements.search_form.stop().fadeTo(100, 0.8);
		}, function () {
			if (settings.lock_searchbox === true) {
				return;
			}
			elements.search_form.stop().fadeTo(500, 0);
			elements.search_icon.stop().fadeTo(800, 0.7);
		});

		elements.search_input.on('focus', function () {
			settings.lock_searchbox = true;
			elements.search_icon_close.stop().fadeTo(200, 1);
		});

		elements.search_input.on('input', function () {

			self.resetSearch();

			if (elements.searchTimeout === null && self.initSearch(false)) {
				elements.searchTimeout = _.delay(function () {
					self.initSearch(false);
					elements.searchTimeout = null;
				}, 2000);
			}
		});

		$(document).keypress(function (e) {
			if (e.which === 13) {
				self.initSearch(true);
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


	initSearch: function (force) {
		var search = elements.search_input.val();

		if (!force && search.length < 3) {
			return false;
		}

		nextSearch.search('files', search, this.searchResult);

		return true;
	},


	searchResult: function (result) {
		elements.old_files.fadeOut(150, function () {
			elements.search_result.fadeIn(150);
		});

		console.log('> ' + JSON.stringify(result));
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



