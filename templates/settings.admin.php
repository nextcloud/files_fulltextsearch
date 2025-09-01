<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Files_FullTextSearch\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, 'admin.elements');
Util::addScript(Application::APP_ID, 'admin.settings');
Util::addScript(Application::APP_ID, 'admin');


?>

<div id="files" class="section" style="display: none;">
	<h2><?php p($l->t('Files')) ?></h2>

	<h3 class="hsub"><?php p($l->t('Sources')); ?></h3>
	<div class="div-table">
		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol"><?php p($l->t('Local Files')); ?>:</span>
				<br/>
				<em><?php p($l->t('Index the content of local files.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_local" value="1"/>
			</div>
		</div>

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol"><?php p($l->t('External Files')); ?>:</span>
				<br/>
				<em><?php p($l->t('Index the content of external files.')); ?></em>
			</div>
			<div class="div-table-col">
				<select id="files_external">
					<option value="0"><?php p($l->t('Index path only')); ?></option>
					<option value="1"><?php p($l->t('Index path and content')); ?></option>
					<option value="2"><?php p($l->t('Do not index path nor content')); ?></option>
				</select>
			</div>
		</div>

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol"><?php p($l->t('Group Folders')); ?>:</span>
				<br/>
				<em><?php p($l->t('Index the content of group folders.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_group_folders" value="1"/>
			</div>
		</div>

		<!--<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">Encrypted Files:</span>
				<br/>
				<em>Index the content of encrypted files.</em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_encrypted" value="1"/>
			</div>
		</div>

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">Federate Shares:</span>
				<br/>
				<em>Index the content of federated shares.</em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_federated" value="1"/>
			</div>
		</div>
	</div>-->

		<h3 class="hsub"><?php p($l->t('Types')); ?></h3>
		<div class="div-table">
			<div class="div-table-row">
				<div class="div-table-col div-table-col-left">
					<span class="leftcol"><?php p($l->t('Maximum file size')); ?>:</span>
					<br/>
					<em><?php p($l->t('Maximum file size to index (in Mb).')); ?></em>
				</div>
				<div class="div-table-col">
					<input type="text" class="small" id="files_size" value=""/>
				</div>
			</div>

			<div class="div-table-row">
				<div class="div-table-col div-table-col-left">
					<span class="leftcol"><?php p($l->t('Extract PDF')); ?>:</span>
					<br/>
					<em><?php p($l->t('Index the content of PDF files.')); ?></em>
				</div>
				<div class="div-table-col">
					<input type="checkbox" id="files_pdf" value="1"/>
				</div>
			</div>

			<div class="div-table-row">
				<div class="div-table-col div-table-col-left">
					<span class="leftcol"><?php p($l->t('Extract Office')); ?>:</span>
					<br/>
					<em><?php p($l->t('Index the content of office files.')); ?></em>
				</div>
				<div class="div-table-col">
					<input type="checkbox" id="files_office" value="1"/>
				</div>
			</div>
		</div>

		<h3 class="hsub"><?php p($l->t('Results')); ?></h3>
		<div class="div-table">
			<div class="div-table-row">
				<div class="div-table-col div-table-col-left">
					<span class="leftcol"><?php p($l->t('Open Files')); ?>:</span>
					<br/>
					<em><?php p($l->t('Directly from search results.')); ?></em>
				</div>
				<div class="div-table-col">
					<input type="checkbox" id="files_open_result_directly" value="1"/>
				</div>
			</div>
		</div>

	</div>
</div>
