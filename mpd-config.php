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


Session::open();

$workerSuccess = false;

// Handle reset
if (isset($_POST['reset']) && $_POST['reset'] == 1) {
	$mpdconfdefault = ConfigDB::read('', 'mpdconfdefault');

	foreach ($mpdconfdefault as $element) {
		ConfigDB::update('cfg_mpd',$element['param'],$element['value_default']);
	}

	// Tell worker to write new MPD config
	if ($workerSuccess = workerPushTask('mpdcfg')) {
		uiSetNotification('MPD config reset', 'Restarting MPD server...');
	}
}

// Handle restart (same as process for mpdcfg)
if (isset($_POST['mpdrestart']) && $_POST['mpdrestart'] == 1) {
	// Tell worker to write new MPD config
	if ($workerSuccess = workerPushTask('mpdcfg')) {
		uiSetNotification('MPD restart', 'MPD restarted');
	}
}

// Handle POST
if (isset($_POST['conf']) && !empty($_POST['conf'])) {
	foreach ($_POST['conf'] as $key => $value) {
		ConfigDB::update('cfg_mpd',$key,$value);
	}

	// Tell worker to write new MPD config
	if ($workerSuccess = workerPushTask('mpdcfg')) {
		uiSetNotification('MPD config modified', 'Restarting MPD server...');
	}
}

// Handle manual config
if (isset($_POST['mpdconf']) && !empty($_POST['mpdconf'])) {
	// tell worker to write new MPD config
	if ($workerSuccess = workerPushTask('mpdcfgman', $_POST['mpdconf'])) {
		uiSetNotification('MPD config modified', 'Restarting MPD server...');
	}
}

// could not start worker job
if (false === $workerSuccess) {
	uiSetNotification('Job failed', 'Background worker is busy');
}


Session::close();


// Wait for worker
waitWorker();


$mpdconf = ConfigDB::read('','mpdconf');
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

// Parse output for template $_mpdconf
foreach ($mpdconf as $key => $value) {
	foreach ($_mpd as $key2 => $value2) {
		if ($value['param'] == $key2) {
			$_mpd[$key2] = $value['value_player'];
		}
	}
}

function getDeviceName($file) {
	$dev = rtrim(@file_get_contents($file));

	switch ($dev) {
		case "":
			// no device
			return "";
		case "DAC":
		case "CODEC":
		case "Interf":
		case "x20":
		case "G1V5":
		case "Audio":
			return "USB audio device";
			break;
		case "ALSA":
			return "On-board audio device";
			break;
		default:
			// default device
			return "I2S audio device";
	}
}

// Output device names
$dev1 = getDeviceName('/proc/asound/card0/id');
$dev2 = getDeviceName('/proc/asound/card1/id');
$dev3 = getDeviceName('/proc/asound/card2/id');


// Load template values

// Audio output device
// TC (Tim Curtis) 2015-04-29: add a bit of logic
$_mpd_select['device'] .= "<option value=\"0\" ".(($_mpd['device'] == '0') ? "selected" : "")." >$dev1</option>\n";
$_mpd_select['device'] .= "<option value=\"1\" ".(($_mpd['device'] == '1') ? "selected" : "")." >$dev2</option>\n";
$_mpd_select['device'] .= "<option value=\"2\" ".(($_mpd['device'] == '2') ? "selected" : "")." >$dev3</option>\n";

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

// Audio output format (none or sample rate conversion)
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
$_mpd_select['samplerate_converter'] .= "<option value=\"soxr medium\" ".(($_mpd['samplerate_converter'] == 'soxr medium') ? "selected" : "")." >SoX: Medium Quality</option>\n";
$_mpd_select['samplerate_converter'] .= "<option value=\"soxr high\" ".(($_mpd['samplerate_converter'] == 'soxr high') ? "selected" : "")." >SoX: High Quality</option>\n";
$_mpd_select['samplerate_converter'] .= "<option value=\"soxr very high\" ".(($_mpd['samplerate_converter'] == 'soxr very high') ? "selected" : "")." >SoX: Very High Quality</option>\n";
// SRC (Secret Rabbit Code)
$_mpd_select['samplerate_converter'] .= "<option value=\"Fastest Sinc Interpolator\" ".(($_mpd['samplerate_converter'] == 'Fastest Sinc Interpolator') ? "selected" : "")." >SRC: Low Quality</option>\n";
$_mpd_select['samplerate_converter'] .= "<option value=\"Medium Sinc Interpolator\" ".(($_mpd['samplerate_converter'] == 'Medium Sinc Interpolator') ? "selected" : "")." >SRC: Medium Quality</option>\n";
$_mpd_select['samplerate_converter'] .= "<option value=\"Best Sinc Interpolator\" ".(($_mpd['samplerate_converter'] == 'Best Sinc Interpolator') ? "selected" : "")." >SRC: Best Quality</option>\n";


// TC (Tim Curtis) 2015-04-29: is this code used anymore?
if (wrk_checkStrSysfile('/proc/asound/card0/pcm0p/info','bcm2835')) {
	$_audioout = "<select id=\"audio-output-interface\" name=\"conf[audio-output-interface]\" class=\"input-large\">\n";
	$_audioout .= "<option value=\"jack\">Analog Jack</option>\n";
	$_audioout .= "<option value=\"hdmi\">HDMI</option>\n";
	$_audioout .= "</select>\n";
	$_audioout .= "<span class=\"help-block\">Select MPD Audio output interface</span>\n";
}
else {
	$_audioout .= "<input class=\"input-large\" class=\"input-large\" type=\"text\" id=\"port\" name=\"\" value=\"USB Audio\" data-trigger=\"change\" disabled>\n";
}

waitWorker();

render("mpd-config");
