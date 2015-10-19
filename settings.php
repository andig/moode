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
 *	TC (Tim Curtis) 2015-08-30, r2.2
 *	- update release id to r22
 *	- add Pi-DigiAMP+ to update_i2s_device
 *	- add max usb current 2x
 *	- add rotary encoder
 *
 *	TC (Tim Curtis) 2015-09-05, r2.3
 *	- update release id to r23
 *
 *	TC (Tim Curtis) 2015-10-DD, r2.4
 *	- update release id to r24
 *	- add autoplay after start
 *	- add Audiophonics PCM5122 DAC, Lucid Labs Raspberry Pi DAC, PlainDAC, PlainDAC+ and HifiBerry DAC+ Pro
 *	- add check for kernel 4.1.10+ in various places
 *
 */
 
<<<<<<< .mine
$TCMODS_REL = "r24"; // Current release
=======

>>>>>>> .theirs
 
require_once dirname(__FILE__) . '/inc/connection.php';
require_once dirname(__FILE__) . '/inc/timezone.php';


/**
 * Update on/off session setting
 */
function sessionToggle($switch) {
	if (isset($_POST[$switch])) {
		$val = (int)$_POST[$switch];

		if ($val != $_SESSION[$switch]) {
			if ($val == 0 || $val == 1) {
				Session::update($switch, $val);
				return true;
			}
	}
}

<<<<<<< .mine
// TC (Tim Curtis) 2014-08-23: i2s driver support for G2 Labs BerryNOS DAC (same drivers as for hifiberry dac)
// TC (Tim Curtis) 2014-08-23: edit message title and text
// TC (Tim Curtis) 2015-01-27: move i2s processing from within switch syscmd above to here
// TC (Tim Curtis) 2015-01-27: update list of drivers and associated modules
// TC (Tim Curtis) 2015-01-27: add code to store selection in db
// TC (Tim Curtis) 2015-02-25: i2s driver select handler
// TC (Tim Curtis) 2015-04-29: add RaspyPlay4 to i2s select list
// TC (Tim Curtis) 2015-04-29: add update btn check
// TC (Tim Curtis) 2015-08-30: add Pi-DigiAMP+
// TC (Tim Curtis) 2015-10-DD: add Audiophonics PCM5122 DAC, PlainDAC+ and HifiBerry DAC+ Pro

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
			} else if ($_POST['i2s'] == "Audiophonics PCM5122 DAC" || 
				$_POST['i2s'] == "HiFiBerry Amp(Amp+)" ||
				$_POST['i2s'] == "HiFiBerry DAC+" || 
				$_POST['i2s'] == "HiFiBerry DAC+ Pro" || 
				$_POST['i2s'] == "IQaudIO Pi-DAC" ||
				$_POST['i2s'] == "IQaudIO Pi-DAC+" ||
				$_POST['i2s'] == "IQaudIO Pi-DigiAMP+" ||
				$_POST['i2s'] == "PlainDAC+" ||
				$_POST['i2s'] == "RaspyPlay4") {
				$_SESSION['notify']['msg'] = $_SESSION['notify']['msg']."<br><br>This device supports hardware volume control. After rebooting, optionally set MPD Volume control to Hardware.";
=======
	return false;

































>>>>>>> .theirs
			}

/**
 * Update on/off session setting
 */
function sessionUpdate($switch, $setting, &$val, &$oldval) {
	if (isset($_POST[$switch]) && isset($_POST[$setting])) {
		$val = $_POST[$setting];
		$oldval = $_SESSION[$setting];

		if ($val != $oldval) {
			// TODO move update to daemon.php or after pushing worker task
			Session::update($setting, $val);
			return true;
		}
	} 

	return false;
}


Session::open();

$workerSuccess = null;	// true/false indicates worker call success
$skipWait = false;		// allow skip waiting for worker

// theme change via system command
if (isset($_POST['syscmd'])) {
	$themes = array('alizarin', 'amethyst', 'bluejeans', 'carrot', 'emerald', 'fallenleaf', 'grass', 'herb', 'lavender', 'river', 'rose', 'turquoise');
	if (in_array($theme = $_POST['syscmd'], $themes)) {
		$workerSuccess = workerPushTask("themechange", $theme);
		}
	} 


/*
 * Session value updates
 */
$val = $oldval = null;

if (sessionUpdate('update_kernel_version', 'kernelver', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('kernelver', $val)) {
		uiSetNotification('Kernel change', "Version " . $val . " install initiated...<br><br>The process can take 5+ minutes to<br>complete after which the CONNECTING<br>screen will appear and the system will<br>be POWERED OFF.", 600);
		// dont wait if kernel select so page returns and uiShowNotification message appears
		$skipWait = true;
}
		}
if (sessionUpdate('update_time_zone', 'timezone', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('timezone', $val)) {
		uiSetNotification('Setting change', "Timezone " . $val . " has been set.", 4);
	} 
}
if (sessionUpdate('update_latency_setting', 'orionprofile', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('orionprofile', $val)) {
		uiSetNotification('Setting change', 'Kernel latency setting has been changed to: '.$val.', REBOOT for setting to take effect.', 4);
		}
		}
if (sessionUpdate('update_browser_title', 'browser_title', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('browser_title', '"' . $oldval . '" "' . $val . '"')) {
		uiSetNotification('Setting change', "Browser title has been changed, REBOOT for setting to take effect.", 4);
	} 
}
if (sessionUpdate('update_airplay_name', 'airplay_name', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('airplay_name', '"' . $oldval . '" "' . $val . '"')) {
		uiSetNotification('Setting change', "Airplay receiver name has been changed, REBOOT for setting to take effect.", 4);
	}
	}
if (sessionUpdate('update_upnp_name', 'upnp_name', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('upnp_name', '"' . $oldval . '" "' . $val . '"')) {
		uiSetNotification('Setting change', "UPnP renderer name has been changed, REBOOT for setting to take effect.", 4);
} 
	}
if (sessionUpdate('update_dlna_name', 'dlna_name', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('dlna_name', '"' . $oldval . '" "' . $val . '"')) {
		uiSetNotification('Setting change', "DLNA server name has been changed, REBOOT for setting to take effect.", 4);
	}
} 
if (sessionUpdate('update_pcm_volume', 'pcm_volume', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('pcm_volume', $val)) {
		uiSetNotification('Setting change', "PCM volume has been set.", 4);
	}
	}
if (sessionUpdate('update_host_name', 'host_name', $val, $oldval)) {
	if (preg_match("/[^A-Za-z0-9-]/", $val) == 1) {
		uiSetNotification('Invalid input', "Host name can only contain A-Z, a-z, 0-9 or hyphen (-).", 4);
} 
	else {
		if ($workerSuccess = workerPushTask('host_name', '"' . $oldval . '" "' . $val . '"')) {
			uiSetNotification('Setting change', "Host name has been changed, REBOOT for setting to take effect.", 4);
	}
	}
} 
if (sessionUpdate('update_i2s_device', 'i2s', $val, $oldval)) {
	if ($workerSuccess = workerPushTask('i2sdriver', $val)) {
		uiSetNotification('Setting change', "I2S device has been changed, REBOOT for setting to take effect.", 5);

		// TC (Tim Curtis) 2015-03-21: Adjust message depending on selected device
		if ($val == "IQaudIO Pi-AMP+") {
			uiSetNotification('', "<br><br>This device REQUIRES hardware volume control. After rebooting, set MPD Volume control to Hardware.");
			}
		elseif (in_array($val, array(
			"HiFiBerry DAC+",
			"HiFiBerry Amp(Amp+)",
			"IQaudIO Pi-DAC",
			"IQaudIO Pi-DAC+",
			"RaspyPlay4"
		))) {
			uiSetNotification('', "<br><br>This device supports hardware volume control. After rebooting, optionally set MPD Volume control to Hardware.");
		} 
	}
}


/*
 * On/off settings
 */

// shairport
if (sessionToggle('shairport')) {
	uiSetNotification('Setting change', ($_POST['shairport'] == 1)
		? 'Airplay receiver enabled, REBOOT for setting to take effect.'
		: 'Airplay receiver disabled, REBOOT for setting to take effect.',
	4);
		}

// upnp
if (sessionToggle('upnpmpdcli')) {
	uiSetNotification('Setting change', ($_POST['upnpmpdcli'] == 1)
		? 'UPnP renderer enabled, REBOOT for setting to take effect.'
		: 'UPnP renderer disabled, REBOOT for setting to take effect.',
	4);
	} 

// djmount
if (sessionToggle('djmount')) {
	uiSetNotification('Setting change', ($_POST['djmount'] == 1)
		? 'DLNA server enabled, REBOOT for setting to take effect.'
		: 'DLNA server disabled, REBOOT for setting to take effect.',
	4);
		}


/*
 * One-time tasks
 */

if (isset($_POST['update_clear_syslogs']) && $_POST['clearsyslogs'] == 1) {
	if ($workerSuccess = workerPushTask('clearsyslogs')) {
		uiSetNotification('Log maintenance', "System logs have been cleared.", 4);
		}
	}
if (isset($_POST['update_clear_playhistory']) && $_POST['clearplayhistory'] == 1) {
	if ($workerSuccess = workerPushTask('clearplayhistory')) {
		uiSetNotification('Log maintenance', "Playback history log hase been cleared.", 4);
}
		}
if (isset($_POST['update_expand_sdcard']) && $_POST['expandsdcard'] == 1) {
	if ($workerSuccess = workerPushTask('expandsdcard')) {
		uiSetNotification('Expand SD Card Storage', "Storage expansion request has been queued. REBOOT has been initiated.", 6);
	}
}


if (false === $workerSuccess) {
	uiSetNotification('Job failed', 'Background worker is busy');
		}

// TC (Tim Curtis) 2015-08-30: max usb current 2x
if (isset($_POST['maxusbcurrent']) && $_POST['maxusbcurrent'] != $_SESSION['maxusbcurrent']) {
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		playerSession('write',$db,'maxusbcurrent',$_POST['maxusbcurrent']);

		$_SESSION['w_queue'] = 'maxusbcurrent';
		$_SESSION['w_queueargs'] = $_POST['maxusbcurrent'];
		$_SESSION['w_active'] = 1;
		$_SESSION['notify']['duration'] = 4; // secs
	
		if ($_POST['maxusbcurrent'] == 1) {
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = 'Max USB current set to 2x, REBOOT for setting to take effect.';
		} else {
			$_SESSION['notify']['title'] = 'Setting change';
			$_SESSION['notify']['msg'] = 'Max USB current set to 1x, REBOOT for setting to take effect.';
		}
		
		playerSession('unlock');
	} else {
		echo "background worker busy";
	}
}

// TC (Tim Curtis) 2015-08-30: rotary encoder
if (isset($_POST['rotaryenc']) && $_POST['rotaryenc'] != $_SESSION['rotaryenc']) {
	session_start();
	if ($_POST['rotaryenc'] == 1 OR $_POST['rotaryenc'] == 0) {
		playerSession('write',$db,'rotaryenc',$_POST['rotaryenc']);
		
		// write to tcmods.conf file to allow vol knob disable check in scripts-playback.js
		$_tcmods_conf = _parseTcmodsConf(shell_exec('cat /var/www/tcmods.conf')); // read
		$_tcmods_conf['rotary_encoder_enabled'] = $_POST['rotaryenc']; // update setting
		$rtn = _updTcmodsConf($_tcmods_conf); // write
	}
	$_SESSION['notify']['duration'] = 4; // secs

	if ($_POST['rotaryenc'] == 1) {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'Rotary encoder enabled, REBOOT for setting to take effect.';
	} else {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'Rotary encoder disabled, REBOOT for setting to take effect.';
	}
	playerSession('unlock');
} 

// TC (Tim Curtis) 2015-10-DD: autoplay after start
if (isset($_POST['autoplay']) && $_POST['autoplay'] != $_SESSION['autoplay']) {
	session_start();
	if ($_POST['autoplay'] == 1 OR $_POST['autoplay'] == 0) {
		playerSession('write',$db,'autoplay',$_POST['autoplay']);
	}
	$_SESSION['notify']['duration'] = 4; // secs

	if ($_POST['autoplay'] == 1) {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'Autoplay after player start up enabled.';
	} else {
		$_SESSION['notify']['title'] = 'Setting change';
		$_SESSION['notify']['msg'] = 'Autoplay after player start up disabled.';
	}
	playerSession('unlock');
} 

// configure html select elements
// TC (Tim Curtis) 2015-10-DD: add Audiophonics PCM5122 DAC, Lucid Labs Raspberry Pi DAC, PlainDAC, PlainDAC+ and HifiBerry DAC+ Pro 
// TC (Tim Curtis) 2015-10-DD: add check for kernel 4.1.10+
$kernelver = getKernelVer($_SESSION['kernelver']);
if ($kernelver == '3.18.5+' || $kernelver == '3.18.11+' || $kernelver == '3.18.14+' || $kernelver == '4.1.10+') {
	$dacs = array(
		'I2S Off',
		'Audiophonics I-Sabre DAC',
		'Audiophonics PCM5122 DAC',
		'Durio Sound PRO',
		'G2 Labs BerryNOS',
		'G2 Labs BerryNOS Red',
		'HiFiBerry Amp(Amp+)',
		'HiFiBerry DAC',
		'HiFiBerry DAC+',
		'HiFiBerry DAC+ Pro',
		'HiFiBerry Digi(Digi+)',
		'Hifimediy ES9023',
		'IQaudIO Pi-AMP+',
		'IQaudIO Pi-DAC',
		'IQaudIO Pi-DAC+',
		'IQaudIO Pi-DigiAMP+',
		'Lucid Labs Raspberry Pi DAC',
		'PlainDAC',
		'PlainDAC+',
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
// TC (Tim Curtis) 2015-08-30: updated text
if ($_SESSION['pcm_volume'] == 'none') {
	$_pcm_volume = '';
	$_pcm_volume_readonly = 'readonly';
	$_pcm_volume_hide = 'hide';
	$_pcm_volume_msg = "<span class=\"help-block help-block-margin\">Hardware volume controller not detected</span>";
}
else {
	// TC (Tim Curtis) 2015-06-26: get current volume setting, requires www-data user in visudo
	// TC (Tim Curtis) 2015-06-26: set simple mixer name based on kernel version and i2s vs USB
	$mixername = getMixerName($kernelver, $_SESSION['i2s']);
	$cmd = "sudo /var/www/tcmods/".MOODE_RELEASE."/cmds/tcmods.sh get-pcmvol ".$mixername;

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
// TC (Tim Curtis) 2015-10-DD: add 4.1.10 kernel to select list
if ($_SESSION['procarch'] == "armv7l") { // Pi-2
	$_linux_kernel['kernelver'] .= "<option value=\"4.1.10-v7+\" ".(($_SESSION['kernelver'] == '4.1.10-v7+') ? "selected" : "").">4.1.10-v7+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.14-v7+\" ".(($_SESSION['kernelver'] == '3.18.14-v7+') ? "selected" : "").">3.18.14-v7+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.11-v7+\" ".(($_SESSION['kernelver'] == '3.18.11-v7+') ? "selected" : "").">3.18.11-v7+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.5-v7+\" ".(($_SESSION['kernelver'] == '3.18.5-v7+') ? "selected" : "").">3.18.5-v7+</option>\n";
}
else if ($_SESSION['procarch'] == "armv6l") { // Pi-1 and Pi-B+
	$_linux_kernel['kernelver'] .= "<option value=\"4.1.10+\" ".(($_SESSION['kernelver'] == '4.1.10+') ? "selected" : "").">4.1.10+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.14+\" ".(($_SESSION['kernelver'] == '3.18.14+') ? "selected" : "").">3.18.14+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.11+\" ".(($_SESSION['kernelver'] == '3.18.11+') ? "selected" : "").">3.18.11+</option>\n";
	$_linux_kernel['kernelver'] .= "<option value=\"3.18.5+\" ".(($_SESSION['kernelver'] == '3.18.5+') ? "selected" : "").">3.18.5+</option>\n";
}
else {
	$_linux_kernel['kernelver'] .= "<option value=\"Unknown Arch\" "."selected".">None</option>\n"; 
}

// kernel tweak profiles
$_system_select['orionprofile'] = "<option value=\"Default\" ".(($_SESSION['orionprofile'] == 'Default') ? "selected" : "").">Default</option>\n";
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
// TC (Tim Curtis) 2015-08-30: max usb current 2x
$_system_select['maxusbcurrent1'] .= "<input type=\"radio\" name=\"maxusbcurrent\" id=\"togglemaxusbcurrent1\" value=\"1\" ".(($_SESSION['maxusbcurrent'] == 1) ? "checked=\"checked\"" : "").">\n";
$_system_select['maxusbcurrent0'] .= "<input type=\"radio\" name=\"maxusbcurrent\" id=\"togglemaxusbcurrent2\" value=\"0\" ".(($_SESSION['maxusbcurrent'] == 0) ? "checked=\"checked\"" : "").">\n";

// TC (Tim Curtis) 2015-08-30: rotary encoder
$_system_select['rotaryenc1'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"togglerotaryenc1\" value=\"1\" ".(($_SESSION['rotaryenc'] == 1) ? "checked=\"checked\"" : "").">\n";
$_system_select['rotaryenc0'] .= "<input type=\"radio\" name=\"rotaryenc\" id=\"togglerotaryenc2\" value=\"0\" ".(($_SESSION['rotaryenc'] == 0) ? "checked=\"checked\"" : "").">\n";

// TC (Tim Curtis) 2015-10-DD: autoplay after start
$_system_select['autoplay1'] .= "<input type=\"radio\" name=\"autoplay\" id=\"toggleautoplay1\" value=\"1\" ".(($_SESSION['autoplay'] == 1) ? "checked=\"checked\"" : "").">\n";
$_system_select['autoplay0'] .= "<input type=\"radio\" name=\"autoplay\" id=\"toggleautoplay2\" value=\"0\" ".(($_SESSION['autoplay'] == 0) ? "checked=\"checked\"" : "").">\n";

// TC (Tim Curtis) 2015-04-29: timezones
$_timezone['timezone'] = buildTimezoneSelect($_SESSION['timezone']);


// close session for waitWorker()
Session::close();


if (!$skipWait) {
	waitWorker();
}


render("settings");
