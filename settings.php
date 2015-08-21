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
require_once dirname(__FILE__) . '/inc/timezone.php';


playerSession('open',$db,'','');
playerSession('unlock',$db,'','');

// theme change via system command
if (isset($_POST['syscmd'])) {
	switch ($_POST['syscmd']) {
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
			if (workerPushTask("themechange", $_POST['syscmd'])) {
				playerSession('unlock');
			}
			else {
				echo "background worker is busy";
			}
			break;
	}
}


session_start();

// audio device update
if (isset($_POST['update_i2s_device'])) {
	if (isset($_POST['i2s']) && $_POST['i2s'] != $_SESSION['i2s']) {
		if (workerPushTask('i2sdriver', $_POST['i2s'])) {
			uiSetNotification('Setting change', "I2S device has been changed, REBOOT for setting to take effect.", 5);

			// TC (Tim Curtis) 2015-03-21: Adjust message depending on selected device
			if ($_POST['i2s'] == "IQaudIO Pi-AMP+") {
				uiSetNotification('', "<br><br>This device REQUIRES hardware volume control. After rebooting, set MPD Volume control to Hardware.");
			}
			elseif (
				$_POST['i2s'] == "HiFiBerry DAC+" ||
				$_POST['i2s'] == "HiFiBerry Amp(Amp+)" ||
				$_POST['i2s'] == "IQaudIO Pi-DAC" ||
				$_POST['i2s'] == "IQaudIO Pi-DAC+" ||
				$_POST['i2s'] == "RaspyPlay4")
			{
				uiSetNotification('', "<br><br>This device supports hardware volume control. After rebooting, optionally set MPD Volume control to Hardware.");
			}
			// TC (Tim Curtis) 2015-04-29: update cfg_engine table, moved from player_wrk.php, fixes field not updating when page echos back
			playerSession('write',$db,'i2s',$_POST['i2s']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}
// TC (Tim Curtis) 2015-02-25: kernel select handler
// TC (Tim Curtis) 2015-06-26: use unique notify Title in update_kernel_version for Waitworker(1) test at end of script to allow the Notify message to appear
// TC (Tim Curtis) 2015-06-26: change notify message duration from 5 to 10 mins
if (isset($_POST['update_kernel_version'])) {
	if (isset($_POST['kernelver']) && $_POST['kernelver'] != $_SESSION['kernelver']) {
		if (workerPushTask('kernelver', $_POST['kernelver'])) {
			uiSetNotification('Kernel change', "Version ".$_POST['kernelver']." install initiated...<br><br>The process can take 5+ minutes to<br>complete after which the CONNECTING<br>screen will appear and the system will<br>be POWERED OFF.", 600);
			// TC (Tim Curtis) 2015-04-29: update cfg_engine table, added, fixes field not updating when page echos back
			playerSession('write',$db,'kernelver',$_POST['kernelver']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}
// TC (Tim Curtis) 2015-04-29: timezone select handler
if (isset($_POST['update_time_zone'])) {
	if (isset($_POST['timezone']) && $_POST['timezone'] != $_SESSION['timezone']) {
		if (workerPushTask('timezone', $_POST['timezone'])) {
			uiSetNotification('Setting change', "Timezone ".$_POST['timezone']." has been set.", 4);
			// TC (Tim Curtis) 2015-04-29: update cfg_engine table, moved from player_wrk.php, fixes field not updating when page echos back
			playerSession('write',$db,'timezone',$_POST['timezone']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_latency_setting'])) {
	if (isset($_POST['orionprofile']) && $_POST['orionprofile'] != $_SESSION['orionprofile']) {
		if (workerPushTask('orionprofile', $_POST['orionprofile'])) {
			uiSetNotification('Setting change', 'Kernel latency setting has been changed to: '.$_POST['orionprofile'].', REBOOT for setting to take effect.', 4);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}

		if ($_SESSION['w_lock'] != 1) {
			playerSession('write',$db,'orionprofile',$_POST['orionprofile']);
			playerSession('unlock');
		}
		else {
			return "background worker busy";
		}
	}
}

// shairport
if (isset($_POST['shairport']) && $_POST['shairport'] != $_SESSION['shairport']) {
	if ($_POST['shairport'] == 1 OR $_POST['shairport'] == 0) {
		playerSession('write',$db,'shairport',$_POST['shairport']);
	}
	uiSetNotification('Setting change', ($_POST['shairport'] == 1)
		? 'Airplay receiver enabled, REBOOT for setting to take effect.'
		: 'Airplay receiver disabled, REBOOT for setting to take effect.',
	4);
	playerSession('unlock');
}


if (isset($_POST['upnpmpdcli']) && $_POST['upnpmpdcli'] != $_SESSION['upnpmpdcli']) {
	if ($_POST['upnpmpdcli'] == 1 OR $_POST['upnpmpdcli'] == 0) {
		playerSession('write',$db,'upnpmpdcli',$_POST['upnpmpdcli']);
	}
	uiSetNotification('Setting change', ($_POST['upnpmpdcli'] == 1)
		? 'UPnP renderer enabled, REBOOT for setting to take effect.'
		: 'UPnP renderer disabled, REBOOT for setting to take effect.',
	4);
	playerSession('unlock');
}

if (isset($_POST['djmount']) && $_POST['djmount'] != $_SESSION['djmount']) {
	if ($_POST['djmount'] == 1 OR $_POST['djmount'] == 0) {
		playerSession('write',$db,'djmount',$_POST['djmount']);
	}
	uiSetNotification('Setting change', ($_POST['djmount'] == 1)
		? 'DLNA server enabled, REBOOT for setting to take effect.'
		: 'DLNA server disabled, REBOOT for setting to take effect.',
	4);
	playerSession('unlock');
}

// TC (Tim Curtis) 2015-04-29: host and network service name change handlers
if (isset($_POST['update_host_name'])) {
	if (isset($_POST['host_name']) && $_POST['host_name'] != $_SESSION['host_name']) {
		if (preg_match("/[^A-Za-z0-9-]/", $_POST['host_name']) == 1) {
			uiSetNotification('Invalid input', "Host name can only contain A-Z, a-z, 0-9 or hyphen (-).", 4);
		}
		else {
			if (workerPushTask('host_name', "\"".$_SESSION['host_name']."\" "."\"".$_POST['host_name']."\"")) {
				uiSetNotification('Setting change', "Host name has been changed, REBOOT for setting to take effect.", 4);
				playerSession('write',$db,'host_name',$_POST['host_name']);
			}
			else {
				echo "background worker busy";
			}
		}
		playerSession('unlock');
	}
}
if (isset($_POST['update_browser_title'])) {
	if (isset($_POST['browser_title']) && $_POST['browser_title'] != $_SESSION['browser_title']) {
		if (workerPushTask('browser_title', "\"".$_SESSION['browser_title']."\" "."\"".$_POST['browser_title']."\"")) {
			uiSetNotification('Setting change', "Browser title has been changed, REBOOT for setting to take effect.", 4);
			playerSession('write',$db,'browser_title',$_POST['browser_title']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_airplay_name'])) {
	if (isset($_POST['airplay_name']) && $_POST['airplay_name'] != $_SESSION['airplay_name']) {
		if (workerPushTask('airplay_name', "\"".$_SESSION['airplay_name']."\" "."\"".$_POST['airplay_name']."\"")) {
			uiSetNotification('Setting change', "Airplay receiver name has been changed, REBOOT for setting to take effect.", 4);
			playerSession('write',$db,'airplay_name',$_POST['airplay_name']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";

		}
	}
}
if (isset($_POST['update_upnp_name'])) {
	if (isset($_POST['upnp_name']) && $_POST['upnp_name'] != $_SESSION['upnp_name']) {
		if (workerPushTask('upnp_name', "\"".$_SESSION['upnp_name']."\" "."\"".$_POST['upnp_name']."\"")) {
			uiSetNotification('Setting change', "UPnP renderer name has been changed, REBOOT for setting to take effect.", 4);
			playerSession('write',$db,'upnp_name',$_POST['upnp_name']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}

}
if (isset($_POST['update_dlna_name'])) {
	if (isset($_POST['dlna_name']) && $_POST['dlna_name'] != $_SESSION['dlna_name']) {
		if (workerPushTask('dlna_name', "\"".$_SESSION['dlna_name']."\" "."\"".$_POST['dlna_name']."\"")) {
			uiSetNotification('Setting change', "DLNA server name has been changed, REBOOT for setting to take effect.", 4);
			playerSession('write',$db,'dlna_name',$_POST['dlna_name']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}

}

// TC (Tim Curtis) 2015-04-29: handle PCM volume change
if (isset($_POST['update_pcm_volume'])) {
	if (isset($_POST['pcm_volume'])) {
		if (workerPushTask('pcm_volume', $_POST['pcm_volume'])) {
			uiSetNotification('Setting change', "PCM volume has been set.", 4);
			playerSession('write',$db,'pcm_volume',$_POST['pcm_volume']);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}

// TC (Tim Curtis) 2015-05-30: handle log maintenance for system and play history logs
if (isset($_POST['update_clear_syslogs'])) {
	if ($_POST['clearsyslogs'] == 1) {
		if (workerPushTask('clearsyslogs')) {
			uiSetNotification('Log maintenance', "System logs have been cleared.", 4);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}
if (isset($_POST['update_clear_playhistory'])) {
	if ($_POST['clearplayhistory'] == 1) {
		if (workerPushTask('clearplayhistory')) {
			uiSetNotification('Log maintenance', "Playback history log hase been cleared.", 4);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}
}

// TC (Tim Curtis) 2015-07-31: expand sd card storage
if (isset($_POST['update_expand_sdcard'])) {
	if ($_POST['expandsdcard'] == 1) {
		if (workerPushTask('expandsdcard')) {
			uiSetNotification('Expand SD Card Storage', "Storage expansion request has been queued. REBOOT has been initiated.", 6);
			playerSession('unlock');
		}
		else {
			echo "background worker busy";
		}
	}

}

// configure html select elements
$kernelver = getKernelVer($_SESSION['kernelver']);
if ($kernelver == '3.18.5+' || $kernelver == '3.18.11+' || $kernelver == '3.18.14+') {
	$dacs = array(
		'I2S Off',
		'Audiophonics I-Sabre DAC',
		'Durio Sound PRO',
		'G2 Labs BerryNOS',
		'G2 Labs BerryNOS Red',
		'HiFiBerry Amp(Amp+)',
		'HiFiBerry DAC',
		'HiFiBerry DAC+',
		'HiFiBerry Digi(Digi+)',
		'Hifimediy ES9023',
		'IQaudIO Pi-AMP+',
		'IQaudIO Pi-DAC',
		'IQaudIO Pi-DAC+',
		'IQaudIO Pi-DigiAMP+',
		'RaspyPlay4',
		'RPi DAC',
		'Generic'
	);

	foreach ($dacs as $dac) {
		$dacName = ($dac == 'I2S Off') ? 'None' : $dac;
		$selected = ($_SESSION['i2s'] == $dac) ? ' selected' : '';
		$_i2s['i2s'] .= sprintf('<option value="%s"%s>%s</option>\n', $dac, $selected, $dacName);
	}
}
else {
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
}
else {
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
}
else if ($_SESSION['procarch'] == "armv6l") { // Pi-1 and Pi-B+
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.14+\" ".(($_SESSION['kernelver'] == '3.18.14+') ? "selected" : "").">3.18.14+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.11+\" ".(($_SESSION['kernelver'] == '3.18.11+') ? "selected" : "").">3.18.11+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.5+\" ".(($_SESSION['kernelver'] == '3.18.5+') ? "selected" : "").">3.18.5+</option>\n";

	// TC (Tim Curtis) 2015-06-26: drop support for these, not in use by any users
	//$_linux_kernel['kernelver'] .= "<option value=\"3.12.26+\" ".(($_SESSION['kernelver'] == '3.12.26+') ? "selected" : "").">3.12.26+</option>\n";
	//$_linux_kernel['kernelver'] .= "<option value=\"3.10.36+\" ".(($_SESSION['kernelver'] == '3.10.36+') ? "selected" : "").">3.10.36+</option>\n";
}
else {
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
$_timezone['timezone'] = buildTimezoneSelect($_SESSION['timezone']);

// set template
$tpl = "settings.html";


$sezione = basename(__FILE__, '.php');
include('_header.php');

// TC (Tim Curtis) 2015-02-25: dont wait if kernel select so page returns and uiShowNotification message appears
// TC (Tim Curtis) 2015-02-25: use notify title as the check since its not cleared by worker (player_wrk.php)
if (!isset($_SESSION['notify']['title']) ||
	isset($_SESSION['notify']['title']) && $_SESSION['notify']['title'] !== 'Kernel change')
{
	waitWorker(1);
}

eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
include('_footer.php');
