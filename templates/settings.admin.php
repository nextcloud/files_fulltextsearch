<?php

use OCA\Files_FullNextSearch\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_NAME, 'admin.elements');
Util::addScript(Application::APP_NAME, 'admin.settings');
Util::addScript(Application::APP_NAME, 'admin');

Util::addStyle(Application::APP_NAME, 'admin');

?>

<div id="files" class="section" style="display: none;">
	<h2><?php p($l->t('Files')) ?></h2>

	<div class="div-table">

		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<span class="leftcol">External Files:</span>
				<br/>
				<em>Index the content of external files.</em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="files_external" value="1"/>
			</div>
		</div>

	</div>


</div>