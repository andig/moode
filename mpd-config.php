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
 *	file:					mpd-config.php
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 *  TC (Tim Curtis) 2014-08-23, r1.0
 *  - add new sample rates
 *
 *  TC (Tim Curtis) 2014-12-23, r1.3
 *  - remove trailing ! in 1st content line causing code to be grayed out in editor 
 *	- add new sample rates 176400:16:2 and 176400:24:2
 *	- shovel & broom
 *
 *	TC (Tim Curtis) 2015-02-25 r1.6
 *	- add SoX prefixes to samplerate_converter
 *	- update SRC text for dropdown
 *
 *	TC (Tim Curtis) 2015-04-29 r1.8
 *	- add friendly names for audio output
 *	- shovel & broom
 *
 *	TC (Tim Curtis) 2015-05-30 r1.9
 *	- add friendly name check for "Interf" (Wyred4Sound DAC)
 *	- add friendly name check for "x20" (Eastern Electric Minimax Junior DAC)
 *
 *	TC (Tim Curtis) 2015-06-26 r2.0
 *	- add friendly name check for "G1V5" (Geek Pulse X-Fi DAC)
 *
 *	TC (Tim Curtis) 2015-07-31 r2.1
 *	- add friendly name check for "Audio" (CM6631A USB/SPDIF converter)
 *
 *	TC (Tim Curtis) 2015-08-30 r2.2
 *	- new logic for making text for Audio output field
 *	- set vol = 0 if mixer 'disabled', prior to restarting MPD
 *	
 *
 */
 
// Common include
include('inc/connection.php');
playerSession('open',$db,'',''); 
$dbh = cfgdb_connect($db);
session_write_close();
?>

<?php
// Handle reset
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	$mpdconfdefault = cfgdb_read('',$dbh,'mpdconfdefault');
	
	foreach($mpdconfdefault as $element) {
		cfgdb_update('cfg_mpd',$dbh,$element['param'],$element['value_default']);
	}
	// Tell worker to write new MPD config
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		$_SESSION['w_queue'] = "mpdcfg";
		$_SESSION['w_active'] = 1;
		// Set UI notify
		$_SESSION['notify']['title'] = 'MPD config reset';
		$_SESSION['notify']['msg'] = 'Restarting MPD server...';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Job failed';
		$_SESSION['notify']['msg'] = 'Background worker is busy';
		session_write_close();
	}
	
	unset($_POST);
}
// Handle restart (same as process for mpdcfg)
if (isset($_POST['mpdrestart']) && $_POST['mpdrestart'] == 1) {
	// Tell worker to write new MPD config
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		$_SESSION['w_queue'] = "mpdcfg";
		$_SESSION['w_active'] = 1;
		// Set UI notify
		$_SESSION['notify']['msg'] = 'MPD restarted';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Job failed';
		$_SESSION['notify']['msg'] = 'Background worker is busy';
		session_write_close();
	}
	
	unset($_POST);
}

// Handle POST
if(isset($_POST['conf']) && !empty($_POST['conf'])) {
	foreach ($_POST['conf'] as $key => $value) {
		cfgdb_update('cfg_mpd',$dbh,$key,$value);
	}

	// TC (Tim Curtis) 2015-08-30: set vol = 0 if mixer 'disabled'
	if ($_POST['conf']['mixer_type'] == 'disabled') {
		sendMpdCommand($mpd, 'setvol 0');
		$resp = readMpdResponse($mpd);
	}

	// Tell worker to write new MPD config
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		$_SESSION['w_queue'] = "mpdcfg";
		$_SESSION['w_active'] = 1;
		// Set UI notify
		$_SESSION['notify']['title'] = 'MPD config modified';
		$_SESSION['notify']['msg'] = 'Restarting MPD server...';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Job failed';
		$_SESSION['notify']['msg'] = 'Background worker is busy';
		session_write_close();
	}
}
	
// Handle manual config
if(isset($_POST['mpdconf']) && !empty($_POST['mpdconf'])) {
	// tell worker to write new MPD config
	if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
		session_start();
		$_SESSION['w_queue'] = "mpdcfgman";
		$_SESSION['w_queueargs'] = $_POST['mpdconf'];
		$_SESSION['w_active'] = 1;
		// Set UI notify
		$_SESSION['notify']['title'] = 'MPD config modified';
		$_SESSION['notify']['msg'] = 'Restarting MPD server...';
		session_write_close();
	} else {
		session_start();
		$_SESSION['notify']['title'] = 'Job Failed';
		$_SESSION['notify']['msg'] = 'Background worker is busy';
		session_write_close();
	}
}

// Wait for worker output if $_SESSION['w_active'] = 1
waitWorker(1);

// Check integrity of /etc/mpd.conf
if(!hashCFG('check_mpd',$db)) {
	$_mpdconf = file_get_contents('/etc/mpd.conf');
	// Set manual config template
	$tpl = "mpd-config-manual.html";
} else {
	$mpdconf = cfgdb_read('',$dbh,'mpdconf');
	// Prepare array
	$_mpd = array (
		'port' => '',
		'gapless_mp3_playback' => '',
		'auto_update' => '',
		'samplerate_converter' => '',
		'auto_update_depth' => '',
		'zeroconf_enabled' => '',
		'zeroconf_name' => '',
		'audio_output_format' => '',
		'mixer_type' => '',
		'audio_buffer_size' => '',
		'buffer_before_play' => '',
		'dsd_usb' => '',
		'device' => '',
		'volume_normalization' => ''
	);

	// debug($mpdconf);							
	// Parse output for template $_mpdconf
	foreach ($mpdconf as $key => $value) {
		foreach ($_mpd as $key2 => $value2) {
			if ($value['param'] == $key2) {
				$_mpd[$key2] = $value['value_player'];	
			}
		}
	}

	// Output device names
	$dev1 = file_get_contents('/proc/asound/card0/id');
	$dev2 = file_get_contents('/proc/asound/card1/id');
	$dev3 = file_get_contents('/proc/asound/card2/id');
	
	// TC (Tim Curtis) 2015-08-30: new logic for making text for Audio output field
	if ($dev1 == "") {} else if ($dev1 == "ALSA\n") {$dev1 = "On-board audio device";} else if ($_SESSION['i2s'] != "I2S Off") {$dev1 = "I2S audio device";} else {$dev1 = "USB audio device";}
	if ($dev2 == "") {} else if ($dev2 == "ALSA\n") {$dev2 = "On-board audio device";} else if ($_SESSION['i2s'] != "I2S Off") {$dev2 = "I2S audio device";} else {$dev2 = "USB audio device";}
	if ($dev3 == "") {} else if ($dev3 == "ALSA\n") {$dev3 = "On-board audio device";} else if ($_SESSION['i2s'] != "I2S Off") {$dev3 = "I2S audio device";} else {$dev3 = "USB audio device";}
	
	// Load template values

	// Audio output device
	// TC (Tim Curtis) 2015-04-29: add a bit of logic
	if ($dev1 != "") {$_mpd_select['device'] .= "<option value=\"0\" ".(($_mpd['device'] == '0') ? "selected" : "")." >$dev1</option>\n";}
	if ($dev2 != "") {$_mpd_select['device'] .= "<option value=\"1\" ".(($_mpd['device'] == '1') ? "selected" : "")." >$dev2</option>\n";}
	if ($dev3 != "") {$_mpd_select['device'] .= "<option value=\"2\" ".(($_mpd['device'] == '2') ? "selected" : "")." >$dev3</option>\n";}
	// TC (Tim Curtis) 2015-04-29: comment out
	//$_mpd_select['device'] .= "<option value=\"3\" ".(($_mpd['device'] == '3') ? "selected" : "")." >$dev4</option>\n";
	
	// Volume control
	$_mpd_select['mixer_type'] .= "<option value=\"disabled\" ".(($_mpd['mixer_type'] == 'none' OR $_mpd['mixer_type'] == '') ? "selected" : "").">disabled</option>\n";
	$_mpd_select['mixer_type'] .= "<option value=\"hardware\" ".(($_mpd['mixer_type'] == 'hardware') ? "selected" : "").">Hardware</option>\n";
	$_mpd_select['mixer_type'] .= "<option value=\"software\" ".(($_mpd['mixer_type'] == 'software') ? "selected" : "").">Software</option>\n";
	
	// Gapless mp3 playback
	$_mpd_select['gapless_mp3_playback'] .= "<option value=\"yes\" ".(($_mpd['gapless_mp3_playback'] == 'yes') ? "selected" : "")." >yes</option>\n";	
	$_mpd_select['gapless_mp3_playback'] .= "<option value=\"no\" ".(($_mpd['gapless_mp3_playback'] == 'no') ? "selected" : "")." >no</option>\n";
	
	// DSD audio support
	$_mpd_select['dsd_usb'] .= "<option value=\"yes\" ".(($_mpd['dsd_usb'] == 'yes') ? "selected" : "")." >yes</option>\n";	
	$_mpd_select['dsd_usb'] .= "<option value=\"no\" ".(($_mpd['dsd_usb'] == 'no') ? "selected" : "")." >no</option>\n";	
	
	// Volume normalization
	$_mpd_select['volume_normalization'] .= "<option value=\"yes\" ".(($_mpd['volume_normalization'] == 'yes') ? "selected" : "")." >yes</option>\n";	
	$_mpd_select['volume_normalization'] .= "<option value=\"no\" ".(($_mpd['volume_normalization'] == 'no') ? "selected" : "")." >no</option>\n";	
	
	// Audio buffer size
	// $_mpd[audio_buffer_size]
	
	// Buffer fill percentage before play
	$_mpd_select['buffer_before_play'] .= "<option value=\"0%\" ".(($_mpd['buffer_before_play'] == '0%') ? "selected" : "")." >disabled</option>\n";	
	$_mpd_select['buffer_before_play'] .= "<option value=\"10%\" ".(($_mpd['buffer_before_play'] == '10%') ? "selected" : "")." >10%</option>\n";	
	$_mpd_select['buffer_before_play'] .= "<option value=\"20%\" ".(($_mpd['buffer_before_play'] == '20%') ? "selected" : "")." >20%</option>\n";	
	$_mpd_select['buffer_before_play'] .= "<option value=\"30%\" ".(($_mpd['buffer_before_play'] == '30%') ? "selected" : "")." >30%</option>\n";	
	
	// Auto MPD DB update
	$_mpd_select['auto_update'] .= "<option value=\"yes\" ".(($_mpd['auto_update'] == 'yes') ? "selected" : "").">yes</option>\n";	
	$_mpd_select['auto_update'] .= "<option value=\"no\" ".(($_mpd['auto_update'] == 'no') ? "selected" : "").">no</option>\n";
	
	// Zeroconf enabled
	$_mpd_select['zeroconf_enabled'] .= "<option value=\"yes\" ".(($_mpd['zeroconf_enabled'] == 'yes') ? "selected" : "").">yes</option>\n";
	$_mpd_select['zeroconf_enabled'] .= "<option value=\"no\" ".(($_mpd['zeroconf_enabled'] == 'no') ? "selected" : "").">no</option>\n";
	
	// Zeroconf name
	// $_mpd[zeroconf_name]
	
	// Audio output format (none or sample rate conversion)
	// TC (Tim Curtis) 2014-08-23: added new sample rates 48000:16:2, 88200:16:2, 48000:24:2, 88200:24:2
	// TC (Tim Curtis) 2014-12-23: added new sample rates 176400:16:2 and 176400:24:2
	$_mpd_select['audio_output_format'] .= "<option value=\"disabled\" ".(($_mpd['audio_output_format'] == 'disabled' OR $_mpd['audio_output_format'] == '') ? "selected" : "").">disabled</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"44100:16:2\" ".(($_mpd['audio_output_format'] == '44100:16:2') ? "selected" : "").">16 bit / 44.1 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"48000:16:2\" ".(($_mpd['audio_output_format'] == '48000:16:2') ? "selected" : "").">16 bit / 48 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"88200:16:2\" ".(($_mpd['audio_output_format'] == '88200:16:2') ? "selected" : "").">16 bit / 88.2 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"96000:16:2\" ".(($_mpd['audio_output_format'] == '96000:16:2') ? "selected" : "").">16 bit / 96 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"176400:16:2\" ".(($_mpd['audio_output_format'] == '176400:16:2') ? "selected" : "").">16 bit / 176.4 kHz</option>\n";

	$_mpd_select['audio_output_format'] .= "<option value=\"44100:24:2\" ".(($_mpd['audio_output_format'] == '44100:24:2') ? "selected" : "").">24 bit / 44.1 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"48000:24:2\" ".(($_mpd['audio_output_format'] == '48000:24:2') ? "selected" : "").">24 bit / 48 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"88200:24:2\" ".(($_mpd['audio_output_format'] == '88200:24:2') ? "selected" : "").">24 bit / 88.2 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"96000:24:2\" ".(($_mpd['audio_output_format'] == '96000:24:2') ? "selected" : "").">24 bit / 96 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"176400:24:2\" ".(($_mpd['audio_output_format'] == '176400:24:2') ? "selected" : "").">24 bit / 176.4 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"192000:24:2\" ".(($_mpd['audio_output_format'] == '192000:24:2') ? "selected" : "").">24 bit / 192 kHz</option>\n";

	$_mpd_select['audio_output_format'] .= "<option value=\"44100:32:2\" ".(($_mpd['audio_output_format'] == '44100:32:2') ? "selected" : "").">32 bit / 44.1 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"96000:32:2\" ".(($_mpd['audio_output_format'] == '96000:32:2') ? "selected" : "").">32 bit / 96 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"192000:32:2\" ".(($_mpd['audio_output_format'] == '192000:32:2') ? "selected" : "").">32 bit / 192 kHz</option>\n";
	$_mpd_select['audio_output_format'] .= "<option value=\"384000:32:2\" ".(($_mpd['audio_output_format'] == '384000:32:2') ? "selected" : "").">32 bit / 384 kHz</option>\n";
	
	// Samplerate converter
	// TC (Tim Curtis) 2015-02-25: added SoX prefixes
	// TC (Tim Curtis) 2015-02-25: updated SRC text
	// SoX
	$_mpd_select['samplerate_converter'] .= "<option value=\"soxr medium\" ".(($_mpd['samplerate_converter'] == 'soxr medium') ? "selected" : "")." >SoX: Medium Quality</option>\n";	
	$_mpd_select['samplerate_converter'] .= "<option value=\"soxr high\" ".(($_mpd['samplerate_converter'] == 'soxr high') ? "selected" : "")." >SoX: High Quality</option>\n";	
	$_mpd_select['samplerate_converter'] .= "<option value=\"soxr very high\" ".(($_mpd['samplerate_converter'] == 'soxr very high') ? "selected" : "")." >SoX: Very High Quality</option>\n";	
	// SRC (Secret Rabbit Code) 
	$_mpd_select['samplerate_converter'] .= "<option value=\"Fastest Sinc Interpolator\" ".(($_mpd['samplerate_converter'] == 'Fastest Sinc Interpolator') ? "selected" : "")." >SRC: Low Quality</option>\n";	
	$_mpd_select['samplerate_converter'] .= "<option value=\"Medium Sinc Interpolator\" ".(($_mpd['samplerate_converter'] == 'Medium Sinc Interpolator') ? "selected" : "")." >SRC: Medium Quality</option>\n";	
	$_mpd_select['samplerate_converter'] .= "<option value=\"Best Sinc Interpolator\" ".(($_mpd['samplerate_converter'] == 'Best Sinc Interpolator') ? "selected" : "")." >SRC: Best Quality</option>\n";	
	
	// Set normal config template
	$tpl = "mpd-config.html";
}


// Close DB connection
$dbh = null;
// Unlock session files
playerSession('unlock',$db,'','');

// TC (Tim Curtis) 2015-04-29: is this code used anymore?
if (wrk_checkStrSysfile('/proc/asound/card0/pcm0p/info','bcm2835')) {
	$_audioout = "<select id=\"audio-output-interface\" name=\"conf[audio-output-interface]\" class=\"input-large\">\n";
	//$_audioout .= "<option value=\"disabled\">disabled</option>";
	$_audioout .= "<option value=\"jack\">Analog Jack</option>\n";
	$_audioout .= "<option value=\"hdmi\">HDMI</option>\n";
	$_audioout .= "</select>\n";
	$_audioout .= "<span class=\"help-block\">Select MPD Audio output interface</span>\n";
} else {
	$_audioout .= "<input class=\"input-large\" class=\"input-large\" type=\"text\" id=\"port\" name=\"\" value=\"USB Audio\" data-trigger=\"change\" disabled>\n";
}
?>

<?php
$sezione = basename(__FILE__, '.php');
include('_header.php'); 
?>

<!-- TC (Tim Curtis) 2014-11-30: remove trailing ! in 1st content line causing code to be grayed out in editor -->
<!-- CONTENT -->
<?php
// Wait for worker output if $_SESSION['w_active'] = 1
waitWorker(1);
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
?>
<!-- CONTENT -->

<?php 
// debug($_POST);
?>

<?php include('_footer.php'); ?>
