/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


/** global: OCA */

var fullTextSearch = OCA.FullTextSearch.api;


var elements = {
	old_files: null,
	search_result: null,
	current_dir: '',
	link_attributes: ''
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

		fullTextSearch.initFullTextSearch('files', t('files_fulltextsearch', 'files'), self);

		this._initFileActions();
	},

	_initFileActions: function () {
		//
		// this.fileActions = new OCA.Files.FileActions();
		// this.fileActions.registerDefaultActions();
		// this.fileActions.merge(window.FileActions);
		// this.fileActions.merge(OCA.Files.fileActions)

		this.fileActions = OCA.Files.fileActions;
		// this._onActionsUpdated = _.bind(this._onActionsUpdated, this);
		// OCA.Files.fileActions.on('setDefault.app-files', this._onActionsUpdated);
		// OCA.Files.fileActions.on('registerAction.app-files', this._onActionsUpdated);
		// window.FileActions.on('setDefault.app-files', this._onActionsUpdated);
		// window.FileActions.on('registerAction.app-files', this._onActionsUpdated);

		// this.fileActions.actions.all.Rename = undefined;
		// this.fileActions.actions.all.MoveCopy = undefined;
		// this.fileActions.actions.all.Copy = undefined;
		// this.fileActions.actions.all.Delete = undefined;

		this.fileActions.registerAction({
			name: 'GoToFolder',
			displayName: t('files_fulltextsearch', 'Go to folder'),
			mime: 'file',
			order: -50,
			iconClass: 'icon-folder',
			permissions: OC.PERMISSION_NONE,
			actionHandler: function (filename, context) {
				window.open(
					OC.generateUrl('/apps/files/?dir=' + context.dir + '&scrollto=' + context.filename));
			}
		});

		this.fileActions.registerAction({
			name: 'OpenFolder',
			displayName: t('files_fulltextsearch', 'Open folder'),
			mime: 'dir',
			order: -50,
			iconClass: 'icon-folder',
			permissions: OC.PERMISSION_NONE,
			actionHandler: function (filename, context) {
				window.open('/apps/files/?dir=' + context.dir + context.filename);
			}
		});
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

		var resultMore = $('<a>', {
			class: 'action action-menu permanent',
			href: '#',
			'data-action': 'menu',
			'data-original-title': ''
		}).append($('<span>', {
			id: 'more',
			class: 'icon icon-more'
		}).html('&nbsp;'));

		// <a class="action action-menu permanent" href="#" data-action="menu" data-original-title=""
		// title=""> <span class="icon icon-more"></span><span
		// class="hidden-visually">Actions</span></a>
		resultEntry.append(
			$('<div>', {
				class: 'files_result_div files_result_item files_div_more'
			}).append(resultMore));

		resultEntry.append(
			$('<div>', {class: 'files_result_div files_result_item files_div_size'}));

		var resultModified = $('<div>',
			{class: 'files_result_div files_result_item files_div_modified'});
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
			'data-link': entry.link,
			'data-size': entry.info.size,
			'data-file': entry.info.file,
			'data-mime': entry.info.mime,
			'data-mtime': entry.info.mtime,
			'data-etag': entry.info.etag,
			'data-permissions': entry.info.permissions,
			'data-dir': entry.info.dir
		});

		var mtime = parseInt(entry.info.mtime, 10) * 1000;
		var size = OC.Util.humanFileSize(parseInt(entry.info.size, 10), true);
		var thumb = OC.generateUrl('/apps/theming/img/core/filetypes/folder.svg?v=3');
		if (entry.info.type !== 'dir') {
			thumb = OC.generateUrl('/core/preview?fileId=' + entry.id + '&x=32&y=32&forceIcon=0&c=' +
				entry.info.etag);
		}
		divEntry.find('.files_div_size').text(size);
		divEntry.find('#modified').text(OC.Util.relativeModifiedDate(mtime));
		divEntry.find('.files_div_thumb').css('background-image', 'url("' + thumb + '")');
	},


	onEntrySelected: function (divEntry, event) {

		var resultEntry = divEntry.find('.files_result');
		this.fileActions.currentFile = resultEntry.children('.files_div_more');

		var dir = resultEntry.attr('data-dir');
		var filename = resultEntry.attr('data-file');
		var link = resultEntry.attr('data-link');
		var mime = resultEntry.attr('data-mime');
		var type = resultEntry.attr('data-type');
		var permissions = resultEntry.attr('data-permissions');

		// if (type !== 'file') {
		// 	return false;
		// }

		if (event.target.id === 'more') {
			this.hackFileActions(resultEntry);
			// this.fileActions._showMenu(filename, this.hackFileActions(divEntry));
			return true;
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
				fileName: filename,
				fileList: this,
				fileActions: this.fileActions,
				dir: dir
			});

			return true;
		}

		if (elements.link_attributes !== '') {
			window.open(link, elements.link_attributes, false);
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

	getCurrentDirectory: function () {
		return this.fileActions.currentFile.parent('.files_result').attr('data-dir');
	},

	getDownloadUrl: function (files, dir, isDir) {
		var file = this.fileActions.currentFile.parent('.files_result').attr('data-file');
		var path = this.fileActions.currentFile.parent('.files_result').attr('data-dir');
		return OCA.Files.Files.getDownloadUrl(file, path, isDir);
	},

	showFileBusyState: function (files, state) {
	},


	/**
	 * Copies a file to a given target folder.
	 *
	 * @param fileNames array of file names to copy
	 * @param targetPath absolute target path
	 * @param callback to call when copy is finished with success
	 * @param dir the dir path where fileNames are located (optionnal, will take current folder if
	 *     undefined)
	 */
	copy: function (fileNames, targetPath, callback, dir) {
		var self = this;
		var filesToNotify = [];
		var count = 0;

		dir = typeof dir === 'string' ? dir : this.getCurrentDirectory();
		if (dir.charAt(dir.length - 1) !== '/') {
			dir += '/';
		}
		var target = OC.basename(targetPath);
		if (!_.isArray(fileNames)) {
			fileNames = [fileNames];
		}
		_.each(fileNames, function (fileName) {
			var $tr = self.findFileEl(fileName);
			self.showFileBusyState($tr, true);
			if (targetPath.charAt(targetPath.length - 1) !== '/') {
				// make sure we move the files into the target dir,
				// not overwrite it
				targetPath = targetPath + '/';
			}
			self.filesClient.copy(dir + fileName, targetPath + fileName)
				.done(function () {
					filesToNotify.push(fileName);

					// if still viewing the same directory
					if (OC.joinPaths(self.getCurrentDirectory(), '/') === dir) {
						// recalculate folder size
						var oldFile = self.findFileEl(target);
						var newFile = self.findFileEl(fileName);
						var oldSize = oldFile.data('size');
						var newSize = oldSize + newFile.data('size');
						oldFile.data('size', newSize);
						oldFile.find('td.filesize').text(OC.Util.humanFileSize(newSize));
					}
				})
				.fail(function (status) {
					if (status === 412) {
						// TODO: some day here we should invoke the conflict dialog
						OC.Notification.show(t('files', 'Could not copy "{file}", target exists',
							{file: fileName}), {type: 'error'}
						);
					} else {
						OC.Notification.show(t('files', 'Could not copy "{file}"',
							{file: fileName}), {type: 'error'}
						);
					}
				})
				.always(function () {
					self.showFileBusyState($tr, false);
					count++;

					/**
					 * We only show the notifications once the last file has been copied
					 */
					if (count === fileNames.length) {
						// Remove leading and ending /
						if (targetPath.slice(0, 1) === '/') {
							targetPath = targetPath.slice(1, targetPath.length);
						}
						if (targetPath.slice(-1) === '/') {
							targetPath = targetPath.slice(0, -1);
						}

						if (filesToNotify.length > 0) {
							// Since there's no visual indication that the files were copied, let's send
							// some notifications !
							if (filesToNotify.length === 1) {
								OC.Notification.show(t('files', 'Copied {origin} inside {destination}',
									{
										origin: filesToNotify[0],
										destination: targetPath
									}
								), {timeout: 10});
							} else if (filesToNotify.length > 0 && filesToNotify.length < 3) {
								OC.Notification.show(t('files', 'Copied {origin} inside {destination}',
									{
										origin: filesToNotify.join(', '),
										destination: targetPath
									}
								), {timeout: 10});
							} else {
								OC.Notification.show(t('files',
									'Copied {origin} and {nbfiles} other files inside {destination}',
									{
										origin: filesToNotify[0],
										nbfiles: filesToNotify.length - 1,
										destination: targetPath
									}
								), {timeout: 10});
							}
						}
					}
				});
		});

		if (callback) {
			callback();
		}
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


	_setCurrentDir: function (targetDir, changeUrl, fileId) {
		window.open(OC.generateUrl('/apps/files?dir=' + targetDir, '_self'));
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
	},


	// Hacky way.
	// fit the FileActionMenu to the div.
	hackFileActions: function (div) {
		var menu = new OCA.Files.FileActionsMenu();

		div.append(menu.$el);
		menu.$el.on('afterHide', function () {
			// context.$file.removeClass('mouseOver');
			// $trigger.removeClass('open');
			// menu.remove();
		});

		var fileInfoModel = new OCA.Files.FileInfoModel(this.elementToFile(div));
		menu.show({
			fileActions: this.fileActions,
			fileList: this,
			fileInfoModel: fileInfoModel,
			$file: div,
			filename: div.attr('data-file'),
			dir: div.attr('data-dir')
		});

		div.find('.fileActionsMenu').addClass('files_force_action_menu');
	},


	/**
	 * Returns the file data from a given file element.
	 * @param $el file tr element
	 * @return file data
	 */
	elementToFile: function (div) {
		var data = {
			id: parseInt(div.attr('data-id'), 10),
			name: div.attr('data-file'),
			mimetype: div.attr('data-mime'),
			mtime: parseInt(div.attr('data-mtime'), 10),
			type: div.attr('data-type'),
			etag: div.attr('data-etag'),
			permissions: parseInt(div.attr('data-permissions'), 10),
			hasPreview: div.attr('data-has-preview') === 'true',
			isEncrypted: div.attr('data-e2eencrypted') === 'true'
		};
		var size = div.attr('data-size');
		if (size) {
			data.size = parseInt(size, 10);
		}
		var icon = div.attr('data-icon');
		if (icon) {
			data.icon = icon;
		}
		var mountType = div.attr('data-mounttype');
		if (mountType) {
			data.mountType = mountType;
		}
		var dir = div.attr('data-dir');
		if (dir) {
			data.dir = dir;
		}
		return data;
	}

};


OCA.Files.FullTextSearch = FullTextSearch;

$(document).ready(function () {
	OCA.Files.FullTextSearch = new FullTextSearch();
});



