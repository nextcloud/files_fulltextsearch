<?php
/**
 * Files_FullNextSearch - Index your files
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
 */

//use OCA\Files_FullNextSearch\AppInfo\Application;
//use OCP\Util;
//
//Util::addScript(Application::APP_NAME, 'admin.elements');
//Util::addScript(Application::APP_NAME, 'admin.settings');
//Util::addScript(Application::APP_NAME, 'admin');
//
//Util::addStyle(Application::APP_NAME, 'admin');

?>
<div>

	<div class="div-table">

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">Within local files:</span>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_local" checked value="1"/>
			</div>
		</div>

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">Within external files:</span>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_external" checked value="1"/>
			</div>
		</div>

		<div class="div-table-row">f
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">Filter by file extension:</span>
			</div>
			<div class="div-table-col">
				<input type="text" id="files_extension" value=""/>
			</div>
		</div>

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">Limit to current folder:</span>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_withindir" value="1"/>
			</div>
		</div>

	</div>


</div>