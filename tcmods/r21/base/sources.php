<?php 
/*
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
 *	UI-design/JS code by: 	Andrea Coiutti (aka ACX)
 *	PHP/JS code by:			Simone De Gregori (aka Orion)
 * 
 *	file:					sources.php
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 *  TC (Tim Curtis) 2014-11-30, r1.3 beta1
 *  - remove trailing ! in 1st content line causing code to be grayed out in editor 
 *
 *	TC (Tim Curtis) 2014-12-23, r1.3
 *	- remove btn-block on $_mounts so l/r margins will be present in html 
 *	- shovel & broom
 *
 *	TC (Tim Curtis) 2015-02-25, r1.6
 *	- remove cifs noatime option, not supported on kernel 3.12.26+ / MPD 0.19.1, causes mount to fail with errors
 *
 *	TC (Tim Curtis) 2015-04-29, r1.8
 *	- streamlined format for source button
 *	- updated $_title wording for source.html
 *
 *	TC (Tim Curtis) 2015-05-30, r1.9
 *	- Streamline layout
 *
 *	TC (Tim Curtis) 2015-06-26, r2.0
 *	- change width of $_mounts button from 220px to 240px
 *
 */
 
// common include
include('inc/connection.php');
playerSession('open',$db,'',''); 
?>

<?php
// handle (reset)
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	// tell worker to write new MPD config
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		$_SESSION['w_queue'] = "sourcecfgman";
		$_SESSION['w_queueargs']  = 'sourcecfgreset';
		$_SESSION['w_active'] = 1;
		// set UI notify
		$_SESSION['notify']['title'] = 'Auto.nas modified';
		$_SESSION['notify']['msg'] = 'Remount shares in progress...';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Job failed';
		$_SESSION['notify']['msg'] = 'Background worker is busy';
		session_write_close();
	}
	unset($_POST);
}

if (isset($_POST['updatempd'])) {
	if ($mpd) {
		session_start();
		sendMpdCommand($mpd,'update');
		$_SESSION['notify']['title'] = 'Database update';
		$_SESSION['notify']['msg'] = 'MPD database update initiated...';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Error';
		$_SESSION['notify']['msg'] = 'Cannot connect to MPD';
		session_write_close();
	}
}

// handle POST
if(isset($_POST['mount']) && !empty($_POST['mount'])) {
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
		if ($_POST['mount']['type'] == 'cifs') {
			$_POST['mount']['options'] = "cache=strict,ro,dir_mode=0777,file_mode=0777";
		} else {
			$_POST['mount']['options'] = "nfsvers=3,ro,noatime";
		}
	}
	// activate worker
	if (isset($_POST['delete']) && $_POST['delete'] == 1) {
		// delete an existing entry
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'sourcecfg';
			$_POST['mount']['action'] = 'delete';
			$_SESSION['w_queueargs'] = $_POST;
			$_SESSION['w_active'] = 1;
			// set UI notify
			$_SESSION['notify']['title'] = 'Mount point deleted';
			$_SESSION['notify']['msg'] = 'MPD database update initiated...';
			session_write_close();
		} else {
			session_start();
			$_SESSION['notify']['title'] = 'Job failed';
			$_SESSION['notify']['msg'] = 'Background worker is busy';
			session_write_close();
		}
	} else {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'sourcecfg';
			$_SESSION['w_queueargs']  = $_POST;
			$_SESSION['w_active'] = 1;
			// set UI notify
			$_SESSION['notify']['title'] = 'Mount point modified';
			$_SESSION['notify']['msg'] = 'MPD database update initiated...';
			session_write_close();
		} else {
			session_start();
			$_SESSION['notify']['title'] = 'Job failed';
			$_SESSION['notify']['msg'] = 'Background worker is busy';
			session_write_close();
		} 
	}
}
	
// handle manual config
// rel 1.0 autoFS 
/*
if(isset($_POST['sourceconf']) && !empty($_POST['sourceconf'])) {
	// tell worker to write new MPD config
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		$_SESSION['w_queue'] = "sourcecfgman";
		$_SESSION['w_queueargs'] = $_POST['sourceconf'];
		$_SESSION['w_active'] = 1;
		// set UI notify
		$_SESSION['notify']['title'] = 'auto.nas modified';
		$_SESSION['notify']['msg'] = 'remount shares in progress...';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Job Failed';
		$_SESSION['notify']['msg'] = 'background worker is busy.';
		session_write_close();
	}
} 
*/

// wait for worker output if $_SESSION['w_active'] = 1
waitWorker(5,'sources');

$dbh = cfgdb_connect($db);
$source = cfgdb_read('cfg_source',$dbh);
$dbh = null;
// set normal config template
$tpl = "sources.html";
// unlock session files
playerSession('unlock',$db,'','');
foreach ($source as $mp) {
	if (wrk_checkStrSysfile('/proc/mounts',$mp['name']) ) {
		$icon = "<i class='icon-ok green sx'></i>";
	} else {
		$icon = "<i class='icon-remove red sx'></i>";
	}
	// TC (Tim Curtis) 2014-12-23: remove btn-block so l/r margins will be present 
	// TC (Tim Curtis) 2015-04-29: streamlined button format for small screens
	// TC (Tim Curtis) 2015-06-26: change width from 220px to 240px
	$_mounts .= "<p><a href=\"sources.php?p=edit&id=".$mp['id']."\" class='btn btn-large' style='width: 240px;'> ".$icon." ".$mp['name']." (".$mp['address'].") </a></p>";
	//$_mounts .= "<p><a href=\"sources.php?p=edit&id=".$mp['id']."\" class='btn btn-large'> ".$icon." NAS/".$mp['name']."&nbsp;&nbsp;&nbsp;&nbsp;//".$mp['address']."/".$mp['remotedir']." </a></p>";
}
?>

<?php
$sezione = basename(__FILE__, '.php');
include('_header.php'); 
?>

<!-- 
TC (Tim Curtis) 2014-11-30
- remove trailing ! in 1st content line causing code to be grayed out in editor 
-->
<!-- content -->
<?php
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
	} else {
		$_title = 'Configure new source';
		$_hide = 'hide';
		$_hideerror = 'hide';
		$_action = 'add';
		$_source_select['type'] .= "<option value=\"cifs\">SMB/CIFS</option>\n";	
		$_source_select['type'] .= "<option value=\"nfs\">NFS</option>\n";	
	}
	$tpl = 'source.html';
} 
//debug($_POST);
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
?>
<!-- content -->

<?php include('_footer.php'); ?>
