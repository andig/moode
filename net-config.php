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

// handle POST (reset)
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	$eth0 = "iface eth0 inet dhcp\n";
	$value = array('ssid' => '', 'encryption' => '', 'password' => '');
	$dbh = cfgdb_connect($db);
	cfgdb_update('cfg_wifisec',$dbh,'',$value);
	$wifisec = cfgdb_read('cfg_wifisec',$dbh);
	$dbh = null;
	$_POST['eth0']['dhcp'] = 'true';
	$_POST['eth0']['ip'] = '';
	$_POST['eth0']['netmask'] = '';
	$_POST['eth0']['gw'] = '';
	$_POST['eth0']['dns1'] = '';
	$_POST['eth0']['dns2'] = '';
}

// handle POST
if (isset($_POST) && !empty($_POST)) {
	$dbh  = cfgdb_connect($db);
	// eth0
	if (isset($_POST['eth0']['dhcp']) && isset($_POST['eth0']['ip'])) {
		if ($_POST['eth0']['dhcp'] == 'true') {
			$_POST['eth0']['dhcp'] = 'true';
			$_POST['eth0']['ip'] = '';
			$_POST['eth0']['netmask'] = '';
			$_POST['eth0']['gw'] = '';
			$_POST['eth0']['dns1'] = '';
			$_POST['eth0']['dns2'] = '';
		}
		else {
			$_POST['eth0']['dhcp'] = 'false';
		}

		$value = array('name' => 'eth0',
			'dhcp' => $_POST['eth0']['dhcp'],
			'ip' => $_POST['eth0']['ip'],
			'netmask' => $_POST['eth0']['netmask'],
			'gw' => $_POST['eth0']['gw'],
			'dns1' => $_POST['eth0']['dns1'],
			'dns2' => $_POST['eth0']['dns2'] );

		cfgdb_update('cfg_lan',$dbh,'',$value);
		$net = cfgdb_read('cfg_lan',$dbh);

		// format new config string for eth0
		if ($_POST['eth0']['dhcp'] == 'true' ) {
			$eth0 = "\nauto eth0\niface eth0 inet dhcp\n";
		}
		else {
			$eth0 = "\nauto eth0\niface eth0 inet static\n";
			$eth0 .= "address ".$_POST['eth0']['ip']."\n";
			$eth0 .= "netmask ".$_POST['eth0']['netmask']."\n";
			$eth0 .= "gateway ".$_POST['eth0']['gw']."\n";
			if (isset($_POST['eth0']['dns1']) && !empty($_POST['eth0']['dns1'])) {
				$eth0 .= "nameserver ".$_POST['eth0']['dns1']."\n";
			}
			if (isset($_POST['eth0']['dns2']) && !empty($_POST['eth0']['dns2'])) {
				$eth0 .= "nameserver ".$_POST['eth0']['dns2']."\n";
			}
		}

		$wlan0 = "\n";
	}

	// wlan0
	if (isset($_POST['wifisec']['ssid']) && !empty($_POST['wifisec']['ssid']) && $_POST['wifisec']['password'] && !empty($_POST['wifisec']['password'])) {
		$value = array('ssid' => $_POST['wifisec']['ssid'], 'encryption' => $_POST['wifisec']['encryption'], 'password' => $_POST['wifisec']['password']);
		cfgdb_update('cfg_wifisec',$dbh,'',$value);
		$wifisec = cfgdb_read('cfg_wifisec',$dbh);

		// format new config string for wlan0
		$wlan0 = "\n";
		$wlan0 .= "auto wlan0\n";
		$wlan0 .= "iface wlan0 inet dhcp\n";
		$wlan0 .= "wireless-power off\n";
		if ($_POST['wifisec']['encryption'] == 'wpa') {
			$wlan0 .= "wpa-ssid ".$_POST['wifisec']['ssid']."\n"; // TC (Tim Curtis) 2015-08-DD: place holder, add quotes around ssid
			$wlan0 .= "wpa-psk ".$_POST['wifisec']['password']."\n";
		}
		else {
			$wlan0 .= "wireless-essid ".$_POST['wifisec']['ssid']."\n"; // TC (Tim Curtis) 2015-08-DD: place holder, add quotes around ssid
			if ($_POST['wifisec']['encryption'] == 'wep') {
				$wlan0 .= "wireless-key ".bin2hex($_POST['wifisec']['password'])."\n";
			}
			else {
				$wlan0 .= "wireless-mode managed\n";
			}
		}

	   $eth0 = "\nauto eth0\niface eth0 inet dhcp\n";

	} // end wlan0

	// handle manual config
	if (isset($_POST['netconf']) && !empty($_POST['netconf'])) {
		// tell worker to write new MPD config
		if (workerQueueTask("netcfgman", $_POST['netconf'])) {
			uiNotify('Network config', 'Network config modified');
		}
		else {
			uiNotify('Job failed', 'Background worker is busy');
		}
	}

	// close DB handle
	$dbh = null;

	// create job for background worker
	if (workerQueueTask('netcfg', $wlan0.$eth0)) {
		uiNotify('Network config', (isset($_GET['reset']) && $_GET['reset'] == 1)
			? 'Network config reset'
			: 'Network config modified'
		);
	}
	else {
		uiNotify('Job failed', 'Background worker is busy');
	}
	// unlock session file
	playerSession('unlock');
}

// wait for worker output if $_SESSION['w_active'] = 1
waitWorker(1);

$dbh = cfgdb_connect($db);
$net = cfgdb_read('cfg_lan',$dbh);
$wifisec = cfgdb_read('cfg_wifisec',$dbh);
$dbh = null;

// eth0
if (isset($_SESSION['netconf']['eth0']) && !empty($_SESSION['netconf']['eth0'])) {
	// TC (Tim Curtis) 2014-08-23
	// changed class from "alert alert-info" to "help-block", added <br>
	// TC (Tim Curtis) 2015-04-29: new layout
	$_eth0 .= $_SESSION['netconf']['eth0']['ip'];
}
else {
	$_eth0 .= "Not used";
}

$_int0dhcp .= "<option value=\"true\" ".((isset($net[0]['dhcp']) && $net[0]['dhcp']=="true") ? "selected" : "")." >enabled (Auto)</option>\n";
$_int0dhcp .= "<option value=\"false\" ".((isset($net[0]['dhcp']) && $net[0]['dhcp']=="false") ? "selected" : "")." >disabled (Static)</option>\n";
$_int0 = $net[0];

// wlan0
if (isset($_SESSION['netconf']['wlan0']) && !empty($_SESSION['netconf']['wlan0'])) {
	// TC (Tim Curtis) 2014-08-23
	// changed class from "alert alert-info" to "help-block", added <br>
	// TC (Tim Curtis) 2015-04-29: new layout
	$_wlan0 .= $_SESSION['netconf']['wlan0']['ip'];
}
else {
	$_wlan0 .= "Not used";
}

$_wlan0ssid = $wifisec[0]['ssid'];

// TC (Tim Curtis) 2015-04-29: reorder so WPA/WPA2 is first
$_wlan0security .= "<option value=\"wpa\"".(($wifisec[0]['security'] == 'wpa') ? "selected" : "").">WPA/WPA2 Personal</option>\n";
$_wlan0security .= "<option value=\"wep\"".(($wifisec[0]['security'] == 'wep') ? "selected" : "").">WEP</option>\n";
$_wlan0security .= "<option value=\"none\"".(($wifisec[0]['security'] == 'none') ? "selected" : "").">No security</option>\n";

$tpl = "net-config.html";


// unlock session files
playerSession('unlock',$db,'','');

$sezione = basename(__FILE__, '.php');
include('_header.php');
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
include('_footer.php');
