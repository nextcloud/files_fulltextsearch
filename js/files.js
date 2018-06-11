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

var fullTextSearch = OCA.FullTextSearch.api;


var elements = {
	old_files: null,
	search_result: null,
	current_dir: ''
};


var FullTextSearch = function () {
	this.init();
};


FullTextSearch.prototype = {

	/**
	 * File actions handler, defaults to OCA.Files.FileActions
	 * @type OCA.Files.FileActions
	 */
	fileActions: null,


	init: function () {
		var self = this;

		elements.old_files = $('#app-content-files');

		elements.search_result = $('<div>');
		elements.search_result.insertBefore(elements.old_files);

		fullTextSearch.setResultContainer(elements.search_result);
		fullTextSearch.setEntryTemplate(self.generateEntryTemplate());
		fullTextSearch.setResultHeader(self.generateResultHeader());
		// fullTextSearch.setResultFooter(self.generateResultFooter());

		fullTextSearch.initFullTextSearch('files', 'files', self);

		this._initFileActions();
	},

	_initFileActions: function () {

		this.fileActions = new OCA.Files.FileActions();
		this.fileActions.registerDefaultActions();
		this.fileActions.merge(window.FileActions);
		this.fileActions.merge(OCA.Files.fileActions)

		this._onActionsUpdated = _.bind(this._onActionsUpdated, this);
		OCA.Files.fileActions.on('setDefault.app-files', this._onActionsUpdated);
		OCA.Files.fileActions.on('registerAction.app-files', this._onActionsUpdated);
		window.FileActions.on('setDefault.app-files', this._onActionsUpdated);
		window.FileActions.on('registerAction.app-files', this._onActionsUpdated);

		// if (this._detailsView) {
		// 	this.fileActions.registerAction({
		// 		name: 'Details',
		// 		displayName: t('files', 'Details'),
		// 		mime: 'all',
		// 		order: -50,
		// 		iconClass: 'icon-details',
		// 		permissions: OC.PERMISSION_NONE,
		// 		actionHandler: function (fileName, context) {
		// 			self._updateDetailsView(fileName);
		// 		}
		// 	});
		// }
	},


	_onActionsUpdated: function (ev, newAction) {
		if (ev.action) {
			this.fileActions.registerAction(ev.action);
		} else if (ev.defaultAction) {
			this.fileActions.setDefault(
				ev.defaultAction.mime,
				ev.defaultAction.name
			);
		}
	},


	generateResultHeader: function () {

		var resultHeader = $('<div>', {class: 'files_header'});
		resultHeader.append($('<div>', {class: 'files_div_checkbox'}).html('&nbsp;'));
		resultHeader.append($('<div>', {class: 'files_div_thumb'}).html('&nbsp;'));
		resultHeader.append(
			$('<div>', {class: 'files_header_div files_div_name'}).text(_('Name')));
		resultHeader.append(
			$('<div>', {class: 'files_header_div files_div_modified'}).text(_('Modified')));
		resultHeader.append(
			$('<div>', {class: 'files_header_div files_div_size'}).text(_('Size')));

		return resultHeader;
	},


	generateResultFooter: function () {
		var resultFooter = $('<div>', {class: 'files_footer'});

		return resultFooter;
	},


	/**
	 *
	 * !!! use this in the fulltextsearch app
	 * !!! use this in the fulltextsearch app
	 * !!! use this in the fulltextsearch app
	 */
	generateEntryTemplate: function () {

		var resultName = $('<div>', {class: 'files_result_file'});
		resultName.append($('<div>', {
			id: 'title',
			class: 'files_result_title'
		}));
		resultName.append($('<div>', {
			id: 'extract',
			class: 'files_result_extract'
		}));

		var resultEntry = $('<div>', {class: 'files_result'});
		resultEntry.append($('<div>', {class: 'files_div_checkbox'}));
		resultEntry.append($('<div>', {class: 'files_div_thumb files_result_div'}));

		resultEntry.append($('<div>', {class: 'files_result_div files_div_name'}).append(resultName));

		var resultMore = $('<span>', {class: 'icon icon-more'});

		// <a class="action action-menu permanent" href="#" data-action="menu" data-original-title=""
		// title=""> <span class="icon icon-more"></span><span
		// class="hidden-visually">Actions</span></a>
		resultEntry.append(
			$('<div>', {class: 'files_result_div files_result_item files_div_more'}).append(resultMore));

		resultEntry.append(
			$('<div>', {class: 'files_result_div files_result_item files_div_size'}));

		var resultModified = $('<div>', {class: 'files_result_div files_result_item files_div_modified'});
		resultModified.append($('<div>', {id: 'modified'}));
		resultModified.append($('<div>', {id: 'info'}));
		resultEntry.append(resultModified);

		return $('<div>').append(resultEntry);
	},


	onEntryGenerated: function (divEntry, entry) {

		var divFile = divEntry.find('.files_result');
		divFile.attr({
			'data-id': entry.id,
			'data-type': entry.info.type,
			'data-size': entry.info.size,
			'data-file': entry.info.file,
			'data-mime': entry.info.mime,
			'data-mtime': entry.info.mtime,
			'data-etag': entry.info.etag,
			'data-permissions': entry.info.permissions,
			'data-path': entry.info.path
		});

		var mtime = parseInt(entry.info.mtime, 10) * 1000;
		var size = OC.Util.humanFileSize(parseInt(entry.info.size, 10), true);
		var thumb = '/index.php/core/preview?fileId=' + entry.id + '&x=32&y=32&forceIcon=0&c=' +
			entry.info.etag;
		divEntry.find('.files_div_size').text(size);
		divEntry.find('#modified').text(OC.Util.relativeModifiedDate(mtime));
		divEntry.find('.files_div_thumb').css('background-image', 'url("' + thumb + '")');
	},


	onEntrySelected: function (divEntry, event) {

		var resultEntry = divEntry.find('.files_result');
		this.fileActions.currentFile = resultEntry;

		var path = resultEntry.attr('data-path');
		var filename = resultEntry.attr('data-file');
		var mime = resultEntry.attr('data-mime');
		var type = resultEntry.attr('data-type');
		var permissions = resultEntry.attr('data-permissions');

		if (type !== 'file') {
			return false;
		}

		if (event && (event.ctrlKey || event.which === 2 || event.button === 4)) {
			return false;
		}

		var action = this.fileActions.getDefault(mime, type, permissions);

		if (action) {

			event.preventDefault();
			window.FileActions.currentFile = this.fileActions.currentFile;
			action(filename, {
				$file: resultEntry,
				fileList: this,
				fileActions: this.fileActions,
				dir: path
			});

			return true;
		}

		return false;
		// if (event && (event.ctrlKey || event.which === 2 || event.button === 4)) {
		// 	window.open('/remote.php/webdav' + path + '/' + filename);
		// } else {
		// 	window.open('/remote.php/webdav' + path + '/' + filename, '_self');
		// }


	},


	getModelForFile: function () {
		return null;
	},


	changeDirectory: function (targetDir, changeUrl, force, fileId) {
		var self = this;
		var currentDir = '/';
		targetDir = targetDir || '/';
		if (!force && currentDir === targetDir) {
			return;
		}
		this._setCurrentDir(targetDir, changeUrl, fileId);

		// discard finished uploads list, we'll get it through a regular reload
		this._uploads = {};
		this.reload().then(function (success) {
			if (!success) {
				self.changeDirectory(currentDir, true);
			}
		});
	},

	onSearchRequest: function (data) {
		if (data.options.files_within_dir === '1') {
			var url = new URL(window.location.href);
			data.options.files_within_dir = url.searchParams.get("dir");
		}
	},


	onResultDisplayed: function () {
		elements.old_files.fadeOut(150, function () {
			elements.search_result.fadeIn(150);
		});
	},


	onResultClose: function () {
		elements.search_result.fadeOut(150, function () {
			elements.old_files.fadeIn(150);
		});
	},


	onSearchReset: function () {
		elements.search_result.fadeOut(150, function () {
			elements.old_files.fadeIn(150);
		});
	}

};


OCA.Files.FullTextSearch = FullTextSearch;

$(document).ready(function () {
	OCA.Files.FullTextSearch = new FullTextSearch();
});



