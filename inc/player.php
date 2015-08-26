<?php
/**
 *  PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 *	Tsunamp Team
 *  http://www.tsunamp.com
 *
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
 * Rewrite by Tim Curtis and Andreas Goetz
 */

// Predefined MPD Response messages
define("MPD_RESPONSE_ERR", "ACK");
define("MPD_RESPONSE_OK",  "OK");

require_once dirname(__FILE__) . '/../inc/Session.php';
require_once dirname(__FILE__) . '/../inc/ConfigDB.php';

function openMpdSocket($host, $port) {
	if (false === ($sock = stream_socket_client('tcp://'.$host.':'.$port, $errorno, $errorstr, MPD_TIMEOUT))) {
		// just log, don't die()
		error_log('Error: could not connect to MPD');
	}
	else {
		$response = readMpdResponse($sock);
	}
	return $sock;
}

function closeMpdSocket($sock) {
	sendMpdCommand($sock, "close");
	fclose($sock);
}

function sendMpdCommand($sock, $cmd) {
	fputs($sock, $cmd . "\n");
}

function readMpdResponse($sock) {
	$res = '';

	while (!feof($sock)) {
		$str = fgets($sock, 1024);

		if (strncmp(MPD_RESPONSE_OK, $str, strlen(MPD_RESPONSE_OK)) == 0) {
			return $res;
		}
		if (strncmp(MPD_RESPONSE_ERR, $str, strlen(MPD_RESPONSE_ERR)) == 0) {
			return false;
		}

		$res .= $str;
	}

	return $res;
}

function execMpdCommand($sock, $command) {
	sendMpdCommand($sock, $command);
	return readMpdResponse($sock);
}

function chainMpdCommands($sock, $commands) {
	$res = '';

	foreach ($commands as $command) {
		$res .= execMpdCommand($sock, $command);
	}

	return $res;
}

function mpdStatus($sock) {
	return execMpdCommand($sock, "status");
}

function mpdMonitorState($sock) {
	execMpdCommand($sock, "idle");
	return _parseStatusResponse(mpdStatus($sock));
}

/*
 * Current playlist functions
 * http://www.musicpd.org/doc/protocol/queue.html
 */
function mpdQueueInfo($sock) {
	$resp = execMpdCommand($sock, "playlistinfo");
	return _parseFileListResponse($resp);
}

function mpdQueueTrackInfo($sock, $id) {
	$resp = execMpdCommand($sock, "playlistinfo " . $id);
	return _parseFileListResponse($resp);
}

function mpdQueueRemoveTrack($sock, $id) {
	$resp = execMpdCommand($sock, "delete " . $id);
	return $resp;
}

function mpdQueueAdd($sock, $path) {
	$ext = pathinfo($path, PATHINFO_EXTENSION);
	$cmd = ($ext == 'm3u' || $ext == 'pls' || strpos($path, '/') === false) ? 'load' : 'add';

	$resp = execMpdCommand($sock, $cmd . ' "' . html_entity_decode($path) . '"');
	return $resp;
}

function mpdQueueAddMultiple($sock, $songs) {
	$commands = array();
	foreach ($songs as $song) {
		array_push($commands, 'add "' . html_entity_decode($song) . '"');
	}
	return chainMpdCommands($sock, $commands);
}

/*
 * Stored playlist functions
 * http://www.musicpd.org/doc/protocol/playlist_files.html
 */
function mpdListPlayList($sock, $plname) {
	$resp = execMpdCommand($sock, 'listplaylist "' . $plname . '"');
	return _parseFileListResponse($resp);
}

function mpdRemovePlayList($sock, $plname) {
	$resp = execMpdCommand($sock, 'rm "' . $plname . '"');
	return $resp;
}

function libLog($str, $overwrite = false) {
	$debug_fhand = fopen("/var/www/liblog.txt", $overwrite ? 'w' : 'a'); // write or append
	fwrite($debug_fhand, $str."\n");
	fclose($debug_fhand);
}

// TC (Tim Curtis) 2015-06-26: add debug logging
function loadAllLib($sock) {
	$cmd = "find modified-since " . ($debug_flags[4] == "2") ? "1901-01-01T00:00:00Z" : "36500";
	$lib = array();
	$item = array();

	foreach (explode("\n", execMpdCommand($sock, $cmd)) as $line) {
		list($key, $val) = explode(": ", $line, 2);
		if ($key == "file") {
			if (count($item)) {
				_libAddItem($lib, $item);
				$item = array();
			}
		}

		$item[$key] = $val;
	}

	if (count($item)) {
		_libAddItem($lib, $item);
		$item = array();
	}

	return $lib;
}

function _libAddItem(&$lib, $item) {
	$genre = isset($item["Genre"]) ? $item["Genre"] : "Unknown";
	$artist = isset($item["Artist"]) ? $item["Artist"] : "Unknown";
	$album = isset($item["Album"]) ? $item["Album"] : "Unknown";

	if (!isset($lib[$genre])) {
		$lib[$genre] = array();
	}
	if (!isset($lib[$genre][$artist])) {
		$lib[$genre][$artist] = array();
	}
	if (!isset($lib[$genre][$artist][$album])) {
		$lib[$genre][$artist][$album] = array();
	}

	$libItem = array(
		"file" => $item['file'],
		"display" => (isset($item['Track']) ? $item['Track']." - " : "") . isset($item['Title']) ? $item['Title'] : '',
		"time" => isset($item['Time']) ? $item['Time'] : 0,
		"time2" => songTime(isset($item['Time']) ? $item['Time'] : 0)
	);

	array_push($lib[$genre][$artist][$album], $libItem);
}

function searchDB($sock, $type, $query = '') {
	if ('' !== $query) {
		$query = ' "' . html_entity_decode($query) . '"';
	}

	switch ($type) {
		case "filepath":
			$resp = execMpdCommand($sock, "lsinfo" . $query);
			break;
		case "album":
		case "artist":
		case "title":
		case "file":
			$resp = execMpdCommand($sock, "search " . $type . $query);
			break;
	}

	return _parseFileListResponse($resp);
}

function songTime($sec) {
	$minutes = sprintf('%02d', floor($sec / 60));
	$seconds = sprintf(':%02d', (int) $sec % 60);
	return $minutes.$seconds;
}

function sysCmd($syscmd) {
	exec($syscmd." 2>&1", $output);
	return $output;
}

/**
 * Parse MPD response into key => value pairs
 */
function parseMpdKeyedResponse($resp, $separator = ': ') {
	$res = array();

	foreach (explode("\n", $resp) as $line) {
		if (strpos($line, $separator)) { // skip lines without separator
			list ($key, $val) = explode($separator, $line, 2);
			$res[$key] = $val;
		}
	}

	return $res;
}

/**
 * Return formatted MPD player status
 */
function _parseStatusResponse($resp) {
	if (is_null($resp)) {
		return NULL;
	}

	$status = parseMpdKeyedResponse($resp);

	// "elapsed time song_percent" added to output array
	$percent = 0;
	if (isset($status['time'])) {
		$time = explode(":", $status['time']);
		if ($time[1] > 0) {
			$percent = round(($time[0]*100) / $time[1]);
		}
		$status["elapsed"] = $time[0];
		$status["time"] = $time[1];
	}

	$status["song_percent"] = $percent;

	 // "audio format" output
	if (isset($status['audio'])) {
		$status += parseAudioFormat($status['audio']);
	}

	return $status;
}

/**
 * Parse MPD playlist
 */
function _parseFileListResponse($resp) {
	$res = array();
	$item = array();
	$directoryIndex = -1;

	foreach (explode("\n", $resp) as $line) {
		if (false === strpos($line, ': ')) {
			continue;
		}

		list($key, $val) = explode(': ', $line, 2);
		if (sizeof($item) && in_array($key, array('file', 'playlist', 'directory'))) {
			$res[] = $item;
			$item = array();
		}

		switch ($key) {
			case 'playlist':
				// treat webradio playlists as files
				if (substr($val, 0, 8) !== "WEBRADIO") {
					break;
				}
				$key = 'file';
				// fallthrough
			case 'file':
				$item["fileext"] = pathinfo($val, PATHINFO_EXTENSION);
				break;
			case 'directory':
				$directoryIndex = sizeof($res);
				break;
			case 'Time':
				$item['Time2'] = songTime($val);
		}

		$item[$key] = $val;
	}

	// add final item to array
	if (sizeof($item)) {
		$res[] = $item;
	}

	// reverse MPD list output
	if ($directoryIndex >= 0) {
		$dir = array_splice($res, $directoryIndex);
		$res = $dir + $res;
	}

	return $res;
}

/**
 * Parse MPD current song
 */
function _parseMpdCurrentSong($resp) {
	if (is_null($resp)) {
		return 'Error, _parseMpdCurrentSong response is null';
	}

	$res = parseMpdKeyedResponse($resp);
	return $res;
}

/**
 * Convert sample rate to easily readable format
 */
function formatSampleRate($raw) {
	switch ($raw) {
		// integer format
		case '32000':
		case '48000':
		case '96000':
		case '192000':
		case '384000':
			$res = rtrim(rtrim(number_format($raw),0), ', ');
			break;
		// decimal format
		case '22050':
			$res = '22.05';
			break;
		case '44100':
		case '88200':
		case '176400':
		case '352800':
			$res = rtrim(number_format($raw,0, ', ', '.'),0);
			break;
		default:
			$res = '';
	}
	return $res;
}

/**
 * Convert number of channels to readable format
 */
function formatChannels($raw) {
	$raw = (int)$raw;
	if ($raw == 2)
		$res = "Stereo";
	elseif ($raw == 1)
		$res = "Mono";
	elseif ($raw > 2)
		$res = "Multichannel";
	else
		$res = '';
	return $res;
}

/**
 * Convert number of channels to readable format
 */
function parseAudioFormat($raw) {
	$audio_format = explode(":", $raw);
	$res = array(
		'audio_sample_rate' => formatSampleRate($audio_format[0]),
		'audio_sample_depth' => $audio_format[1],
		'audio_channels' => formatChannels($audio_format[2])
	);
	return $res;
}

function uiSetNotification($title, $msg, $duration = 2) {
	$_SESSION['notify'] = array(
		'title' => $title,
		'msg' => $msg,
		'duration' => $duration
	);
}

function uiShowNotification($notify) {
	$str = <<<EOT
<script>
jQuery(document).ready(function() {
	$.pnotify({
		title: '%s',
		text: '%s',
		icon: 'icon-ok',
		delay: '%d',
		opacity: .9,
		history: false
	});
});
</script>
EOT;

	echo sprintf($str, $notify['title'], $notify['msg'], isset($notify['duration'])
		? $notify['duration'] * 1000
		: 2000
	);
}

// OUTPUT: parse HW_PARAMS
function getHwParams() {
	if (false === ($resp = file_get_contents('/proc/asound/card0/pcm0p/sub0/hw_params'))) {
		die('Error, _parseHwParams response is null');
	}

	if ($resp != "closed\n") {
		$res = parseMpdKeyedResponse($resp, ': ');

		// format sample rate, ex: "44100 (44100/1)"
		$rate = substr($res['rate'], 0, strpos($res['rate'], ' ('));
		$res['rate'] = formatSampleRate($rate);
		// format sample depth, ex "S24_3LE"
		$res['format'] = substr($res['format'], 1, 2);
		$_bits = (float)$res['format'];
		$_chans = (float)$res['channels'];

		$res['channels'] = formatChannels($res['channels']);

		$res['status'] = 'active';
		$res['calcrate'] = number_format((((float)$rate * $_bits * $_chans) / 1000000),3, '.', '');
	}
	else {
		$res['status'] = 'closed';
		$res['calcrate'] = '0 bps';
	}
	return $res;
}

// DSP: parse MPD Conf
function _parseMpdConf() {
	// prepare array
	$_mpd = array(
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

	// read in mpd conf settings
	$mpdconf = ConfigDB::read('', 'mpdconf');

	// parse output for template
	foreach ($mpdconf as $key => $value) {
		if (in_array($value['param'], array_keys($_mpd))) {
			$_mpd[$value['param']] = $value['value_player'];
		}
	}

	// parse audio output format, ex "44100:16:2"
	$_mpd += parseAudioFormat($_mpd['audio_output_format']);

	return $_mpd;
}

// /var/www/tcmods.conf
function getTcmodsConf() {
	if (false === ($conf = file_get_contents('/var/www/tcmods.conf'))) {
		die('Failed to read tcmods.conf');
	}

	// split config lines
	return parseMpdKeyedResponse($conf, ": ");
}



function _updTcmodsConf($tcmconf) {
	$keys = array(
		'albumart_lookup_method',
		'audio_device_name',
		'audio_device_dac',
		'audio_device_arch',
		'audio_device_iface',
		'audio_device_other',
		'clock_radio_enabled',
		'clock_radio_playitem',
		'clock_radio_playname',
		'clock_radio_starttime',
		'clock_radio_stoptime',
		'clock_radio_volume',
		'clock_radio_shutdown',
		'play_history_currentsong',
		'play_history_enabled',
		'search_autofocus_enabled',
		'sys_kernel_ver',
		'sys_processor_arch',
		'sys_mpd_ver',
		'time_knob_countup',
		'theme_color',
		'volume_curve_factor',
		'volume_curve_logarithmic',
		'volume_knob_setting',
		'volume_max_percent',
		'volume_mixer_type',
		'volume_muted',
		'volume_warning_limit'
	);

	$data = '';
	foreach ($keys as $key) {
		$data .= $key . ': ' . $tcmconf[$key]."\n";
	}

	if (false === file_put_contents('/var/www/tcmods.conf', $data)) {
		die('Failed to write tcmods.conf');
	}

	return '_updTcmodsConf: update tcmods.conf complete';
}


function getTemplate($template) {
	return str_replace("\"", "\\\"",implode("",file($template)));
}

function echoTemplate($template) {
	echo $template;
}


// TC (Tim Curtis) 2015-05-30: update play history log
function _updatePlayHistory($currentsong) {
	// Open file for write w/append
	$_file = '/var/www/playhistory.log';
	if (false === ($handle = fopen($_file, 'a'))) {
		error_log('tcmods.php: file open failed on '.$_file);
	}

	// Append data, close file
	fwrite($handle, $currentsong."\n");
	fclose($handle);

	return '_updatePlayHistory: update playhistory.log complete';
}

function _setI2sDtoverlay($device) {
	if ($device == 'I2S Off') {
		_setI2sModules('I2S Off');
	}
	else {
		$text = "# Device Tree Overlay being used\n";
		file_put_contents('/etc/modules', $text);

		switch ($device) {
			case 'Generic': 				// use hifiberry driver
			case 'G2 Labs BerryNOS':		// use hifiberry driver
			case 'G2 Labs BerryNOS Red':	// use hifiberry driver
			case 'Durio Sound PRO':
			case 'Hifimediy ES9023':
			case 'Audiophonics I-Sabre DAC ES9023 TCXO':
			case 'HiFiBerry DAC':
				sysCmd('echo dtoverlay=hifiberry-dac >> /boot/config.txt');
				break;
			case 'HiFiBerry DAC+':
				sysCmd('echo dtoverlay=hifiberry-dacplus >> /boot/config.txt');
				break;
			case 'HiFiBerry Digi(Digi+)':
				sysCmd('echo dtoverlay=hifiberry-digi >> /boot/config.txt');
				break;
			case 'HiFiBerry Amp(Amp+)':
				sysCmd('echo dtoverlay=hifiberry-amp >> /boot/config.txt');
				break;
			case 'RaspyPlay4':
			case 'IQaudIO Pi-DAC':
				sysCmd('echo dtoverlay=iqaudio-dac >> /boot/config.txt');
				break;
			case 'IQaudIO Pi-DAC+':
			case 'IQaudIO Pi-AMP+':
			case 'IQaudIO Pi-DigiAMP+':
				sysCmd('echo dtoverlay=iqaudio-dacplus >> /boot/config.txt');
				break;
			case 'RPi DAC': // exception since there is no dtoverlay driver for this dac in 3.18
				sysCmd('echo dtoverlay= >> /boot/config.txt');
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "snd_soc_bcm2708_i2s\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm5102a\n";
				$text .= "snd_soc_rpi_dac\n";
				file_put_contents($file, $text);
				break;
		}
	}
}

// TC (Tim Curtis) 2015-02-25: for pre 3.18 kernels
function _setI2sModules($device) {
	$text = "# ". $device."\n";
	$text .= "snd_soc_bcm2708\n";
	$text .= "bcm2708_dmaengine\n";

	switch ($device) {
		case 'I2S Off':
			$text = "# I2S output deactivated\n";
			$text .= "snd-bcm2835\n";
			break;
		case 'G2 Labs BerryNOS':
		case 'G2 Labs BerryNOS Red':
		case 'HiFiBerry DAC':
			$text .= "snd_soc_pcm5102a\n";
			$text .= "snd_soc_hifiberry_dac\n";
			break;
		case 'HiFiBerry DAC+':
			$text .= "snd_soc_pcm512x\n";
			$text .= "snd_soc_hifiberry_dacplus\n";
			break;
		case 'HiFiBerry Digi(Digi+)':
			$text .= "snd_soc_hifiberry_digi\n";
			break;
		case 'HiFiBerry Amp(Amp+)':
			$text .= "snd_soc_hifiberry_amp\n";
			break;
		case 'IQaudIO Pi-DAC':
		case 'IQaudIO Pi-DAC+':
			$text .= "snd_soc_bcm2708_i2s\n";
			$text .= "snd_soc_pcm512x\n";
			$text .= "snd_soc_iqaudio_dac\n";
			break;
		case 'RPi DAC':
			$text .= "snd_soc_bcm2708_i2s\n";
			$text .= "snd_soc_pcm5102a\n";
			$text .= "snd_soc_rpi_dac\n";
			break;
		case 'Generic':
			$text = "# Generic I2S driver\n";
			$text .= "snd_soc_bcm2708\n";
			$text .= "bcm2708_dmaengine\n";
			$text .= "snd_soc_bcm2708_i2s\n";
			$text .= "snd_soc_pcm5102a\n";
			$text .= "snd_soc_pcm512x\n";
			$text .= "snd_soc_hifiberry_dac\n";
			$text .= "snd_soc_rpi_dac\n";
			break;
	}

	file_put_contents('/etc/modules', $text);
}

// TC (Tim Curtis) 2015-06-26: return kernel version number without "-v7" suffix
function getKernelVer($kernel) {
	return str_replace('-v7', '', $kernel);
}

// TC (Tim Curtis) 2015-06-26: return mixer name based on kernel version and i2s vs USB
// TC (Tim Curtis) 2015-06-26: set mixer name to "Master" for Hifiberry Amp(Amp+)
function getMixerName($kernelver, $i2s) {
	if ($i2s != 'I2S Off') {
		if ($i2s == 'HiFiBerry Amp(Amp+)') {
			$mixername = 'Master'; // Hifiberry Amp(Amp+) i2s device
		}
		else {
			$mixername = ($kernelver == '3.18.11+' || $kernelver == '3.18.14+')
				? 'Digital' // default for these kernels
				: 'PCM'; // default for 3.18.5+
		}
	}
	else {
		$mixername = 'PCM'; // USB devices
	}

	return $mixername;
}

function waitWorker($sleeptime = 1, $mpdUpdate = false) {
	logWorker('[client] waiting for worker');

	$wait = 0;
	if ($_SESSION['w_active'] == 1) {
		do {
			sleep($sleeptime);
			logWorker('[client] waitWorker (' . $wait++ . ')');
			logWorker('$_SESSION[w_active]' . $_SESSION['w_active']);
			session_start();
			session_write_close();
		}
		while ($_SESSION['w_active'] != 0);

		// update MPD db after worker finishes
		if ($mpdUpdate) {
			$mpd = openMpdSocket(MPD_HOST, 6600);
			execMpdCommand($mpd, 'update');
			closeMpdSocket($mpd);
		}
	}

	logWorker('[client] worker finished');
}

function logWorker($o) {
	if (false !== ($f = @fopen('/var/log/worker.log', 'a'))) {
		if (in_array(gettype($o), array("array", "object"))) {
			$o = print_r($o, true);
		}

		fwrite($f, $o . "\n");
		fclose($f);
	}
}
