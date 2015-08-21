<?php
/**
 *  This Program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3, or (at your option)
 *  any later version.
 *
 *  This Program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with TsunAMP; see the file COPYING.  If not, see
 *  <http://www.gnu.org/licenses/>.
 *
 *	PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 *	Tsunamp Team
 *	http://www.tsunamp.com
 *
 * Rewrite by Tim Curtis and Andreas Goetz
 */

require_once dirname(__FILE__) . '/inc/connection.php';
require_once dirname(__FILE__) . '/inc/worker.php';

// open player session
playerSession('open',$db,'','');

session_start();

// handle (reset)
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	// tell worker to write new MPD config
	if (workerPushTask('sourcecfgman', 'sourcecfgreset')) {
		uiSetNotification('Auto.nas modified', 'Remount shares in progress...');
	}
	else {
		uiSetNotification('Job failed', 'Background worker is busy');
	}
	unset($_POST);
}

if (isset($_POST['updatempd'])) {
	if ($mpd) {
		execMpdCommand($mpd,'update');
		uiSetNotification('Database update', 'MPD database update initiated...');
	}
	else {
		uiSetNotification('Error', 'Cannot connect to MPD');
	}
}

// handle POST
if (isset($_POST['mount']) && !empty($_POST['mount'])) {
	// convert slashes for remotedir path
	$_POST['mount']['remotedir'] = str_replace('\\', '/', $_POST['mount']['remotedir']);

	if ($_POST['mount']['wsize'] == '') {
		$_POST['mount']['wsize'] = 8096;
	}

	if ($_POST['mount']['rsize'] == '') {
		$_POST['mount']['rsize'] = 8048;
	}

	// TC (Tim Curtis) 2015-02-25: remove cifs noatime option, not supported on kernel 3.12.26+ / MPD 0.19.1, causes mount to fail with errors
	if ($_POST['mount']['options'] == '') {
		$_POST['mount']['options'] = ($_POST['mount']['type'] == 'cifs')
			? "cache=strict,ro,dir_mode=0777,file_mode=0777"
			: "nfsvers=3,ro,noatime";
	}

	// activate worker
	if (isset($_POST['delete']) && $_POST['delete'] == 1) {
		// delete an existing entry
		$_POST['mount']['action'] = 'delete';
		if (workerPushTask('sourcecfg', $_POST)) {
			uiSetNotification('Mount point deleted', 'MPD database update initiated...');
		}
		else {
			uiSetNotification('Job failed', 'Background worker is busy');
		}
	}
	else {
		if (workerPushTask('sourcecfg', $_POST)) {
			uiSetNotification('Mount point modified', 'MPD database update initiated...');
		}
		else {
			uiSetNotification('Job failed', 'Background worker is busy');
		}
	}
}

session_write_close();

// wait for worker output if $_SESSION['w_active'] = 1
waitWorker(5, 'sources');

$dbh = cfgdb_connect($db);
$source = cfgdb_read('cfg_source',$dbh);
$dbh = null;

// unlock session files
playerSession('unlock',$db,'','');

$_mounts = '';
foreach ($source as $mp) {
	$icon = wrk_checkStrSysfile('/proc/mounts',$mp['name'])
		? "<i class='icon-ok green sx'></i>"
		: "<i class='icon-remove red sx'></i>";
	$_mounts .= "<p><a href=\"sources.php?p=edit&id=".$mp['id']."\" class='btn btn-large' style='width: 240px;'> ".$icon." ".$mp['name']." (".$mp['address'].") </a></p>";
}

$tpl = "sources.html";

if (isset($_GET['p']) && !empty($_GET['p'])) {
	if (isset($_GET['id']) && !empty($_GET['id'])) {
		$_id = $_GET['id'];
		foreach ($source as $mount) {
			if ($mount['id'] == $_id) {
				$_name = $mount['name'];
				$_address = $mount['address'];
				$_remotedir = $mount['remotedir'];
				$_username = $mount['username'];
				$_password = $mount['password'];
				$_rsize = $mount['rsize'];
				$_wsize = $mount['wsize'];
				// mount type select
				$_source_select['type'] .= "<option value=\"cifs\" ".(($mount['type'] == 'cifs') ? "selected" : "")." >SMB/CIFS</option>\n";
				$_source_select['type'] .= "<option value=\"nfs\" ".(($mount['type'] == 'nfs') ? "selected" : "")." >NFS</option>\n";
				$_charset = $mount['charset'];
				$_options = $mount['options'];
				$_error = $mount['error'];
				if (empty($_error)) {
					$_hideerror = 'hide';
				}
			}
		}
		// TC (Tim Curtis) 2015-04-29: update title wording
		$_title = 'Edit source';
		$_action = 'edit';
	}
	else {
		$_title = 'Configure new source';
		$_hide = 'hide';
		$_hideerror = 'hide';
		$_action = 'add';
		$_source_select['type'] .= "<option value=\"cifs\">SMB/CIFS</option>\n";
		$_source_select['type'] .= "<option value=\"nfs\">NFS</option>\n";
	}
	$tpl = 'source.html';
}

$sezione = basename(__FILE__, '.php');
include('_header.php');
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
include('_footer.php');
