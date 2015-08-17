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

$TCMODS_REL = "r21"; // Current release

require_once dirname(__FILE__) . '/inc/connection.php';


playerSession('open',$db,'','');
playerSession('unlock',$db,'','');

if (isset($_POST['syscmd'])) {
	switch ($_POST['syscmd']) {
		// TC (Tim Curtis) 2014-08-23: process theme change requests
		// TC (Tim Curtis) 2015-04-29: remove session[notify] since this is now handled in notify.js
		// TC (Tim Curtis) 2015-04-29: streamline theme change code
		// TC (Tim Curtis) 2015-04-29: add 6 new theme colors
		// TC (Tim Curtis) 2015-05-30: streamline theme change code to a single stacked case
		case 'alizarin':
		case 'amethyst':
		case 'bluejeans':
		case 'carrot':
		case 'emerald':
		case 'fallenleaf':
		case 'grass':
		case 'herb':
		case 'lavender':
		case 'river':
		case 'rose':
		case 'turquoise':
			if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
				session_start();
				$_SESSION['w_queue'] = "themechange";
				$_SESSION['w_queueargs'] = $_POST['syscmd'];
				$_SESSION['w_active'] = 1;
				playerSession('unlock');
			} else {
				echo "background worker is busy";
			}
			break;
	}
}

// TC (Tim Curtis) 2014-08-23: i2s driver support for G2 Labs BerryNOS DAC (same drivers as for hifiberry dac)
// TC (Tim Curtis) 2014-08-23: edit message title and text
// TC (Tim Curtis) 2015-01-27: move i2s processing from within switch syscmd above to here
// TC (Tim Curtis) 2015-01-27: update list of drivers and associated modules
// TC (Tim Curtis) 2015-01-27: add code to store selection in db
// TC (Tim Curtis) 2015-02-25: i2s driver select handler
// TC (Tim Curtis) 2015-04-29: add RaspyPlay4 to i2s select list
// TC (Tim Curtis) 2015-04-29: add update btn check
if (isset($_POST['update_i2s_device'])) {
	if (isset($_POST['i2s']) && $_POST['i2s'] != $_SESSION['i2s']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'i2sdriver';
			$_SESSION['w_queueargs'] = $_POST['i2s'];
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "I2S device has been changed, REBOOT for setting to take effect.";
			$_SESSION['notify']['duration'] = 5; // secs
			// TC (Tim Curtis) 2015-03-21: Adjust message depending on selected device
			if ($_POST['i2s'] == "IQaudIO Pi-AMP+") {
				$_SESSION['notify']['msg'] = $_SESSION['notify']['msg']."<br><br>This device REQUIRES hardware volume control. After rebooting, set MPD Volume control to Hardware.";
			} else if ($_POST['i2s'] == "HiFiBerry DAC+" ||
				$_POST['i2s'] == "HiFiBerry Amp(Amp+)" ||
				$_POST['i2s'] == "IQaudIO Pi-DAC" ||
				$_POST['i2s'] == "IQaudIO Pi-DAC+" ||
				$_POST['i2s'] == "RaspyPlay4") {
				$_SESSION['notify']['msg'] = $_SESSION['notify']['msg']."<br><br>This device supports hardware volume control. After rebooting, optionally set MPD Volume control to Hardware.";
			}
			// TC (Tim Curtis) 2015-04-29: update cfg_engine table, moved from player_wrk.php, fixes field not updating when page echos back
			playerSession('write',$db,'i2s',$_POST['i2s']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
// TC (Tim Curtis) 2015-02-25: kernel select handler
// TC (Tim Curtis) 2015-06-26: use unique notify Title in update_kernel_version for Waitworker(1) test at end of script to allow the Notify message to appear
// TC (Tim Curtis) 2015-06-26: change notify message duration from 5 to 10 mins
if (isset($_POST['update_kernel_version'])) {
	if (isset($_POST['kernelver']) && $_POST['kernelver'] != $_SESSION['kernelver']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'kernelver';
			$_SESSION['w_queueargs'] = $_POST['kernelver'];
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Kernel change';
			$_SESSION['notify']['msg'] = "Version ".$_POST['kernelver']." install initiated...<br><br>The process can take 5+ minutes to<br>complete after which the CONNECTING<br>screen will appear and the system will<br>be POWERED OFF.";
			$_SESSION['notify']['duration'] = 600; // secs (10 mins)
			// TC (Tim Curtis) 2015-04-29: update cfg_engine table, added, fixes field not updating when page echos back
			playerSession('write',$db,'kernelver',$_POST['kernelver']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
// TC (Tim Curtis) 2015-04-29: timezone select handler
if (isset($_POST['update_time_zone'])) {
	if (isset($_POST['timezone']) && $_POST['timezone'] != $_SESSION['timezone']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'timezone';
			$_SESSION['w_queueargs'] = $_POST['timezone'];
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "Timezone ".$_POST['timezone']." has been set.";
			$_SESSION['notify']['duration'] = 4; // secs
			// TC (Tim Curtis) 2015-04-29: update cfg_engine table, moved from player_wrk.php, fixes field not updating when page echos back
			playerSession('write',$db,'timezone',$_POST['timezone']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_latency_setting'])) {
	if (isset($_POST['orionprofile']) && $_POST['orionprofile'] != $_SESSION['orionprofile']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'orionprofile';
			$_SESSION['w_queueargs'] = $_POST['orionprofile'];
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = 'Kernel latency setting has been changed to: '.$_POST['orionprofile'].', REBOOT for setting to take effect.';
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
		if ($_SESSION['w_lock'] != 1) {
			session_start();
			$_SESSION['w_active'] = 1;
			playerSession('write',$db,'orionprofile',$_POST['orionprofile']);
			playerSession('unlock');
		} else {
			return "background worker busy";
		}
	}
}

if (isset($_POST['shairport']) && $_POST['shairport'] != $_SESSION['shairport']) {
	session_start();
	if ($_POST['shairport'] == 1 OR $_POST['shairport'] == 0) {
		playerSession('write',$db,'shairport',$_POST['shairport']);
	}
	if ($_POST['shairport'] == 1) {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'Airplay receiver enabled, REBOOT for setting to take effect.';
		$_SESSION['notify']['duration'] = 4; // secs
	} else {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'Airplay receiver disabled, REBOOT for setting to take effect.';
		$_SESSION['notify']['duration'] = 4; // secs
	}
	playerSession('unlock');
}

if (isset($_POST['upnpmpdcli']) && $_POST['upnpmpdcli'] != $_SESSION['upnpmpdcli']) {
	session_start();
	if ($_POST['upnpmpdcli'] == 1 OR $_POST['upnpmpdcli'] == 0) {
		playerSession('write',$db,'upnpmpdcli',$_POST['upnpmpdcli']);
	}
	if ($_POST['upnpmpdcli'] == 1) {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'UPnP renderer enabled, REBOOT for setting to take effect.';
		$_SESSION['notify']['duration'] = 4; // secs
	} else {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'UPnP renderer disabled, REBOOT for setting to take effect.';
		$_SESSION['notify']['duration'] = 4; // secs
	}
	playerSession('unlock');
}

if (isset($_POST['djmount']) && $_POST['djmount'] != $_SESSION['djmount']) {
	session_start();
	if ($_POST['djmount'] == 1 OR $_POST['djmount'] == 0) {
		playerSession('write',$db,'djmount',$_POST['djmount']);
	}
	if ($_POST['djmount'] == 1) {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'DLNA server enabled, REBOOT for setting to take effect.';
		$_SESSION['notify']['duration'] = 4; // secs
	} else {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'DLNA server disabled, REBOOT for setting to take effect.';
		$_SESSION['notify']['duration'] = 4; // secs
	}
	playerSession('unlock');
}

// TC (Tim Curtis) 2015-04-29: host and network service name change handlers
if (isset($_POST['update_host_name'])) {
	if (isset($_POST['host_name']) && $_POST['host_name'] != $_SESSION['host_name']) {
		session_start();
		if (preg_match("/[^A-Za-z0-9-]/", $_POST['host_name']) == 1) {
			$_SESSION['notify']['title'] = 'Invalid input';
			$_SESSION['notify']['msg'] = "Host name can only contain A-Z, a-z, 0-9 or hyphen (-).";
			$_SESSION['notify']['duration'] = 4; // secs
		} else {
			if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
				$_SESSION['w_queue'] = 'host_name';
				$_SESSION['w_queueargs'] = "\"".$_SESSION['host_name']."\" "."\"".$_POST['host_name']."\""; // "old":"new"
				$_SESSION['w_active'] = 1;
				$_SESSION['notify']['title'] = 'Setting change';
				$_SESSION['notify']['msg'] = "Host name has been changed, REBOOT for setting to take effect.";
				$_SESSION['notify']['duration'] = 4; // secs
				playerSession('write',$db,'host_name',$_POST['host_name']);
			} else {
				echo "background worker busy";
			}
		}
		playerSession('unlock');
	}
}
if (isset($_POST['update_browser_title'])) {
	if (isset($_POST['browser_title']) && $_POST['browser_title'] != $_SESSION['browser_title']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'browser_title';
			$_SESSION['w_queueargs'] = "\"".$_SESSION['browser_title']."\" "."\"".$_POST['browser_title']."\""; // "old":"new"
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "Browser title has been changed, REBOOT for setting to take effect.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('write',$db,'browser_title',$_POST['browser_title']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_airplay_name'])) {
	if (isset($_POST['airplay_name']) && $_POST['airplay_name'] != $_SESSION['airplay_name']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'airplay_name';
			$_SESSION['w_queueargs'] = "\"".$_SESSION['airplay_name']."\" "."\"".$_POST['airplay_name']."\""; // "old":"new"
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "Airplay receiver name has been changed, REBOOT for setting to take effect.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('write',$db,'airplay_name',$_POST['airplay_name']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_upnp_name'])) {
	if (isset($_POST['upnp_name']) && $_POST['upnp_name'] != $_SESSION['upnp_name']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'upnp_name';
			$_SESSION['w_queueargs'] = "\"".$_SESSION['upnp_name']."\" "."\"".$_POST['upnp_name']."\""; // "old":"new"
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "UPnP renderer name has been changed, REBOOT for setting to take effect.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('write',$db,'upnp_name',$_POST['upnp_name']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_dlna_name'])) {
	if (isset($_POST['dlna_name']) && $_POST['dlna_name'] != $_SESSION['dlna_name']) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'dlna_name';
			$_SESSION['w_queueargs'] = "\"".$_SESSION['dlna_name']."\" "."\"".$_POST['dlna_name']."\""; // "old":"new"
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "DLNA server name has been changed, REBOOT for setting to take effect.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('write',$db,'dlna_name',$_POST['dlna_name']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}

// TC (Tim Curtis) 2015-04-29: handle PCM volume change
if (isset($_POST['update_pcm_volume'])) {
	if (isset($_POST['pcm_volume'])) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'pcm_volume';
			$_SESSION['w_queueargs'] = $_POST['pcm_volume'];
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = "PCM volume has been set.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('write',$db,'pcm_volume',$_POST['pcm_volume']);
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}

// TC (Tim Curtis) 2015-05-30: handle log maintenance for system and play history logs
if (isset($_POST['update_clear_syslogs'])) {
	if ($_POST['clearsyslogs'] == 1) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'clearsyslogs';
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Log maintenance';
			$_SESSION['notify']['msg'] = "System logs have been cleared.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_clear_playhistory'])) {
	if ($_POST['clearplayhistory'] == 1) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'clearplayhistory';
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Log maintenance';
			$_SESSION['notify']['msg'] = "Playback history log hase been cleared.";
			$_SESSION['notify']['duration'] = 4; // secs
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}

// TC (Tim Curtis) 2015-07-31: expand sd card storage
if (isset($_POST['update_expand_sdcard'])) {
	if ($_POST['expandsdcard'] == 1) {
		if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
			session_start();
			$_SESSION['w_queue'] = 'expandsdcard';
			$_SESSION['w_active'] = 1;
			$_SESSION['notify']['title'] = 'Expand SD Card Storage';
			$_SESSION['notify']['msg'] = "Storage expansion request has been queued. REBOOT has been initiated.";
			$_SESSION['notify']['duration'] = 6; // secs
			playerSession('unlock');
		} else {
			echo "background worker busy";
		}
	}
}

// configure html select elements
// TC (Tim Curtis) 2015-01-27: add i2s driver list
// TC (Tim Curtis) 2015-02-25: filter driver list by kernel version(s)
// TC (Tim Curtis) 2015-03-21: add IQaudIO Pi-AMP+
// TC (Tim Curtis) 2015-04-29: add RaspyPlay4 and Durio Sound PRO
// TC (Tim Curtis) 2015-04-29: cleanup i2s if() logic
// TC (Tim Curtis) 2015-06-26: use getKernelVer()
// TC (Tim Curtis) 2015-06-26: add IQaudIO Pi-DigiAMP+ and Hifimediy ES9023
// TC (Tim Curtis) 2015-07-31: add Audiophonics I-Sabre DAC ES9023 TCXO
$kernelver = getKernelVer($_SESSION['kernelver']);
if ($kernelver == '3.18.5+' || $kernelver == '3.18.11+' || $kernelver == '3.18.14+') {
	$_i2s['i2s'] .= "<option value=\"I2S Off\" ".(($_SESSION['i2s'] == 'I2S Off') ? "selected" : "").">None</option>\n";
	$_i2s['i2s'] .= "<option value=\"Audiophonics I-Sabre DAC ES9023 TCXO\" ".(($_SESSION['i2s'] == 'Audiophonics I-Sabre DAC ES9023 TCXO') ? "selected" : "").">Audiophonics I-Sabre DAC ES9023 TCXO</option>\n";
	$_i2s['i2s'] .= "<option value=\"Durio Sound PRO\" ".(($_SESSION['i2s'] == 'Durio Sound PRO') ? "selected" : "").">Durio Sound PRO</option>\n";
	$_i2s['i2s'] .= "<option value=\"G2 Labs BerryNOS\" ".(($_SESSION['i2s'] == 'G2 Labs BerryNOS') ? "selected" : "").">G2 Labs BerryNOS</option>\n";
	$_i2s['i2s'] .= "<option value=\"G2 Labs BerryNOS Red\" ".(($_SESSION['i2s'] == 'G2 Labs BerryNOS Red') ? "selected" : "").">G2 Labs BerryNOS Red</option>\n";
	$_i2s['i2s'] .= "<option value=\"HiFiBerry Amp(Amp+)\" ".(($_SESSION['i2s'] == 'HiFiBerry Amp(Amp+)') ? "selected" : "").">HiFiBerry Amp(Amp+)</option>\n";
	$_i2s['i2s'] .= "<option value=\"HiFiBerry DAC\" ".(($_SESSION['i2s'] == 'HiFiBerry DAC') ? "selected" : "").">HiFiBerry DAC</option>\n";
	$_i2s['i2s'] .= "<option value=\"HiFiBerry DAC+\" ".(($_SESSION['i2s'] == 'HiFiBerry DAC+') ? "selected" : "").">HiFiBerry DAC+</option>\n";
	$_i2s['i2s'] .= "<option value=\"HiFiBerry Digi(Digi+)\" ".(($_SESSION['i2s'] == 'HiFiBerry Digi(Digi+)') ? "selected" : "").">HiFiBerry Digi(Digi+)</option>\n";
	$_i2s['i2s'] .= "<option value=\"Hifimediy ES9023\" ".(($_SESSION['i2s'] == 'Hifimediy ES9023') ? "selected" : "").">Hifimediy ES9023</option>\n";
	$_i2s['i2s'] .= "<option value=\"IQaudIO Pi-AMP+\" ".(($_SESSION['i2s'] == 'IQaudIO Pi-AMP+') ? "selected" : "").">IQaudIO Pi-AMP+</option>\n";
	$_i2s['i2s'] .= "<option value=\"IQaudIO Pi-DAC\" ".(($_SESSION['i2s'] == 'IQaudIO Pi-DAC') ? "selected" : "").">IQaudIO Pi-DAC</option>\n";
	$_i2s['i2s'] .= "<option value=\"IQaudIO Pi-DAC+\" ".(($_SESSION['i2s'] == 'IQaudIO Pi-DAC+') ? "selected" : "").">IQaudIO Pi-DAC+</option>\n";
	$_i2s['i2s'] .= "<option value=\"IQaudIO Pi-DigiAMP+\" ".(($_SESSION['i2s'] == 'IQaudIO Pi-DigiAMP+') ? "selected" : "").">IQaudIO Pi-DigiAMP+</option>\n";
	$_i2s['i2s'] .= "<option value=\"RaspyPlay4\" ".(($_SESSION['i2s'] == 'RaspyPlay4') ? "selected" : "").">RaspyPlay4</option>\n";
	$_i2s['i2s'] .= "<option value=\"RPi DAC\" ".(($_SESSION['i2s'] == 'RPi DAC') ? "selected" : "").">RPi DAC</option>\n";
	$_i2s['i2s'] .= "<option value=\"Generic\" ".(($_SESSION['i2s'] == 'Generic') ? "selected" : "").">Generic</option>\n";
} else {
	// TC (Tim Curtis) 2015-06-26: drop support for DAC list under 3.10.36+ and 3.12.26+, kernels not in use by any users
	$_i2s['i2s'] .= "<option value=\"I2S Off\" ".(($_SESSION['i2s'] == 'I2S Off') ? "selected" : "").">None</option>\n";
}

// TC (Tim Curtis) 2015-04-29: add host and network service names
$_system_select['host_name'] = $_SESSION['host_name'];
$_system_select['browser_title'] = $_SESSION['browser_title'];
$_system_select['airplay_name'] = $_SESSION['airplay_name'];
$_system_select['upnp_name'] = $_SESSION['upnp_name'];
$_system_select['dlna_name'] = $_SESSION['dlna_name'];

// TC (Tim Curtis) 2015-04-29: add PCM (alsamixer) volume
// TC (Tim Curtis) 2015-06-26: updated logic
if ($_SESSION['pcm_volume'] == 'none') {
	$_pcm_volume = '';
	$_pcm_volume_readonly = 'readonly';
	$_pcm_volume_hide = 'hide';
	$_pcm_volume_msg = "<span class=\"help-block help-block-margin\">PCM volume mixer not detected for attached audio device</span>";
} else {
	// TC (Tim Curtis) 2015-06-26: get current volume setting, requires www-data user in visudo
	// TC (Tim Curtis) 2015-06-26: set simple mixer name based on kernel version and i2s vs USB
	$mixername = getMixerName($kernelver, $_SESSION['i2s']);
	$cmd = "sudo /var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh get-pcmvol ".$mixername;

	$rtn = sysCmd($cmd);
	$_pcm_volume = str_replace("%", "", $rtn[0]);

	if (isset($_POST['pcm_volume']) && $_pcm_volume != $_POST['pcm_volume']) { // player_wrk has not processed the change yet
		$_pcm_volume = 	$_POST['pcm_volume'];
	}

	$_pcm_volume_readonly = '';
	$_pcm_volume_hide = '';
	$_pcm_volume_msg = '';
}

// TC (Tim Curtis) 2015-02-25: add kernel select list
// TC (Tim Curtis) 2015-06-26: add 3.18.14 and 3.18.11 kernels to select list
if ($_SESSION['procarch'] == "armv7l") { // Pi-2
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.14-v7+\" ".(($_SESSION['kernelver'] == '3.18.14-v7+') ? "selected" : "").">3.18.14-v7+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.11-v7+\" ".(($_SESSION['kernelver'] == '3.18.11-v7+') ? "selected" : "").">3.18.11-v7+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.5-v7+\" ".(($_SESSION['kernelver'] == '3.18.5-v7+') ? "selected" : "").">3.18.5-v7+</option>\n";
} else if ($_SESSION['procarch'] == "armv6l") { // Pi-1 and Pi-B+
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.14+\" ".(($_SESSION['kernelver'] == '3.18.14+') ? "selected" : "").">3.18.14+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.11+\" ".(($_SESSION['kernelver'] == '3.18.11+') ? "selected" : "").">3.18.11+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.5+\" ".(($_SESSION['kernelver'] == '3.18.5+') ? "selected" : "").">3.18.5+</option>\n";
	// TC (Tim Curtis) 2015-06-26: drop support for these, not in use by any users
	//$_linux_kernel['kernelver'] .= "<option value=\"3.12.26+\" ".(($_SESSION['kernelver'] == '3.12.26+') ? "selected" : "").">3.12.26+</option>\n";
	//$_linux_kernel['kernelver'] .= "<option value=\"3.10.36+\" ".(($_SESSION['kernelver'] == '3.10.36+') ? "selected" : "").">3.10.36+</option>\n";
} else {
	$_linux_kernel['kernelver'] .= "<option value=\"Unknown Arch\" "."selected".">None</option>\n";
}

// kernel tweak profiles
$_system_select['orionprofile'] .= "<option value=\"Default\" ".(($_SESSION['orionprofile'] == 'Default') ? "selected" : "").">Default</option>\n";
$_system_select['orionprofile'] .= "<option value=\"ACX\" ".(($_SESSION['orionprofile'] == 'ACX') ? "selected" : "").">ACX</option>\n";
$_system_select['orionprofile'] .= "<option value=\"Orion\" ".(($_SESSION['orionprofile'] == 'Orion') ? "selected" : "").">Orion</option>\n";
// airplay receiver
$_system_select['shairport1'] .= "<input type=\"radio\" name=\"shairport\" id=\"toggleshairport1\" value=\"1\" ".(($_SESSION['shairport'] == 1) ? "checked=\"checked\"" : "").">\n";
$_system_select['shairport0'] .= "<input type=\"radio\" name=\"shairport\" id=\"toggleshairport2\" value=\"0\" ".(($_SESSION['shairport'] == 0) ? "checked=\"checked\"" : "").">\n";
// dlna server
$_system_select['djmount1'] .= "<input type=\"radio\" name=\"djmount\" id=\"toggledjmount1\" value=\"1\" ".(($_SESSION['djmount'] == 1) ? "checked=\"checked\"" : "").">\n";
$_system_select['djmount0'] .= "<input type=\"radio\" name=\"djmount\" id=\"toggledjmount2\" value=\"0\" ".(($_SESSION['djmount'] == 0) ? "checked=\"checked\"" : "").">\n";
// upnp renderer
$_system_select['upnpmpdcli1'] .= "<input type=\"radio\" name=\"upnpmpdcli\" id=\"toggleupnpmpdcli1\" value=\"1\" ".(($_SESSION['upnpmpdcli'] == 1) ? "checked=\"checked\"" : "").">\n";
$_system_select['upnpmpdcli0'] .= "<input type=\"radio\" name=\"upnpmpdcli\" id=\"toggleupnpmpdcli2\" value=\"0\" ".(($_SESSION['upnpmpdcli'] == 0) ? "checked=\"checked\"" : "").">\n";

// TC (Tim Curtis) 2015-05-30: add system and play history log maintenence

// clear syslogs
$_system_select['clearsyslogs1'] .= "<input type=\"radio\" name=\"clearsyslogs\" id=\"toggleclearsyslogs1\" value=\"1\" ".">\n";
$_system_select['clearsyslogs0'] .= "<input type=\"radio\" name=\"clearsyslogs\" id=\"toggleclearsyslogs2\" value=\"0\" "."checked=\"checked\"".">\n";
// clear playback history
$_system_select['clearplayhistory1'] .= "<input type=\"radio\" name=\"clearplayhistory\" id=\"toggleclearplayhistory1\" value=\"1\" ".">\n";
$_system_select['clearplayhistory0'] .= "<input type=\"radio\" name=\"clearplayhistory\" id=\"toggleclearplayhistory2\" value=\"0\" "."checked=\"checked\"".">\n";
// TC (Tim Curtis) 2015-07-31: expand sd card storage
$_system_select['expandsdcard1'] .= "<input type=\"radio\" name=\"expandsdcard\" id=\"toggleexpandsdcard1\" value=\"1\" ".">\n";
$_system_select['expandsdcard0'] .= "<input type=\"radio\" name=\"expandsdcard\" id=\"toggleexpandsdcard2\" value=\"0\" "."checked=\"checked\"".">\n";

// TC (Tim Curtis) 2015-04-29: timezones
$_timezone['timezone'] .= "<option value=\"Africa/Abidjan\" ".(($_SESSION['timezone'] == 'Africa/Abidjan') ? "selected" : "").">Africa/Abidjan</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Accra\" ".(($_SESSION['timezone'] == 'Africa/Accra') ? "selected" : "").">Africa/Accra</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Addis_Ababa\" ".(($_SESSION['timezone'] == 'Africa/Addis_Ababa') ? "selected" : "").">Africa/Addis_Ababa</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Algiers\" ".(($_SESSION['timezone'] == 'Africa/Algiers') ? "selected" : "").">Africa/Algiers</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Asmara\" ".(($_SESSION['timezone'] == 'Africa/Asmara') ? "selected" : "").">Africa/Asmara</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Asmera\" ".(($_SESSION['timezone'] == 'Africa/Asmera') ? "selected" : "").">Africa/Asmera</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Bamako\" ".(($_SESSION['timezone'] == 'Africa/Bamako') ? "selected" : "").">Africa/Bamako</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Bangui\" ".(($_SESSION['timezone'] == 'Africa/Bangui') ? "selected" : "").">Africa/Bangui</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Banjul\" ".(($_SESSION['timezone'] == 'Africa/Banjul') ? "selected" : "").">Africa/Banjul</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Bissau\" ".(($_SESSION['timezone'] == 'Africa/Bissau') ? "selected" : "").">Africa/Bissau</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Blantyre\" ".(($_SESSION['timezone'] == 'Africa/Blantyre') ? "selected" : "").">Africa/Blantyre</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Brazzaville\" ".(($_SESSION['timezone'] == 'Africa/Brazzaville') ? "selected" : "").">Africa/Brazzaville</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Bujumbura\" ".(($_SESSION['timezone'] == 'Africa/Bujumbura') ? "selected" : "").">Africa/Bujumbura</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Cairo\" ".(($_SESSION['timezone'] == 'Africa/Cairo') ? "selected" : "").">Africa/Cairo</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Casablanca\" ".(($_SESSION['timezone'] == 'Africa/Casablanca') ? "selected" : "").">Africa/Casablanca</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Ceuta\" ".(($_SESSION['timezone'] == 'Africa/Ceuta') ? "selected" : "").">Africa/Ceuta</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Conakry\" ".(($_SESSION['timezone'] == 'Africa/Conakry') ? "selected" : "").">Africa/Conakry</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Dakar\" ".(($_SESSION['timezone'] == 'Africa/Dakar') ? "selected" : "").">Africa/Dakar</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Dar_es_Salaam\" ".(($_SESSION['timezone'] == 'Africa/Dar_es_Salaam') ? "selected" : "").">Africa/Dar_es_Salaam</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Djibouti\" ".(($_SESSION['timezone'] == 'Africa/Djibouti') ? "selected" : "").">Africa/Djibouti</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Douala\" ".(($_SESSION['timezone'] == 'Africa/Douala') ? "selected" : "").">Africa/Douala</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/El_Aaiun\" ".(($_SESSION['timezone'] == 'Africa/El_Aaiun') ? "selected" : "").">Africa/El_Aaiun</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Freetown\" ".(($_SESSION['timezone'] == 'Africa/Freetown') ? "selected" : "").">Africa/Freetown</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Gaborone\" ".(($_SESSION['timezone'] == 'Africa/Gaborone') ? "selected" : "").">Africa/Gaborone</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Harare\" ".(($_SESSION['timezone'] == 'Africa/Harare') ? "selected" : "").">Africa/Harare</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Johannesburg\" ".(($_SESSION['timezone'] == 'Africa/Johannesburg') ? "selected" : "").">Africa/Johannesburg</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Juba\" ".(($_SESSION['timezone'] == 'Africa/Juba') ? "selected" : "").">Africa/Juba</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Kampala\" ".(($_SESSION['timezone'] == 'Africa/Kampala') ? "selected" : "").">Africa/Kampala</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Khartoum\" ".(($_SESSION['timezone'] == 'Africa/Khartoum') ? "selected" : "").">Africa/Khartoum</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Kigali\" ".(($_SESSION['timezone'] == 'Africa/Kigali') ? "selected" : "").">Africa/Kigali</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Kinshasa\" ".(($_SESSION['timezone'] == 'Africa/Kinshasa') ? "selected" : "").">Africa/Kinshasa</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Lagos\" ".(($_SESSION['timezone'] == 'Africa/Lagos') ? "selected" : "").">Africa/Lagos</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Libreville\" ".(($_SESSION['timezone'] == 'Africa/Libreville') ? "selected" : "").">Africa/Libreville</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Lome\" ".(($_SESSION['timezone'] == 'Africa/Lome') ? "selected" : "").">Africa/Lome</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Luanda\" ".(($_SESSION['timezone'] == 'Africa/Luanda') ? "selected" : "").">Africa/Luanda</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Lubumbashi\" ".(($_SESSION['timezone'] == 'Africa/Lubumbashi') ? "selected" : "").">Africa/Lubumbashi</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Lusaka\" ".(($_SESSION['timezone'] == 'Africa/Lusaka') ? "selected" : "").">Africa/Lusaka</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Malabo\" ".(($_SESSION['timezone'] == 'Africa/Malabo') ? "selected" : "").">Africa/Malabo</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Maputo\" ".(($_SESSION['timezone'] == 'Africa/Maputo') ? "selected" : "").">Africa/Maputo</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Maseru\" ".(($_SESSION['timezone'] == 'Africa/Maseru') ? "selected" : "").">Africa/Maseru</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Mbabane\" ".(($_SESSION['timezone'] == 'Africa/Mbabane') ? "selected" : "").">Africa/Mbabane</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Mogadishu\" ".(($_SESSION['timezone'] == 'Africa/Mogadishu') ? "selected" : "").">Africa/Mogadishu</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Monrovia\" ".(($_SESSION['timezone'] == 'Africa/Monrovia') ? "selected" : "").">Africa/Monrovia</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Nairobi\" ".(($_SESSION['timezone'] == 'Africa/Nairobi') ? "selected" : "").">Africa/Nairobi</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Ndjamena\" ".(($_SESSION['timezone'] == 'Africa/Ndjamena') ? "selected" : "").">Africa/Ndjamena</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Niamey\" ".(($_SESSION['timezone'] == 'Africa/Niamey') ? "selected" : "").">Africa/Niamey</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Nouakchott\" ".(($_SESSION['timezone'] == 'Africa/Nouakchott') ? "selected" : "").">Africa/Nouakchott</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Ouagadougou\" ".(($_SESSION['timezone'] == 'Africa/Ouagadougou') ? "selected" : "").">Africa/Ouagadougou</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Porto-Novo\" ".(($_SESSION['timezone'] == 'Africa/Porto-Novo') ? "selected" : "").">Africa/Porto-Novo</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Sao_Tome\" ".(($_SESSION['timezone'] == 'Africa/Sao_Tome') ? "selected" : "").">Africa/Sao_Tome</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Timbuktu\" ".(($_SESSION['timezone'] == 'Africa/Timbuktu') ? "selected" : "").">Africa/Timbuktu</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Tripoli\" ".(($_SESSION['timezone'] == 'Africa/Tripoli') ? "selected" : "").">Africa/Tripoli</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Tunis\" ".(($_SESSION['timezone'] == 'Africa/Tunis') ? "selected" : "").">Africa/Tunis</option>\n";
$_timezone['timezone'] .= "<option value=\"Africa/Windhoek\" ".(($_SESSION['timezone'] == 'Africa/Windhoek') ? "selected" : "").">Africa/Windhoek</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Adak\" ".(($_SESSION['timezone'] == 'America/Adak') ? "selected" : "").">America/Adak</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Anchorage\" ".(($_SESSION['timezone'] == 'America/Anchorage') ? "selected" : "").">America/Anchorage</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Anguilla\" ".(($_SESSION['timezone'] == 'America/Anguilla') ? "selected" : "").">America/Anguilla</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Antigua\" ".(($_SESSION['timezone'] == 'America/Antigua') ? "selected" : "").">America/Antigua</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Araguaina\" ".(($_SESSION['timezone'] == 'America/Araguaina') ? "selected" : "").">America/Araguaina</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Buenos_Aires\" ".(($_SESSION['timezone'] == 'America/Argentina/Buenos_Aires') ? "selected" : "").">America/Argentina/Buenos_Aires</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Catamarca\" ".(($_SESSION['timezone'] == 'America/Argentina/Catamarca') ? "selected" : "").">America/Argentina/Catamarca</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/ComodRivadavia\" ".(($_SESSION['timezone'] == 'America/Argentina/ComodRivadavia') ? "selected" : "").">America/Argentina/ComodRivadavia</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Cordoba\" ".(($_SESSION['timezone'] == 'America/Argentina/Cordoba') ? "selected" : "").">America/Argentina/Cordoba</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Jujuy\" ".(($_SESSION['timezone'] == 'America/Argentina/Jujuy') ? "selected" : "").">America/Argentina/Jujuy</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/La_Rioja\" ".(($_SESSION['timezone'] == 'America/Argentina/La_Rioja') ? "selected" : "").">America/Argentina/La_Rioja</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Mendoza\" ".(($_SESSION['timezone'] == 'America/Argentina/Mendoza') ? "selected" : "").">America/Argentina/Mendoza</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Rio_Gallegos\" ".(($_SESSION['timezone'] == 'America/Argentina/Rio_Gallegos') ? "selected" : "").">America/Argentina/Rio_Gallegos</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Salta\" ".(($_SESSION['timezone'] == 'America/Argentina/Salta') ? "selected" : "").">America/Argentina/Salta</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/San_Juan\" ".(($_SESSION['timezone'] == 'America/Argentina/San_Juan') ? "selected" : "").">America/Argentina/San_Juan</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/San_Luis\" ".(($_SESSION['timezone'] == 'America/Argentina/San_Luis') ? "selected" : "").">America/Argentina/San_Luis</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Tucuman\" ".(($_SESSION['timezone'] == 'America/Argentina/Tucuman') ? "selected" : "").">America/Argentina/Tucuman</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Argentina/Ushuaia\" ".(($_SESSION['timezone'] == 'America/Argentina/Ushuaia') ? "selected" : "").">America/Argentina/Ushuaia</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Aruba\" ".(($_SESSION['timezone'] == 'America/Aruba') ? "selected" : "").">America/Aruba</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Asuncion\" ".(($_SESSION['timezone'] == 'America/Asuncion') ? "selected" : "").">America/Asuncion</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Atikokan\" ".(($_SESSION['timezone'] == 'America/Atikokan') ? "selected" : "").">America/Atikokan</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Atka\" ".(($_SESSION['timezone'] == 'America/Atka') ? "selected" : "").">America/Atka</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Bahia\" ".(($_SESSION['timezone'] == 'America/Bahia') ? "selected" : "").">America/Bahia</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Bahia_Banderas\" ".(($_SESSION['timezone'] == 'America/Bahia_Banderas') ? "selected" : "").">America/Bahia_Banderas</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Barbados\" ".(($_SESSION['timezone'] == 'America/Barbados') ? "selected" : "").">America/Barbados</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Belem\" ".(($_SESSION['timezone'] == 'America/Belem') ? "selected" : "").">America/Belem</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Belize\" ".(($_SESSION['timezone'] == 'America/Belize') ? "selected" : "").">America/Belize</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Blanc-Sablon\" ".(($_SESSION['timezone'] == 'America/Blanc-Sablon') ? "selected" : "").">America/Blanc-Sablon</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Boa_Vista\" ".(($_SESSION['timezone'] == 'America/Boa_Vista') ? "selected" : "").">America/Boa_Vista</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Bogota\" ".(($_SESSION['timezone'] == 'America/Bogota') ? "selected" : "").">America/Bogota</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Boise\" ".(($_SESSION['timezone'] == 'America/Boise') ? "selected" : "").">America/Boise</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Buenos_Aires\" ".(($_SESSION['timezone'] == 'America/Buenos_Aires') ? "selected" : "").">America/Buenos_Aires</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Cambridge_Bay\" ".(($_SESSION['timezone'] == 'America/Cambridge_Bay') ? "selected" : "").">America/Cambridge_Bay</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Campo_Grande\" ".(($_SESSION['timezone'] == 'America/Campo_Grande') ? "selected" : "").">America/Campo_Grande</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Cancun\" ".(($_SESSION['timezone'] == 'America/Cancun') ? "selected" : "").">America/Cancun</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Caracas\" ".(($_SESSION['timezone'] == 'America/Caracas') ? "selected" : "").">America/Caracas</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Catamarca\" ".(($_SESSION['timezone'] == 'America/Catamarca') ? "selected" : "").">America/Catamarca</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Cayenne\" ".(($_SESSION['timezone'] == 'America/Cayenne') ? "selected" : "").">America/Cayenne</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Cayman\" ".(($_SESSION['timezone'] == 'America/Cayman') ? "selected" : "").">America/Cayman</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Chicago\" ".(($_SESSION['timezone'] == 'America/Chicago') ? "selected" : "").">America/Chicago</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Chihuahua\" ".(($_SESSION['timezone'] == 'America/Chihuahua') ? "selected" : "").">America/Chihuahua</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Coral_Harbour\" ".(($_SESSION['timezone'] == 'America/Coral_Harbour') ? "selected" : "").">America/Coral_Harbour</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Cordoba\" ".(($_SESSION['timezone'] == 'America/Cordoba') ? "selected" : "").">America/Cordoba</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Costa_Rica\" ".(($_SESSION['timezone'] == 'America/Costa_Rica') ? "selected" : "").">America/Costa_Rica</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Creston\" ".(($_SESSION['timezone'] == 'America/Creston') ? "selected" : "").">America/Creston</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Cuiaba\" ".(($_SESSION['timezone'] == 'America/Cuiaba') ? "selected" : "").">America/Cuiaba</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Curacao\" ".(($_SESSION['timezone'] == 'America/Curacao') ? "selected" : "").">America/Curacao</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Danmarkshavn\" ".(($_SESSION['timezone'] == 'America/Danmarkshavn') ? "selected" : "").">America/Danmarkshavn</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Dawson\" ".(($_SESSION['timezone'] == 'America/Dawson') ? "selected" : "").">America/Dawson</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Dawson_Creek\" ".(($_SESSION['timezone'] == 'America/Dawson_Creek') ? "selected" : "").">America/Dawson_Creek</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Denver\" ".(($_SESSION['timezone'] == 'America/Denver') ? "selected" : "").">America/Denver</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Detroit\" ".(($_SESSION['timezone'] == 'America/Detroit') ? "selected" : "").">America/Detroit</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Dominica\" ".(($_SESSION['timezone'] == 'America/Dominica') ? "selected" : "").">America/Dominica</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Edmonton\" ".(($_SESSION['timezone'] == 'America/Edmonton') ? "selected" : "").">America/Edmonton</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Eirunepe\" ".(($_SESSION['timezone'] == 'America/Eirunepe') ? "selected" : "").">America/Eirunepe</option>\n";
$_timezone['timezone'] .= "<option value=\"America/El_Salvador\" ".(($_SESSION['timezone'] == 'America/El_Salvador') ? "selected" : "").">America/El_Salvador</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Ensenada\" ".(($_SESSION['timezone'] == 'America/Ensenada') ? "selected" : "").">America/Ensenada</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Fortaleza\" ".(($_SESSION['timezone'] == 'America/Fortaleza') ? "selected" : "").">America/Fortaleza</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Fort_Wayne\" ".(($_SESSION['timezone'] == 'America/Fort_Wayne') ? "selected" : "").">America/Fort_Wayne</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Glace_Bay\" ".(($_SESSION['timezone'] == 'America/Glace_Bay') ? "selected" : "").">America/Glace_Bay</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Godthab\" ".(($_SESSION['timezone'] == 'America/Godthab') ? "selected" : "").">America/Godthab</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Goose_Bay\" ".(($_SESSION['timezone'] == 'America/Goose_Bay') ? "selected" : "").">America/Goose_Bay</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Grand_Turk\" ".(($_SESSION['timezone'] == 'America/Grand_Turk') ? "selected" : "").">America/Grand_Turk</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Grenada\" ".(($_SESSION['timezone'] == 'America/Grenada') ? "selected" : "").">America/Grenada</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Guadeloupe\" ".(($_SESSION['timezone'] == 'America/Guadeloupe') ? "selected" : "").">America/Guadeloupe</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Guatemala\" ".(($_SESSION['timezone'] == 'America/Guatemala') ? "selected" : "").">America/Guatemala</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Guayaquil\" ".(($_SESSION['timezone'] == 'America/Guayaquil') ? "selected" : "").">America/Guayaquil</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Guyana\" ".(($_SESSION['timezone'] == 'America/Guyana') ? "selected" : "").">America/Guyana</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Halifax\" ".(($_SESSION['timezone'] == 'America/Halifax') ? "selected" : "").">America/Halifax</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Havana\" ".(($_SESSION['timezone'] == 'America/Havana') ? "selected" : "").">America/Havana</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Hermosillo\" ".(($_SESSION['timezone'] == 'America/Hermosillo') ? "selected" : "").">America/Hermosillo</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Indianapolis\" ".(($_SESSION['timezone'] == 'America/Indiana/Indianapolis') ? "selected" : "").">America/Indiana/Indianapolis</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Knox\" ".(($_SESSION['timezone'] == 'America/Indiana/Knox') ? "selected" : "").">America/Indiana/Knox</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Marengo\" ".(($_SESSION['timezone'] == 'America/Indiana/Marengo') ? "selected" : "").">America/Indiana/Marengo</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Petersburg\" ".(($_SESSION['timezone'] == 'America/Indiana/Petersburg') ? "selected" : "").">America/Indiana/Petersburg</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indianapolis\" ".(($_SESSION['timezone'] == 'America/Indianapolis') ? "selected" : "").">America/Indianapolis</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Tell_City\" ".(($_SESSION['timezone'] == 'America/Indiana/Tell_City') ? "selected" : "").">America/Indiana/Tell_City</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Vevay\" ".(($_SESSION['timezone'] == 'America/Indiana/Vevay') ? "selected" : "").">America/Indiana/Vevay</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Vincennes\" ".(($_SESSION['timezone'] == 'America/Indiana/Vincennes') ? "selected" : "").">America/Indiana/Vincennes</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Indiana/Winamac\" ".(($_SESSION['timezone'] == 'America/Indiana/Winamac') ? "selected" : "").">America/Indiana/Winamac</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Inuvik\" ".(($_SESSION['timezone'] == 'America/Inuvik') ? "selected" : "").">America/Inuvik</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Iqaluit\" ".(($_SESSION['timezone'] == 'America/Iqaluit') ? "selected" : "").">America/Iqaluit</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Jamaica\" ".(($_SESSION['timezone'] == 'America/Jamaica') ? "selected" : "").">America/Jamaica</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Jujuy\" ".(($_SESSION['timezone'] == 'America/Jujuy') ? "selected" : "").">America/Jujuy</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Juneau\" ".(($_SESSION['timezone'] == 'America/Juneau') ? "selected" : "").">America/Juneau</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Kentucky/Louisville\" ".(($_SESSION['timezone'] == 'America/Kentucky/Louisville') ? "selected" : "").">America/Kentucky/Louisville</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Kentucky/Monticello\" ".(($_SESSION['timezone'] == 'America/Kentucky/Monticello') ? "selected" : "").">America/Kentucky/Monticello</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Knox_IN\" ".(($_SESSION['timezone'] == 'America/Knox_IN') ? "selected" : "").">America/Knox_IN</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Kralendijk\" ".(($_SESSION['timezone'] == 'America/Kralendijk') ? "selected" : "").">America/Kralendijk</option>\n";
$_timezone['timezone'] .= "<option value=\"America/La_Paz\" ".(($_SESSION['timezone'] == 'America/La_Paz') ? "selected" : "").">America/La_Paz</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Lima\" ".(($_SESSION['timezone'] == 'America/Lima') ? "selected" : "").">America/Lima</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Los_Angeles\" ".(($_SESSION['timezone'] == 'America/Los_Angeles') ? "selected" : "").">America/Los_Angeles</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Louisville\" ".(($_SESSION['timezone'] == 'America/Louisville') ? "selected" : "").">America/Louisville</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Lower_Princes\" ".(($_SESSION['timezone'] == 'America/Lower_Princes') ? "selected" : "").">America/Lower_Princes</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Maceio\" ".(($_SESSION['timezone'] == 'America/Maceio') ? "selected" : "").">America/Maceio</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Managua\" ".(($_SESSION['timezone'] == 'America/Managua') ? "selected" : "").">America/Managua</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Manaus\" ".(($_SESSION['timezone'] == 'America/Manaus') ? "selected" : "").">America/Manaus</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Marigot\" ".(($_SESSION['timezone'] == 'America/Marigot') ? "selected" : "").">America/Marigot</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Martinique\" ".(($_SESSION['timezone'] == 'America/Martinique') ? "selected" : "").">America/Martinique</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Matamoros\" ".(($_SESSION['timezone'] == 'America/Matamoros') ? "selected" : "").">America/Matamoros</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Mazatlan\" ".(($_SESSION['timezone'] == 'America/Mazatlan') ? "selected" : "").">America/Mazatlan</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Mendoza\" ".(($_SESSION['timezone'] == 'America/Mendoza') ? "selected" : "").">America/Mendoza</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Menominee\" ".(($_SESSION['timezone'] == 'America/Menominee') ? "selected" : "").">America/Menominee</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Merida\" ".(($_SESSION['timezone'] == 'America/Merida') ? "selected" : "").">America/Merida</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Metlakatla\" ".(($_SESSION['timezone'] == 'America/Metlakatla') ? "selected" : "").">America/Metlakatla</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Mexico_City\" ".(($_SESSION['timezone'] == 'America/Mexico_City') ? "selected" : "").">America/Mexico_City</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Miquelon\" ".(($_SESSION['timezone'] == 'America/Miquelon') ? "selected" : "").">America/Miquelon</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Moncton\" ".(($_SESSION['timezone'] == 'America/Moncton') ? "selected" : "").">America/Moncton</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Monterrey\" ".(($_SESSION['timezone'] == 'America/Monterrey') ? "selected" : "").">America/Monterrey</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Montevideo\" ".(($_SESSION['timezone'] == 'America/Montevideo') ? "selected" : "").">America/Montevideo</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Montreal\" ".(($_SESSION['timezone'] == 'America/Montreal') ? "selected" : "").">America/Montreal</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Montserrat\" ".(($_SESSION['timezone'] == 'America/Montserrat') ? "selected" : "").">America/Montserrat</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Nassau\" ".(($_SESSION['timezone'] == 'America/Nassau') ? "selected" : "").">America/Nassau</option>\n";
$_timezone['timezone'] .= "<option value=\"America/New_York\" ".(($_SESSION['timezone'] == 'America/New_York') ? "selected" : "").">America/New_York</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Nipigon\" ".(($_SESSION['timezone'] == 'America/Nipigon') ? "selected" : "").">America/Nipigon</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Nome\" ".(($_SESSION['timezone'] == 'America/Nome') ? "selected" : "").">America/Nome</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Noronha\" ".(($_SESSION['timezone'] == 'America/Noronha') ? "selected" : "").">America/Noronha</option>\n";
$_timezone['timezone'] .= "<option value=\"America/North_Dakota/Beulah\" ".(($_SESSION['timezone'] == 'America/North_Dakota/Beulah') ? "selected" : "").">America/North_Dakota/Beulah</option>\n";
$_timezone['timezone'] .= "<option value=\"America/North_Dakota/Center\" ".(($_SESSION['timezone'] == 'America/North_Dakota/Center') ? "selected" : "").">America/North_Dakota/Center</option>\n";
$_timezone['timezone'] .= "<option value=\"America/North_Dakota/New_Salem\" ".(($_SESSION['timezone'] == 'America/North_Dakota/New_Salem') ? "selected" : "").">America/North_Dakota/New_Salem</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Ojinaga\" ".(($_SESSION['timezone'] == 'America/Ojinaga') ? "selected" : "").">America/Ojinaga</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Panama\" ".(($_SESSION['timezone'] == 'America/Panama') ? "selected" : "").">America/Panama</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Pangnirtung\" ".(($_SESSION['timezone'] == 'America/Pangnirtung') ? "selected" : "").">America/Pangnirtung</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Paramaribo\" ".(($_SESSION['timezone'] == 'America/Paramaribo') ? "selected" : "").">America/Paramaribo</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Phoenix\" ".(($_SESSION['timezone'] == 'America/Phoenix') ? "selected" : "").">America/Phoenix</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Port-au-Prince\" ".(($_SESSION['timezone'] == 'America/Port-au-Prince') ? "selected" : "").">America/Port-au-Prince</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Porto_Acre\" ".(($_SESSION['timezone'] == 'America/Porto_Acre') ? "selected" : "").">America/Porto_Acre</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Port_of_Spain\" ".(($_SESSION['timezone'] == 'America/Port_of_Spain') ? "selected" : "").">America/Port_of_Spain</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Porto_Velho\" ".(($_SESSION['timezone'] == 'America/Porto_Velho') ? "selected" : "").">America/Porto_Velho</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Puerto_Rico\" ".(($_SESSION['timezone'] == 'America/Puerto_Rico') ? "selected" : "").">America/Puerto_Rico</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Rainy_River\" ".(($_SESSION['timezone'] == 'America/Rainy_River') ? "selected" : "").">America/Rainy_River</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Rankin_Inlet\" ".(($_SESSION['timezone'] == 'America/Rankin_Inlet') ? "selected" : "").">America/Rankin_Inlet</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Recife\" ".(($_SESSION['timezone'] == 'America/Recife') ? "selected" : "").">America/Recife</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Regina\" ".(($_SESSION['timezone'] == 'America/Regina') ? "selected" : "").">America/Regina</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Resolute\" ".(($_SESSION['timezone'] == 'America/Resolute') ? "selected" : "").">America/Resolute</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Rio_Branco\" ".(($_SESSION['timezone'] == 'America/Rio_Branco') ? "selected" : "").">America/Rio_Branco</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Rosario\" ".(($_SESSION['timezone'] == 'America/Rosario') ? "selected" : "").">America/Rosario</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Santa_Isabel\" ".(($_SESSION['timezone'] == 'America/Santa_Isabel') ? "selected" : "").">America/Santa_Isabel</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Santarem\" ".(($_SESSION['timezone'] == 'America/Santarem') ? "selected" : "").">America/Santarem</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Santiago\" ".(($_SESSION['timezone'] == 'America/Santiago') ? "selected" : "").">America/Santiago</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Santo_Domingo\" ".(($_SESSION['timezone'] == 'America/Santo_Domingo') ? "selected" : "").">America/Santo_Domingo</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Sao_Paulo\" ".(($_SESSION['timezone'] == 'America/Sao_Paulo') ? "selected" : "").">America/Sao_Paulo</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Scoresbysund\" ".(($_SESSION['timezone'] == 'America/Scoresbysund') ? "selected" : "").">America/Scoresbysund</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Shiprock\" ".(($_SESSION['timezone'] == 'America/Shiprock') ? "selected" : "").">America/Shiprock</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Sitka\" ".(($_SESSION['timezone'] == 'America/Sitka') ? "selected" : "").">America/Sitka</option>\n";
$_timezone['timezone'] .= "<option value=\"America/St_Barthelemy\" ".(($_SESSION['timezone'] == 'America/St_Barthelemy') ? "selected" : "").">America/St_Barthelemy</option>\n";
$_timezone['timezone'] .= "<option value=\"America/St_Johns\" ".(($_SESSION['timezone'] == 'America/St_Johns') ? "selected" : "").">America/St_Johns</option>\n";
$_timezone['timezone'] .= "<option value=\"America/St_Kitts\" ".(($_SESSION['timezone'] == 'America/St_Kitts') ? "selected" : "").">America/St_Kitts</option>\n";
$_timezone['timezone'] .= "<option value=\"America/St_Lucia\" ".(($_SESSION['timezone'] == 'America/St_Lucia') ? "selected" : "").">America/St_Lucia</option>\n";
$_timezone['timezone'] .= "<option value=\"America/St_Thomas\" ".(($_SESSION['timezone'] == 'America/St_Thomas') ? "selected" : "").">America/St_Thomas</option>\n";
$_timezone['timezone'] .= "<option value=\"America/St_Vincent\" ".(($_SESSION['timezone'] == 'America/St_Vincent') ? "selected" : "").">America/St_Vincent</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Swift_Current\" ".(($_SESSION['timezone'] == 'America/Swift_Current') ? "selected" : "").">America/Swift_Current</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Tegucigalpa\" ".(($_SESSION['timezone'] == 'America/Tegucigalpa') ? "selected" : "").">America/Tegucigalpa</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Thule\" ".(($_SESSION['timezone'] == 'America/Thule') ? "selected" : "").">America/Thule</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Thunder_Bay\" ".(($_SESSION['timezone'] == 'America/Thunder_Bay') ? "selected" : "").">America/Thunder_Bay</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Tijuana\" ".(($_SESSION['timezone'] == 'America/Tijuana') ? "selected" : "").">America/Tijuana</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Toronto\" ".(($_SESSION['timezone'] == 'America/Toronto') ? "selected" : "").">America/Toronto</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Tortola\" ".(($_SESSION['timezone'] == 'America/Tortola') ? "selected" : "").">America/Tortola</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Vancouver\" ".(($_SESSION['timezone'] == 'America/Vancouver') ? "selected" : "").">America/Vancouver</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Virgin\" ".(($_SESSION['timezone'] == 'America/Virgin') ? "selected" : "").">America/Virgin</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Whitehorse\" ".(($_SESSION['timezone'] == 'America/Whitehorse') ? "selected" : "").">America/Whitehorse</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Winnipeg\" ".(($_SESSION['timezone'] == 'America/Winnipeg') ? "selected" : "").">America/Winnipeg</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Yakutat\" ".(($_SESSION['timezone'] == 'America/Yakutat') ? "selected" : "").">America/Yakutat</option>\n";
$_timezone['timezone'] .= "<option value=\"America/Yellowknife\" ".(($_SESSION['timezone'] == 'America/Yellowknife') ? "selected" : "").">America/Yellowknife</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Casey\" ".(($_SESSION['timezone'] == 'Antarctica/Casey') ? "selected" : "").">Antarctica/Casey</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Davis\" ".(($_SESSION['timezone'] == 'Antarctica/Davis') ? "selected" : "").">Antarctica/Davis</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/DumontDUrville\" ".(($_SESSION['timezone'] == 'Antarctica/DumontDUrville') ? "selected" : "").">Antarctica/DumontDUrville</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Macquarie\" ".(($_SESSION['timezone'] == 'Antarctica/Macquarie') ? "selected" : "").">Antarctica/Macquarie</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Mawson\" ".(($_SESSION['timezone'] == 'Antarctica/Mawson') ? "selected" : "").">Antarctica/Mawson</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/McMurdo\" ".(($_SESSION['timezone'] == 'Antarctica/McMurdo') ? "selected" : "").">Antarctica/McMurdo</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Palmer\" ".(($_SESSION['timezone'] == 'Antarctica/Palmer') ? "selected" : "").">Antarctica/Palmer</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Rothera\" ".(($_SESSION['timezone'] == 'Antarctica/Rothera') ? "selected" : "").">Antarctica/Rothera</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/South_Pole\" ".(($_SESSION['timezone'] == 'Antarctica/South_Pole') ? "selected" : "").">Antarctica/South_Pole</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Syowa\" ".(($_SESSION['timezone'] == 'Antarctica/Syowa') ? "selected" : "").">Antarctica/Syowa</option>\n";
$_timezone['timezone'] .= "<option value=\"Antarctica/Vostok\" ".(($_SESSION['timezone'] == 'Antarctica/Vostok') ? "selected" : "").">Antarctica/Vostok</option>\n";
$_timezone['timezone'] .= "<option value=\"Arctic/Longyearbyen\" ".(($_SESSION['timezone'] == 'Arctic/Longyearbyen') ? "selected" : "").">Arctic/Longyearbyen</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Aden\" ".(($_SESSION['timezone'] == 'Asia/Aden') ? "selected" : "").">Asia/Aden</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Almaty\" ".(($_SESSION['timezone'] == 'Asia/Almaty') ? "selected" : "").">Asia/Almaty</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Amman\" ".(($_SESSION['timezone'] == 'Asia/Amman') ? "selected" : "").">Asia/Amman</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Anadyr\" ".(($_SESSION['timezone'] == 'Asia/Anadyr') ? "selected" : "").">Asia/Anadyr</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Aqtau\" ".(($_SESSION['timezone'] == 'Asia/Aqtau') ? "selected" : "").">Asia/Aqtau</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Aqtobe\" ".(($_SESSION['timezone'] == 'Asia/Aqtobe') ? "selected" : "").">Asia/Aqtobe</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ashgabat\" ".(($_SESSION['timezone'] == 'Asia/Ashgabat') ? "selected" : "").">Asia/Ashgabat</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ashkhabad\" ".(($_SESSION['timezone'] == 'Asia/Ashkhabad') ? "selected" : "").">Asia/Ashkhabad</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Baghdad\" ".(($_SESSION['timezone'] == 'Asia/Baghdad') ? "selected" : "").">Asia/Baghdad</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Bahrain\" ".(($_SESSION['timezone'] == 'Asia/Bahrain') ? "selected" : "").">Asia/Bahrain</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Baku\" ".(($_SESSION['timezone'] == 'Asia/Baku') ? "selected" : "").">Asia/Baku</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Bangkok\" ".(($_SESSION['timezone'] == 'Asia/Bangkok') ? "selected" : "").">Asia/Bangkok</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Beirut\" ".(($_SESSION['timezone'] == 'Asia/Beirut') ? "selected" : "").">Asia/Beirut</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Bishkek\" ".(($_SESSION['timezone'] == 'Asia/Bishkek') ? "selected" : "").">Asia/Bishkek</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Brunei\" ".(($_SESSION['timezone'] == 'Asia/Brunei') ? "selected" : "").">Asia/Brunei</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Calcutta\" ".(($_SESSION['timezone'] == 'Asia/Calcutta') ? "selected" : "").">Asia/Calcutta</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Choibalsan\" ".(($_SESSION['timezone'] == 'Asia/Choibalsan') ? "selected" : "").">Asia/Choibalsan</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Chongqing\" ".(($_SESSION['timezone'] == 'Asia/Chongqing') ? "selected" : "").">Asia/Chongqing</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Chungking\" ".(($_SESSION['timezone'] == 'Asia/Chungking') ? "selected" : "").">Asia/Chungking</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Colombo\" ".(($_SESSION['timezone'] == 'Asia/Colombo') ? "selected" : "").">Asia/Colombo</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Dacca\" ".(($_SESSION['timezone'] == 'Asia/Dacca') ? "selected" : "").">Asia/Dacca</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Damascus\" ".(($_SESSION['timezone'] == 'Asia/Damascus') ? "selected" : "").">Asia/Damascus</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Dhaka\" ".(($_SESSION['timezone'] == 'Asia/Dhaka') ? "selected" : "").">Asia/Dhaka</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Dili\" ".(($_SESSION['timezone'] == 'Asia/Dili') ? "selected" : "").">Asia/Dili</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Dubai\" ".(($_SESSION['timezone'] == 'Asia/Dubai') ? "selected" : "").">Asia/Dubai</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Dushanbe\" ".(($_SESSION['timezone'] == 'Asia/Dushanbe') ? "selected" : "").">Asia/Dushanbe</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Gaza\" ".(($_SESSION['timezone'] == 'Asia/Gaza') ? "selected" : "").">Asia/Gaza</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Harbin\" ".(($_SESSION['timezone'] == 'Asia/Harbin') ? "selected" : "").">Asia/Harbin</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Hebron\" ".(($_SESSION['timezone'] == 'Asia/Hebron') ? "selected" : "").">Asia/Hebron</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ho_Chi_Minh\" ".(($_SESSION['timezone'] == 'Asia/Ho_Chi_Minh') ? "selected" : "").">Asia/Ho_Chi_Minh</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Hong_Kong\" ".(($_SESSION['timezone'] == 'Asia/Hong_Kong') ? "selected" : "").">Asia/Hong_Kong</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Hovd\" ".(($_SESSION['timezone'] == 'Asia/Hovd') ? "selected" : "").">Asia/Hovd</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Irkutsk\" ".(($_SESSION['timezone'] == 'Asia/Irkutsk') ? "selected" : "").">Asia/Irkutsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Istanbul\" ".(($_SESSION['timezone'] == 'Asia/Istanbul') ? "selected" : "").">Asia/Istanbul</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Jakarta\" ".(($_SESSION['timezone'] == 'Asia/Jakarta') ? "selected" : "").">Asia/Jakarta</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Jayapura\" ".(($_SESSION['timezone'] == 'Asia/Jayapura') ? "selected" : "").">Asia/Jayapura</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Jerusalem\" ".(($_SESSION['timezone'] == 'Asia/Jerusalem') ? "selected" : "").">Asia/Jerusalem</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kabul\" ".(($_SESSION['timezone'] == 'Asia/Kabul') ? "selected" : "").">Asia/Kabul</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kamchatka\" ".(($_SESSION['timezone'] == 'Asia/Kamchatka') ? "selected" : "").">Asia/Kamchatka</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Karachi\" ".(($_SESSION['timezone'] == 'Asia/Karachi') ? "selected" : "").">Asia/Karachi</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kashgar\" ".(($_SESSION['timezone'] == 'Asia/Kashgar') ? "selected" : "").">Asia/Kashgar</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kathmandu\" ".(($_SESSION['timezone'] == 'Asia/Kathmandu') ? "selected" : "").">Asia/Kathmandu</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Katmandu\" ".(($_SESSION['timezone'] == 'Asia/Katmandu') ? "selected" : "").">Asia/Katmandu</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Khandyga\" ".(($_SESSION['timezone'] == 'Asia/Khandyga') ? "selected" : "").">Asia/Khandyga</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kolkata\" ".(($_SESSION['timezone'] == 'Asia/Kolkata') ? "selected" : "").">Asia/Kolkata</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Krasnoyarsk\" ".(($_SESSION['timezone'] == 'Asia/Krasnoyarsk') ? "selected" : "").">Asia/Krasnoyarsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kuala_Lumpur\" ".(($_SESSION['timezone'] == 'Asia/Kuala_Lumpur') ? "selected" : "").">Asia/Kuala_Lumpur</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kuching\" ".(($_SESSION['timezone'] == 'Asia/Kuching') ? "selected" : "").">Asia/Kuching</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Kuwait\" ".(($_SESSION['timezone'] == 'Asia/Kuwait') ? "selected" : "").">Asia/Kuwait</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Macao\" ".(($_SESSION['timezone'] == 'Asia/Macao') ? "selected" : "").">Asia/Macao</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Macau\" ".(($_SESSION['timezone'] == 'Asia/Macau') ? "selected" : "").">Asia/Macau</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Magadan\" ".(($_SESSION['timezone'] == 'Asia/Magadan') ? "selected" : "").">Asia/Magadan</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Makassar\" ".(($_SESSION['timezone'] == 'Asia/Makassar') ? "selected" : "").">Asia/Makassar</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Manila\" ".(($_SESSION['timezone'] == 'Asia/Manila') ? "selected" : "").">Asia/Manila</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Muscat\" ".(($_SESSION['timezone'] == 'Asia/Muscat') ? "selected" : "").">Asia/Muscat</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Nicosia\" ".(($_SESSION['timezone'] == 'Asia/Nicosia') ? "selected" : "").">Asia/Nicosia</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Novokuznetsk\" ".(($_SESSION['timezone'] == 'Asia/Novokuznetsk') ? "selected" : "").">Asia/Novokuznetsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Novosibirsk\" ".(($_SESSION['timezone'] == 'Asia/Novosibirsk') ? "selected" : "").">Asia/Novosibirsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Omsk\" ".(($_SESSION['timezone'] == 'Asia/Omsk') ? "selected" : "").">Asia/Omsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Oral\" ".(($_SESSION['timezone'] == 'Asia/Oral') ? "selected" : "").">Asia/Oral</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Phnom_Penh\" ".(($_SESSION['timezone'] == 'Asia/Phnom_Penh') ? "selected" : "").">Asia/Phnom_Penh</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Pontianak\" ".(($_SESSION['timezone'] == 'Asia/Pontianak') ? "selected" : "").">Asia/Pontianak</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Pyongyang\" ".(($_SESSION['timezone'] == 'Asia/Pyongyang') ? "selected" : "").">Asia/Pyongyang</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Qatar\" ".(($_SESSION['timezone'] == 'Asia/Qatar') ? "selected" : "").">Asia/Qatar</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Qyzylorda\" ".(($_SESSION['timezone'] == 'Asia/Qyzylorda') ? "selected" : "").">Asia/Qyzylorda</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Rangoon\" ".(($_SESSION['timezone'] == 'Asia/Rangoon') ? "selected" : "").">Asia/Rangoon</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Riyadh\" ".(($_SESSION['timezone'] == 'Asia/Riyadh') ? "selected" : "").">Asia/Riyadh</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Riyadh87\" ".(($_SESSION['timezone'] == 'Asia/Riyadh87') ? "selected" : "").">Asia/Riyadh87</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Riyadh88\" ".(($_SESSION['timezone'] == 'Asia/Riyadh88') ? "selected" : "").">Asia/Riyadh88</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Riyadh89\" ".(($_SESSION['timezone'] == 'Asia/Riyadh89') ? "selected" : "").">Asia/Riyadh89</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Saigon\" ".(($_SESSION['timezone'] == 'Asia/Saigon') ? "selected" : "").">Asia/Saigon</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Sakhalin\" ".(($_SESSION['timezone'] == 'Asia/Sakhalin') ? "selected" : "").">Asia/Sakhalin</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Samarkand\" ".(($_SESSION['timezone'] == 'Asia/Samarkand') ? "selected" : "").">Asia/Samarkand</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Seoul\" ".(($_SESSION['timezone'] == 'Asia/Seoul') ? "selected" : "").">Asia/Seoul</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Shanghai\" ".(($_SESSION['timezone'] == 'Asia/Shanghai') ? "selected" : "").">Asia/Shanghai</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Singapore\" ".(($_SESSION['timezone'] == 'Asia/Singapore') ? "selected" : "").">Asia/Singapore</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Taipei\" ".(($_SESSION['timezone'] == 'Asia/Taipei') ? "selected" : "").">Asia/Taipei</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Tashkent\" ".(($_SESSION['timezone'] == 'Asia/Tashkent') ? "selected" : "").">Asia/Tashkent</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Tbilisi\" ".(($_SESSION['timezone'] == 'Asia/Tbilisi') ? "selected" : "").">Asia/Tbilisi</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Tehran\" ".(($_SESSION['timezone'] == 'Asia/Tehran') ? "selected" : "").">Asia/Tehran</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Tel_Aviv\" ".(($_SESSION['timezone'] == 'Asia/Tel_Aviv') ? "selected" : "").">Asia/Tel_Aviv</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Thimbu\" ".(($_SESSION['timezone'] == 'Asia/Thimbu') ? "selected" : "").">Asia/Thimbu</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Thimphu\" ".(($_SESSION['timezone'] == 'Asia/Thimphu') ? "selected" : "").">Asia/Thimphu</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Tokyo\" ".(($_SESSION['timezone'] == 'Asia/Tokyo') ? "selected" : "").">Asia/Tokyo</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ujung_Pandang\" ".(($_SESSION['timezone'] == 'Asia/Ujung_Pandang') ? "selected" : "").">Asia/Ujung_Pandang</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ulaanbaatar\" ".(($_SESSION['timezone'] == 'Asia/Ulaanbaatar') ? "selected" : "").">Asia/Ulaanbaatar</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ulan_Bator\" ".(($_SESSION['timezone'] == 'Asia/Ulan_Bator') ? "selected" : "").">Asia/Ulan_Bator</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Urumqi\" ".(($_SESSION['timezone'] == 'Asia/Urumqi') ? "selected" : "").">Asia/Urumqi</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Ust-Nera\" ".(($_SESSION['timezone'] == 'Asia/Ust-Nera') ? "selected" : "").">Asia/Ust-Nera</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Vientiane\" ".(($_SESSION['timezone'] == 'Asia/Vientiane') ? "selected" : "").">Asia/Vientiane</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Vladivostok\" ".(($_SESSION['timezone'] == 'Asia/Vladivostok') ? "selected" : "").">Asia/Vladivostok</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Yakutsk\" ".(($_SESSION['timezone'] == 'Asia/Yakutsk') ? "selected" : "").">Asia/Yakutsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Yekaterinburg\" ".(($_SESSION['timezone'] == 'Asia/Yekaterinburg') ? "selected" : "").">Asia/Yekaterinburg</option>\n";
$_timezone['timezone'] .= "<option value=\"Asia/Yerevan\" ".(($_SESSION['timezone'] == 'Asia/Yerevan') ? "selected" : "").">Asia/Yerevan</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Azores\" ".(($_SESSION['timezone'] == 'Atlantic/Azores') ? "selected" : "").">Atlantic/Azores</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Bermuda\" ".(($_SESSION['timezone'] == 'Atlantic/Bermuda') ? "selected" : "").">Atlantic/Bermuda</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Canary\" ".(($_SESSION['timezone'] == 'Atlantic/Canary') ? "selected" : "").">Atlantic/Canary</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Cape_Verde\" ".(($_SESSION['timezone'] == 'Atlantic/Cape_Verde') ? "selected" : "").">Atlantic/Cape_Verde</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Faeroe\" ".(($_SESSION['timezone'] == 'Atlantic/Faeroe') ? "selected" : "").">Atlantic/Faeroe</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Faroe\" ".(($_SESSION['timezone'] == 'Atlantic/Faroe') ? "selected" : "").">Atlantic/Faroe</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Jan_Mayen\" ".(($_SESSION['timezone'] == 'Atlantic/Jan_Mayen') ? "selected" : "").">Atlantic/Jan_Mayen</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Madeira\" ".(($_SESSION['timezone'] == 'Atlantic/Madeira') ? "selected" : "").">Atlantic/Madeira</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Reykjavik\" ".(($_SESSION['timezone'] == 'Atlantic/Reykjavik') ? "selected" : "").">Atlantic/Reykjavik</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/South_Georgia\" ".(($_SESSION['timezone'] == 'Atlantic/South_Georgia') ? "selected" : "").">Atlantic/South_Georgia</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/Stanley\" ".(($_SESSION['timezone'] == 'Atlantic/Stanley') ? "selected" : "").">Atlantic/Stanley</option>\n";
$_timezone['timezone'] .= "<option value=\"Atlantic/St_Helena\" ".(($_SESSION['timezone'] == 'Atlantic/St_Helena') ? "selected" : "").">Atlantic/St_Helena</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/ACT\" ".(($_SESSION['timezone'] == 'Australia/ACT') ? "selected" : "").">Australia/ACT</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Adelaide\" ".(($_SESSION['timezone'] == 'Australia/Adelaide') ? "selected" : "").">Australia/Adelaide</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Brisbane\" ".(($_SESSION['timezone'] == 'Australia/Brisbane') ? "selected" : "").">Australia/Brisbane</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Broken_Hill\" ".(($_SESSION['timezone'] == 'Australia/Broken_Hill') ? "selected" : "").">Australia/Broken_Hill</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Canberra\" ".(($_SESSION['timezone'] == 'Australia/Canberra') ? "selected" : "").">Australia/Canberra</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Currie\" ".(($_SESSION['timezone'] == 'Australia/Currie') ? "selected" : "").">Australia/Currie</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Darwin\" ".(($_SESSION['timezone'] == 'Australia/Darwin') ? "selected" : "").">Australia/Darwin</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Eucla\" ".(($_SESSION['timezone'] == 'Australia/Eucla') ? "selected" : "").">Australia/Eucla</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Hobart\" ".(($_SESSION['timezone'] == 'Australia/Hobart') ? "selected" : "").">Australia/Hobart</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/LHI\" ".(($_SESSION['timezone'] == 'Australia/LHI') ? "selected" : "").">Australia/LHI</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Lindeman\" ".(($_SESSION['timezone'] == 'Australia/Lindeman') ? "selected" : "").">Australia/Lindeman</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Lord_Howe\" ".(($_SESSION['timezone'] == 'Australia/Lord_Howe') ? "selected" : "").">Australia/Lord_Howe</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Melbourne\" ".(($_SESSION['timezone'] == 'Australia/Melbourne') ? "selected" : "").">Australia/Melbourne</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/North\" ".(($_SESSION['timezone'] == 'Australia/North') ? "selected" : "").">Australia/North</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/NSW\" ".(($_SESSION['timezone'] == 'Australia/NSW') ? "selected" : "").">Australia/NSW</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Perth\" ".(($_SESSION['timezone'] == 'Australia/Perth') ? "selected" : "").">Australia/Perth</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Queensland\" ".(($_SESSION['timezone'] == 'Australia/Queensland') ? "selected" : "").">Australia/Queensland</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/South\" ".(($_SESSION['timezone'] == 'Australia/South') ? "selected" : "").">Australia/South</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Sydney\" ".(($_SESSION['timezone'] == 'Australia/Sydney') ? "selected" : "").">Australia/Sydney</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Tasmania\" ".(($_SESSION['timezone'] == 'Australia/Tasmania') ? "selected" : "").">Australia/Tasmania</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Victoria\" ".(($_SESSION['timezone'] == 'Australia/Victoria') ? "selected" : "").">Australia/Victoria</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/West\" ".(($_SESSION['timezone'] == 'Australia/West') ? "selected" : "").">Australia/West</option>\n";
$_timezone['timezone'] .= "<option value=\"Australia/Yancowinna\" ".(($_SESSION['timezone'] == 'Australia/Yancowinna') ? "selected" : "").">Australia/Yancowinna</option>\n";
$_timezone['timezone'] .= "<option value=\"Brazil/Acre\" ".(($_SESSION['timezone'] == 'Brazil/Acre') ? "selected" : "").">Brazil/Acre</option>\n";
$_timezone['timezone'] .= "<option value=\"Brazil/DeNoronha\" ".(($_SESSION['timezone'] == 'Brazil/DeNoronha') ? "selected" : "").">Brazil/DeNoronha</option>\n";
$_timezone['timezone'] .= "<option value=\"Brazil/East\" ".(($_SESSION['timezone'] == 'Brazil/East') ? "selected" : "").">Brazil/East</option>\n";
$_timezone['timezone'] .= "<option value=\"Brazil/West\" ".(($_SESSION['timezone'] == 'Brazil/West') ? "selected" : "").">Brazil/West</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Atlantic\" ".(($_SESSION['timezone'] == 'Canada/Atlantic') ? "selected" : "").">Canada/Atlantic</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Central\" ".(($_SESSION['timezone'] == 'Canada/Central') ? "selected" : "").">Canada/Central</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Eastern\" ".(($_SESSION['timezone'] == 'Canada/Eastern') ? "selected" : "").">Canada/Eastern</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/East-Saskatchewan\" ".(($_SESSION['timezone'] == 'Canada/East-Saskatchewan') ? "selected" : "").">Canada/East-Saskatchewan</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Mountain\" ".(($_SESSION['timezone'] == 'Canada/Mountain') ? "selected" : "").">Canada/Mountain</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Newfoundland\" ".(($_SESSION['timezone'] == 'Canada/Newfoundland') ? "selected" : "").">Canada/Newfoundland</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Pacific\" ".(($_SESSION['timezone'] == 'Canada/Pacific') ? "selected" : "").">Canada/Pacific</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Saskatchewan\" ".(($_SESSION['timezone'] == 'Canada/Saskatchewan') ? "selected" : "").">Canada/Saskatchewan</option>\n";
$_timezone['timezone'] .= "<option value=\"Canada/Yukon\" ".(($_SESSION['timezone'] == 'Canada/Yukon') ? "selected" : "").">Canada/Yukon</option>\n";
$_timezone['timezone'] .= "<option value=\"Chile/Continental\" ".(($_SESSION['timezone'] == 'Chile/Continental') ? "selected" : "").">Chile/Continental</option>\n";
$_timezone['timezone'] .= "<option value=\"Chile/EasterIsland\" ".(($_SESSION['timezone'] == 'Chile/EasterIsland') ? "selected" : "").">Chile/EasterIsland</option>\n";
$_timezone['timezone'] .= "<option value=\"Cuba\" ".(($_SESSION['timezone'] == 'Cuba') ? "selected" : "").">Cuba</option>\n";
$_timezone['timezone'] .= "<option value=\"Egypt\" ".(($_SESSION['timezone'] == 'Egypt') ? "selected" : "").">Egypt</option>\n";
$_timezone['timezone'] .= "<option value=\"Eire\" ".(($_SESSION['timezone'] == 'Eire') ? "selected" : "").">Eire</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Amsterdam\" ".(($_SESSION['timezone'] == 'Europe/Amsterdam') ? "selected" : "").">Europe/Amsterdam</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Andorra\" ".(($_SESSION['timezone'] == 'Europe/Andorra') ? "selected" : "").">Europe/Andorra</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Athens\" ".(($_SESSION['timezone'] == 'Europe/Athens') ? "selected" : "").">Europe/Athens</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Belfast\" ".(($_SESSION['timezone'] == 'Europe/Belfast') ? "selected" : "").">Europe/Belfast</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Belgrade\" ".(($_SESSION['timezone'] == 'Europe/Belgrade') ? "selected" : "").">Europe/Belgrade</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Berlin\" ".(($_SESSION['timezone'] == 'Europe/Berlin') ? "selected" : "").">Europe/Berlin</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Bratislava\" ".(($_SESSION['timezone'] == 'Europe/Bratislava') ? "selected" : "").">Europe/Bratislava</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Brussels\" ".(($_SESSION['timezone'] == 'Europe/Brussels') ? "selected" : "").">Europe/Brussels</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Bucharest\" ".(($_SESSION['timezone'] == 'Europe/Bucharest') ? "selected" : "").">Europe/Bucharest</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Budapest\" ".(($_SESSION['timezone'] == 'Europe/Budapest') ? "selected" : "").">Europe/Budapest</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Busingen\" ".(($_SESSION['timezone'] == 'Europe/Busingen') ? "selected" : "").">Europe/Busingen</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Chisinau\" ".(($_SESSION['timezone'] == 'Europe/Chisinau') ? "selected" : "").">Europe/Chisinau</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Copenhagen\" ".(($_SESSION['timezone'] == 'Europe/Copenhagen') ? "selected" : "").">Europe/Copenhagen</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Dublin\" ".(($_SESSION['timezone'] == 'Europe/Dublin') ? "selected" : "").">Europe/Dublin</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Gibraltar\" ".(($_SESSION['timezone'] == 'Europe/Gibraltar') ? "selected" : "").">Europe/Gibraltar</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Guernsey\" ".(($_SESSION['timezone'] == 'Europe/Guernsey') ? "selected" : "").">Europe/Guernsey</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Helsinki\" ".(($_SESSION['timezone'] == 'Europe/Helsinki') ? "selected" : "").">Europe/Helsinki</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Isle_of_Man\" ".(($_SESSION['timezone'] == 'Europe/Isle_of_Man') ? "selected" : "").">Europe/Isle_of_Man</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Istanbul\" ".(($_SESSION['timezone'] == 'Europe/Istanbul') ? "selected" : "").">Europe/Istanbul</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Jersey\" ".(($_SESSION['timezone'] == 'Europe/Jersey') ? "selected" : "").">Europe/Jersey</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Kaliningrad\" ".(($_SESSION['timezone'] == 'Europe/Kaliningrad') ? "selected" : "").">Europe/Kaliningrad</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Kiev\" ".(($_SESSION['timezone'] == 'Europe/Kiev') ? "selected" : "").">Europe/Kiev</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Lisbon\" ".(($_SESSION['timezone'] == 'Europe/Lisbon') ? "selected" : "").">Europe/Lisbon</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Ljubljana\" ".(($_SESSION['timezone'] == 'Europe/Ljubljana') ? "selected" : "").">Europe/Ljubljana</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/London\" ".(($_SESSION['timezone'] == 'Europe/London') ? "selected" : "").">Europe/London</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Luxembourg\" ".(($_SESSION['timezone'] == 'Europe/Luxembourg') ? "selected" : "").">Europe/Luxembourg</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Madrid\" ".(($_SESSION['timezone'] == 'Europe/Madrid') ? "selected" : "").">Europe/Madrid</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Malta\" ".(($_SESSION['timezone'] == 'Europe/Malta') ? "selected" : "").">Europe/Malta</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Mariehamn\" ".(($_SESSION['timezone'] == 'Europe/Mariehamn') ? "selected" : "").">Europe/Mariehamn</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Minsk\" ".(($_SESSION['timezone'] == 'Europe/Minsk') ? "selected" : "").">Europe/Minsk</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Monaco\" ".(($_SESSION['timezone'] == 'Europe/Monaco') ? "selected" : "").">Europe/Monaco</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Moscow\" ".(($_SESSION['timezone'] == 'Europe/Moscow') ? "selected" : "").">Europe/Moscow</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Nicosia\" ".(($_SESSION['timezone'] == 'Europe/Nicosia') ? "selected" : "").">Europe/Nicosia</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Oslo\" ".(($_SESSION['timezone'] == 'Europe/Oslo') ? "selected" : "").">Europe/Oslo</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Paris\" ".(($_SESSION['timezone'] == 'Europe/Paris') ? "selected" : "").">Europe/Paris</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Podgorica\" ".(($_SESSION['timezone'] == 'Europe/Podgorica') ? "selected" : "").">Europe/Podgorica</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Prague\" ".(($_SESSION['timezone'] == 'Europe/Prague') ? "selected" : "").">Europe/Prague</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Riga\" ".(($_SESSION['timezone'] == 'Europe/Riga') ? "selected" : "").">Europe/Riga</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Rome\" ".(($_SESSION['timezone'] == 'Europe/Rome') ? "selected" : "").">Europe/Rome</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Samara\" ".(($_SESSION['timezone'] == 'Europe/Samara') ? "selected" : "").">Europe/Samara</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/San_Marino\" ".(($_SESSION['timezone'] == 'Europe/San_Marino') ? "selected" : "").">Europe/San_Marino</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Sarajevo\" ".(($_SESSION['timezone'] == 'Europe/Sarajevo') ? "selected" : "").">Europe/Sarajevo</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Simferopol\" ".(($_SESSION['timezone'] == 'Europe/Simferopol') ? "selected" : "").">Europe/Simferopol</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Skopje\" ".(($_SESSION['timezone'] == 'Europe/Skopje') ? "selected" : "").">Europe/Skopje</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Sofia\" ".(($_SESSION['timezone'] == 'Europe/Sofia') ? "selected" : "").">Europe/Sofia</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Stockholm\" ".(($_SESSION['timezone'] == 'Europe/Stockholm') ? "selected" : "").">Europe/Stockholm</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Tallinn\" ".(($_SESSION['timezone'] == 'Europe/Tallinn') ? "selected" : "").">Europe/Tallinn</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Tirane\" ".(($_SESSION['timezone'] == 'Europe/Tirane') ? "selected" : "").">Europe/Tirane</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Tiraspol\" ".(($_SESSION['timezone'] == 'Europe/Tiraspol') ? "selected" : "").">Europe/Tiraspol</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Uzhgorod\" ".(($_SESSION['timezone'] == 'Europe/Uzhgorod') ? "selected" : "").">Europe/Uzhgorod</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Vaduz\" ".(($_SESSION['timezone'] == 'Europe/Vaduz') ? "selected" : "").">Europe/Vaduz</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Vatican\" ".(($_SESSION['timezone'] == 'Europe/Vatican') ? "selected" : "").">Europe/Vatican</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Vienna\" ".(($_SESSION['timezone'] == 'Europe/Vienna') ? "selected" : "").">Europe/Vienna</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Vilnius\" ".(($_SESSION['timezone'] == 'Europe/Vilnius') ? "selected" : "").">Europe/Vilnius</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Volgograd\" ".(($_SESSION['timezone'] == 'Europe/Volgograd') ? "selected" : "").">Europe/Volgograd</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Warsaw\" ".(($_SESSION['timezone'] == 'Europe/Warsaw') ? "selected" : "").">Europe/Warsaw</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Zagreb\" ".(($_SESSION['timezone'] == 'Europe/Zagreb') ? "selected" : "").">Europe/Zagreb</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Zaporozhye\" ".(($_SESSION['timezone'] == 'Europe/Zaporozhye') ? "selected" : "").">Europe/Zaporozhye</option>\n";
$_timezone['timezone'] .= "<option value=\"Europe/Zurich\" ".(($_SESSION['timezone'] == 'Europe/Zurich') ? "selected" : "").">Europe/Zurich</option>\n";
$_timezone['timezone'] .= "<option value=\"Greenwich\" ".(($_SESSION['timezone'] == 'Greenwich') ? "selected" : "").">Greenwich</option>\n";
$_timezone['timezone'] .= "<option value=\"Hongkong\" ".(($_SESSION['timezone'] == 'Hongkong') ? "selected" : "").">Hongkong</option>\n";
$_timezone['timezone'] .= "<option value=\"Iceland\" ".(($_SESSION['timezone'] == 'Iceland') ? "selected" : "").">Iceland</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Antananarivo\" ".(($_SESSION['timezone'] == 'Indian/Antananarivo') ? "selected" : "").">Indian/Antananarivo</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Chagos\" ".(($_SESSION['timezone'] == 'Indian/Chagos') ? "selected" : "").">Indian/Chagos</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Christmas\" ".(($_SESSION['timezone'] == 'Indian/Christmas') ? "selected" : "").">Indian/Christmas</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Cocos\" ".(($_SESSION['timezone'] == 'Indian/Cocos') ? "selected" : "").">Indian/Cocos</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Comoro\" ".(($_SESSION['timezone'] == 'Indian/Comoro') ? "selected" : "").">Indian/Comoro</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Kerguelen\" ".(($_SESSION['timezone'] == 'Indian/Kerguelen') ? "selected" : "").">Indian/Kerguelen</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Mahe\" ".(($_SESSION['timezone'] == 'Indian/Mahe') ? "selected" : "").">Indian/Mahe</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Maldives\" ".(($_SESSION['timezone'] == 'Indian/Maldives') ? "selected" : "").">Indian/Maldives</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Mauritius\" ".(($_SESSION['timezone'] == 'Indian/Mauritius') ? "selected" : "").">Indian/Mauritius</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Mayotte\" ".(($_SESSION['timezone'] == 'Indian/Mayotte') ? "selected" : "").">Indian/Mayotte</option>\n";
$_timezone['timezone'] .= "<option value=\"Indian/Reunion\" ".(($_SESSION['timezone'] == 'Indian/Reunion') ? "selected" : "").">Indian/Reunion</option>\n";
$_timezone['timezone'] .= "<option value=\"Iran\" ".(($_SESSION['timezone'] == 'Iran') ? "selected" : "").">Iran</option>\n";
$_timezone['timezone'] .= "<option value=\"Israel\" ".(($_SESSION['timezone'] == 'Israel') ? "selected" : "").">Israel</option>\n";
$_timezone['timezone'] .= "<option value=\"Jamaica\" ".(($_SESSION['timezone'] == 'Jamaica') ? "selected" : "").">Jamaica</option>\n";
$_timezone['timezone'] .= "<option value=\"Japan\" ".(($_SESSION['timezone'] == 'Japan') ? "selected" : "").">Japan</option>\n";
$_timezone['timezone'] .= "<option value=\"Kwajalein\" ".(($_SESSION['timezone'] == 'Kwajalein') ? "selected" : "").">Kwajalein</option>\n";
$_timezone['timezone'] .= "<option value=\"Libya\" ".(($_SESSION['timezone'] == 'Libya') ? "selected" : "").">Libya</option>\n";
$_timezone['timezone'] .= "<option value=\"localtime\" ".(($_SESSION['timezone'] == 'localtime') ? "selected" : "").">localtime</option>\n";
$_timezone['timezone'] .= "<option value=\"Mexico/BajaNorte\" ".(($_SESSION['timezone'] == 'Mexico/BajaNorte') ? "selected" : "").">Mexico/BajaNorte</option>\n";
$_timezone['timezone'] .= "<option value=\"Mexico/BajaSur\" ".(($_SESSION['timezone'] == 'Mexico/BajaSur') ? "selected" : "").">Mexico/BajaSur</option>\n";
$_timezone['timezone'] .= "<option value=\"Mexico/General\" ".(($_SESSION['timezone'] == 'Mexico/General') ? "selected" : "").">Mexico/General</option>\n";
$_timezone['timezone'] .= "<option value=\"Mideast/Riyadh87\" ".(($_SESSION['timezone'] == 'Mideast/Riyadh87') ? "selected" : "").">Mideast/Riyadh87</option>\n";
$_timezone['timezone'] .= "<option value=\"Mideast/Riyadh88\" ".(($_SESSION['timezone'] == 'Mideast/Riyadh88') ? "selected" : "").">Mideast/Riyadh88</option>\n";
$_timezone['timezone'] .= "<option value=\"Mideast/Riyadh89\" ".(($_SESSION['timezone'] == 'Mideast/Riyadh89') ? "selected" : "").">Mideast/Riyadh89</option>\n";
$_timezone['timezone'] .= "<option value=\"Navajo\" ".(($_SESSION['timezone'] == 'Navajo') ? "selected" : "").">Navajo</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Apia\" ".(($_SESSION['timezone'] == 'Pacific/Apia') ? "selected" : "").">Pacific/Apia</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Auckland\" ".(($_SESSION['timezone'] == 'Pacific/Auckland') ? "selected" : "").">Pacific/Auckland</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Chatham\" ".(($_SESSION['timezone'] == 'Pacific/Chatham') ? "selected" : "").">Pacific/Chatham</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Chuuk\" ".(($_SESSION['timezone'] == 'Pacific/Chuuk') ? "selected" : "").">Pacific/Chuuk</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Easter\" ".(($_SESSION['timezone'] == 'Pacific/Easter') ? "selected" : "").">Pacific/Easter</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Efate\" ".(($_SESSION['timezone'] == 'Pacific/Efate') ? "selected" : "").">Pacific/Efate</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Enderbury\" ".(($_SESSION['timezone'] == 'Pacific/Enderbury') ? "selected" : "").">Pacific/Enderbury</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Fakaofo\" ".(($_SESSION['timezone'] == 'Pacific/Fakaofo') ? "selected" : "").">Pacific/Fakaofo</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Fiji\" ".(($_SESSION['timezone'] == 'Pacific/Fiji') ? "selected" : "").">Pacific/Fiji</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Funafuti\" ".(($_SESSION['timezone'] == 'Pacific/Funafuti') ? "selected" : "").">Pacific/Funafuti</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Galapagos\" ".(($_SESSION['timezone'] == 'Pacific/Galapagos') ? "selected" : "").">Pacific/Galapagos</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Gambier\" ".(($_SESSION['timezone'] == 'Pacific/Gambier') ? "selected" : "").">Pacific/Gambier</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Guadalcanal\" ".(($_SESSION['timezone'] == 'Pacific/Guadalcanal') ? "selected" : "").">Pacific/Guadalcanal</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Guam\" ".(($_SESSION['timezone'] == 'Pacific/Guam') ? "selected" : "").">Pacific/Guam</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Honolulu\" ".(($_SESSION['timezone'] == 'Pacific/Honolulu') ? "selected" : "").">Pacific/Honolulu</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Johnston\" ".(($_SESSION['timezone'] == 'Pacific/Johnston') ? "selected" : "").">Pacific/Johnston</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Kiritimati\" ".(($_SESSION['timezone'] == 'Pacific/Kiritimati') ? "selected" : "").">Pacific/Kiritimati</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Kosrae\" ".(($_SESSION['timezone'] == 'Pacific/Kosrae') ? "selected" : "").">Pacific/Kosrae</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Kwajalein\" ".(($_SESSION['timezone'] == 'Pacific/Kwajalein') ? "selected" : "").">Pacific/Kwajalein</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Majuro\" ".(($_SESSION['timezone'] == 'Pacific/Majuro') ? "selected" : "").">Pacific/Majuro</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Marquesas\" ".(($_SESSION['timezone'] == 'Pacific/Marquesas') ? "selected" : "").">Pacific/Marquesas</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Midway\" ".(($_SESSION['timezone'] == 'Pacific/Midway') ? "selected" : "").">Pacific/Midway</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Nauru\" ".(($_SESSION['timezone'] == 'Pacific/Nauru') ? "selected" : "").">Pacific/Nauru</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Niue\" ".(($_SESSION['timezone'] == 'Pacific/Niue') ? "selected" : "").">Pacific/Niue</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Norfolk\" ".(($_SESSION['timezone'] == 'Pacific/Norfolk') ? "selected" : "").">Pacific/Norfolk</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Noumea\" ".(($_SESSION['timezone'] == 'Pacific/Noumea') ? "selected" : "").">Pacific/Noumea</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Pago_Pago\" ".(($_SESSION['timezone'] == 'Pacific/Pago_Pago') ? "selected" : "").">Pacific/Pago_Pago</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Palau\" ".(($_SESSION['timezone'] == 'Pacific/Palau') ? "selected" : "").">Pacific/Palau</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Pitcairn\" ".(($_SESSION['timezone'] == 'Pacific/Pitcairn') ? "selected" : "").">Pacific/Pitcairn</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Pohnpei\" ".(($_SESSION['timezone'] == 'Pacific/Pohnpei') ? "selected" : "").">Pacific/Pohnpei</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Ponape\" ".(($_SESSION['timezone'] == 'Pacific/Ponape') ? "selected" : "").">Pacific/Ponape</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Port_Moresby\" ".(($_SESSION['timezone'] == 'Pacific/Port_Moresby') ? "selected" : "").">Pacific/Port_Moresby</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Rarotonga\" ".(($_SESSION['timezone'] == 'Pacific/Rarotonga') ? "selected" : "").">Pacific/Rarotonga</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Saipan\" ".(($_SESSION['timezone'] == 'Pacific/Saipan') ? "selected" : "").">Pacific/Saipan</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Samoa\" ".(($_SESSION['timezone'] == 'Pacific/Samoa') ? "selected" : "").">Pacific/Samoa</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Tahiti\" ".(($_SESSION['timezone'] == 'Pacific/Tahiti') ? "selected" : "").">Pacific/Tahiti</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Tarawa\" ".(($_SESSION['timezone'] == 'Pacific/Tarawa') ? "selected" : "").">Pacific/Tarawa</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Tongatapu\" ".(($_SESSION['timezone'] == 'Pacific/Tongatapu') ? "selected" : "").">Pacific/Tongatapu</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Truk\" ".(($_SESSION['timezone'] == 'Pacific/Truk') ? "selected" : "").">Pacific/Truk</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Wake\" ".(($_SESSION['timezone'] == 'Pacific/Wake') ? "selected" : "").">Pacific/Wake</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Wallis\" ".(($_SESSION['timezone'] == 'Pacific/Wallis') ? "selected" : "").">Pacific/Wallis</option>\n";
$_timezone['timezone'] .= "<option value=\"Pacific/Yap\" ".(($_SESSION['timezone'] == 'Pacific/Yap') ? "selected" : "").">Pacific/Yap</option>\n";
$_timezone['timezone'] .= "<option value=\"Poland\" ".(($_SESSION['timezone'] == 'Poland') ? "selected" : "").">Poland</option>\n";
$_timezone['timezone'] .= "<option value=\"Portugal\" ".(($_SESSION['timezone'] == 'Portugal') ? "selected" : "").">Portugal</option>\n";
$_timezone['timezone'] .= "<option value=\"Singapore\" ".(($_SESSION['timezone'] == 'Singapore') ? "selected" : "").">Singapore</option>\n";
$_timezone['timezone'] .= "<option value=\"Turkey\" ".(($_SESSION['timezone'] == 'Turkey') ? "selected" : "").">Turkey</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Alaska\" ".(($_SESSION['timezone'] == 'US/Alaska') ? "selected" : "").">US/Alaska</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Aleutian\" ".(($_SESSION['timezone'] == 'US/Aleutian') ? "selected" : "").">US/Aleutian</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Arizona\" ".(($_SESSION['timezone'] == 'US/Arizona') ? "selected" : "").">US/Arizona</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Central\" ".(($_SESSION['timezone'] == 'US/Central') ? "selected" : "").">US/Central</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Eastern\" ".(($_SESSION['timezone'] == 'US/Eastern') ? "selected" : "").">US/Eastern</option>\n";
$_timezone['timezone'] .= "<option value=\"US/East-Indiana\" ".(($_SESSION['timezone'] == 'US/East-Indiana') ? "selected" : "").">US/East-Indiana</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Hawaii\" ".(($_SESSION['timezone'] == 'US/Hawaii') ? "selected" : "").">US/Hawaii</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Indiana-Starke\" ".(($_SESSION['timezone'] == 'US/Indiana-Starke') ? "selected" : "").">US/Indiana-Starke</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Michigan\" ".(($_SESSION['timezone'] == 'US/Michigan') ? "selected" : "").">US/Michigan</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Mountain\" ".(($_SESSION['timezone'] == 'US/Mountain') ? "selected" : "").">US/Mountain</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Pacific\" ".(($_SESSION['timezone'] == 'US/Pacific') ? "selected" : "").">US/Pacific</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Pacific-New\" ".(($_SESSION['timezone'] == 'US/Pacific-New') ? "selected" : "").">US/Pacific-New</option>\n";
$_timezone['timezone'] .= "<option value=\"US/Samoa\" ".(($_SESSION['timezone'] == 'US/Samoa') ? "selected" : "").">US/Samoa</option>\n";
$_timezone['timezone'] .= "<option value=\"Zulu\" ".(($_SESSION['timezone'] == 'Zulu') ? "selected" : "").">Zulu</option>\n";
// end timezones

// set template
$tpl = "settings.html";
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
// wait for worker output if $_SESSION['w_active'] = 1
// TC (Tim Curtis) 2015-02-25: dont wait if kernel select so page returns and ui_notify message appears
// TC (Tim Curtis) 2015-02-25: use notify title as the check since its not cleared by worker (player_wrk.php)
if ($_SESSION['notify']['title'] != 'Kernel change') {
	waitWorker(1);
}
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
?>
<!-- content -->

<?php
debug($_POST);
?>

<?php include('_footer.php'); ?>
