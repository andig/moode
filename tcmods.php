<?php
/*
 *	This Program is free software; you can redistribute it and/or modify
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
 *  along with this software. If not, refer to the following link.
 *  http://www.gnu.org/licenses/
 *
 * Rewrite by Tim Curtis and Andreas Goetz
 */

require_once dirname(__FILE__) . '/inc/connection.php';

playerSession('open',$db,'','');
playerSession('unlock',$db,'','');
$dbh = cfgdb_connect($db);
session_write_close();


if (isset($_POST['syscmd'])) {
	switch ($_POST['syscmd']) {
		// Power off and reboot
		case 'poweroff':
			if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
				// start / respawn session
				session_start();
				$_SESSION['w_queue'] = "poweroff";
				$_SESSION['w_active'] = 1;
				// Set UI notify
				$_SESSION['notify']['title'] = 'Shutdown';
				$_SESSION['notify']['msg'] = 'System shutdown initiated...';
			} else {
				echo "Background worker is busy";
			}
			// Unlock session file
			playerSession('unlock');
			// Set template html
			$tpl = "poweroff.html";
			break;

		case 'reboot':
			if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
				// Start / respawn session
				session_start();
				$_SESSION['w_queue'] = "reboot";
				$_SESSION['w_active'] = 1;
				// Set UI notify
				$_SESSION['notify']['title'] = 'Reboot';
				$_SESSION['notify']['msg'] = 'System reboot initiated...';
			} else {
				echo "Background worker is busy";
			}
			// Unlock session file
			playerSession('unlock');
			// Set template html
			$tpl = "reboot.html";
			break;
		// TC (Tim Curtis) 2014-12-23: reload clock radio settings from conf file
		case 'reloadclockradio':
			if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
				// Start / respawn session
				session_start();
				$_SESSION['w_queue'] = "reloadclockradio";
				$_SESSION['w_active'] = 1;
			} else {
				echo "Background worker is busy";
			}
			// Unlock session file
			playerSession('unlock');
			break;
		// TC (Tim Curtis) 2015-05-30: reload tcmods config settings
		case 'reloadtcmodsconf':
			if ($_SESSION['w_lock'] != 1 && $_SESSION['w_queue'] == '') {
				// Start / respawn session
				session_start();
				$_SESSION['w_queue'] = "reloadtcmodsconf";
				$_SESSION['w_active'] = 1;
			} else {
				echo "Background worker is busy";
			}
			// Unlock session file
			playerSession('unlock');
			break;
	}

	// Display template if not clock radio reload or tcmods conf reload
	if (!($_POST['syscmd'] == 'reloadclockradio' || $_POST['syscmd'] == 'reloadtcmodsconf')) {
		$sezione = basename(__FILE__, '.php');
		include('_header.php');
		include('_footer.php');
		// TC (Tim Curtis) 2014-08-23: waitworker(1) does not seem to be needed for pwroff/reboot actions
		// wait for worker output if $_SESSION['w_active'] = 1
		// waitWorker(1);
		eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
	}

// TC (Tim Curtis) 2014-11-30: read contents of tcmods.conf
// TC (Tim Curtis) 2014-11-30: return mpd status
// TC (Tim Curtis) 2014-12-23: update contents of tcmods.conf
// TC (Tim Curtis) 2014-12-23: read contents of radio station file
// TC (Tim Curtis) 2015-01-27: query cfg_logourl for station logo url
// TC (Tim Curtis) 2015-01-27: add search_autofocus_enabled
// TC (Tim Curtis) 2015-02-25: move updatetcmconf code to _updTcmodsConf() function in player_lib.php
// TC (Tim Curtis) 2015-03-21: query cfg_audiodev for audio device description
// TC (Tim Curtis) 2015-05-30: add play history read
// TC (Tim Curtis) 2015-05-30: add get upnp coverart url
// TC (Tim Curtis) 2015-07-31: add get radio station info
} else if (isset($_GET['cmd']) && $_GET['cmd'] != '') {
	$cmd = $_GET['cmd'];
	switch ($cmd) {
		case 'getaudiodevdesc':
			$result = cfgdb_read('cfg_audiodev', $dbh, $_POST['audiodev']);
			echo json_encode($result[0]);
			break;
		case 'getradioinfo':
			$result = cfgdb_read('cfg_radio', $dbh, $_POST['station']);
			echo json_encode($result[0]);
			break;

		case 'getupnpcoverurl':
			$cmd = "upexplorer --album-art \"".$_SESSION['upnp_name']."\"";
			$rtn = sysCmd($cmd);
			//error_log("getupnpcoverurl cmd= <".$cmd.">", 0); // debug
			//error_log("getupnpcoverurl rtn= <".$rtn[0].">", 0); // debug
			echo $rtn[0];
			break;

		case 'readtcmconf':
			echo json_encode(_parseTcmodsConf(shell_exec('cat /var/www/tcmods.conf')));
			break;

		case 'updatetcmconf':
			echo json_encode(_updTcmodsConf($_POST));
			break;

		case 'getmpdstatus':
			echo json_encode(_parseStatusResponse(MpdStatus($mpd)));
			break;

		case 'readstationfile':
			echo json_encode(_parseStationFile(shell_exec("cat \""."/var/lib/mpd/music/".$_POST['path']."\"")));
			break;

		case 'readplayhistory':
			echo json_encode(_parsePlayHistory(shell_exec('cat /var/www/playhistory.log')));
			break;

		// TC (Tim Curtis) 2015-06-26: TESTING ALSA-Direct volume control, requires www-data user in visudo
		case 'sendalsacmd':
			$mixername = getMixerName(getKernelVer($_SESSION['kernelver']), $_SESSION['i2s']);

			$cmd = "sudo ".$_POST['alsacmd']." ".$mixername." ".$_POST['volumelevel'].$_POST['scale'];
			$rtn = sysCmd($cmd);
			echo json_encode($rtn[0]);

			// debug
			//error_log("setalsavolume cmd= <".$cmd.">", 0);
			//error_log("setalsavolume rtn= <".$rtn[0].">", 0);
			break;

	} // End switch

} else {
	// Show audio information
	// Audio Info header btn href has no value= element which is how we get here

	// OUTPUT INFO: hw_params, actual audio output format sent to DAC
	$_hwparams = _parseHwParams(shell_exec('cat /proc/asound/card0/pcm0p/sub0/hw_params'));
	// TC (Tim Curtis) 2015-06-26: comment out to make room for Volume settings under DSP INFO
	//$audioinfo_hwparams_status = $_hwparams['status'];
	if ($_hwparams['status'] == 'active') {
		$audioinfo_hwparams_format = $_hwparams['channels'];
		$audioinfo_hwparams_format .= ", ";
		$audioinfo_hwparams_format .= $_hwparams['format'];
		$audioinfo_hwparams_format .= " bit, ";
		$audioinfo_hwparams_format .= $_hwparams['rate'];
		$audioinfo_hwparams_format .= " kHz";
		$audioinfo_hwparams_calcrate = $_hwparams['calcrate'];
		$audioinfo_hwparams_calcrate .= " mbps";
	} else {
		$audioinfo_hwparams_format = '';
		$audioinfo_hwparams_calcrate = '0 bps';
	}

	// INPUT INFO: mpd currentsong and status cmds
	if (!$mpd) {
        $audioinfo_mpdstatus = 'Error Connecting to MPD daemon';
	} else {
		// mpd currentsong
		sendMpdCommand($mpd, 'currentsong');
		$resp = readMpdResponse($mpd);
		$_mpdcurrentsong = _parseMpdCurrentSong($resp);

		$audioinfo_mpdcurrentsong_file = $_mpdcurrentsong['file'];
		// mpd status
		$_mpdstatus = _parseStatusResponse(MpdStatus($mpd));
		if ($_hwparams['status'] == 'active') {
		// source format
		$audioinfo_mpdstatus_format = $_mpdstatus['audio_channels'];
		$audioinfo_mpdstatus_format .= ", ";
		// TC (Tim Curtis) 2015-07-31: format when "dsd" (for dsf files)
		$audioinfo_mpdstatus_format .= $_mpdstatus['audio_sample_depth'];
		if ($_mpdstatus['audio_sample_depth'] == "dsd") {
			$audioinfo_mpdstatus_format .= ", ";
		} else {
			$audioinfo_mpdstatus_format .= " bit, ";
		}
		$audioinfo_mpdstatus_format .= $_mpdstatus['audio_sample_rate'];
		$audioinfo_mpdstatus_format .= " kHz";
		// bit rate
		$audioinfo_mpdstatus_bitrate .= $_mpdstatus['bitrate'];
		$audioinfo_mpdstatus_bitrate .= " kbps";
		} else {
			$audioinfo_mpdstatus_format = '';
			$audioinfo_mpdstatus_bitrate .= "0 bps";
		}
	}

	// DSP INFO: mpd.conf, configured SRC output format and converter
	// TC (Tim Curtis) 2015-06-26: add Volume settings from tcmods.conf
	$_tcmodsconf = _parseTcmodsConf(shell_exec('cat /var/www/tcmods.conf'));
	$_mpdconf = _parseMpdConf($dbh);
	if ($_mpdconf['audio_channels'] != '') {
		$audioinfo_mpdconf_src = $_mpdconf['samplerate_converter'];
		$audioinfo_mpdconf_format = $_mpdconf['audio_channels'];
		$audioinfo_mpdconf_format .= ", ";
		$audioinfo_mpdconf_format .= $_mpdconf['audio_sample_depth'];
		$audioinfo_mpdconf_format .= " bit, ";
		$audioinfo_mpdconf_format .= $_mpdconf['audio_sample_rate'];
		$audioinfo_mpdconf_format .= " kHz";
	} else {
		$audioinfo_mpdconf_src = 'off';
		$audioinfo_mpdconf_format = '';
	}
	if ($_tcmodsconf['volume_mixer_type'] == "hardware") {
		if ($_tcmodsconf['volume_curve_logarithmic'] == "Yes") {
			$curve_type = "Logarthmic curve";
			if ($_tcmodsconf['volume_curve_factor'] == 56) {$curve_slope = "Standard slope,";}
			else if ($_tcmodsconf['volume_curve_factor'] == 66) {$curve_slope = "Less (-10) slope,";}
			else if ($_tcmodsconf['volume_curve_factor'] == 76) {$curve_slope = "Less (-20) slope,";}
			else if ($_tcmodsconf['volume_curve_factor'] == 86) {$curve_slope = "Less (-30) slope,";}
			else if ($_tcmodsconf['volume_curve_factor'] == 50) {$curve_slope = "More (+06) slope,";}
			else if ($_tcmodsconf['volume_curve_factor'] == 44) {$curve_slope = "More (+12) slope,";}
			else if ($_tcmodsconf['volume_curve_factor'] == 38) {$curve_slope = "More (+18) slope,";}
		} else {
			$curve_type = "Linear curve";
			$curve_slope = '';
		}
		$audioinfo_tcmodsconf_volume = "Hardware, ".$curve_type.", ".$curve_slope." Vol-max ".$_tcmodsconf['volume_max_percent']."%";
	} else if ($_tcmodsconf['volume_mixer_type'] == "software") {
		$audioinfo_tcmodsconf_volume = "Software (MPD 32 bit float with dither)";
	}

	// DEVICE INFO: tcmods.conf, audio device description (manually entered by user)
	// TC (Tim Curtis) 2014-11-30: fix bug: change .= to =
	// TC (Tim Curtis 2015-06-26: comment out, moved to DSP INFO section
	//$_tcmodsconf = _parseTcmodsConf(shell_exec('cat /var/www/tcmods.conf'));
	$audioinfo_tcmodsconf_device_name = $_tcmodsconf['audio_device_name'];
	$audioinfo_tcmodsconf_device_dac = $_tcmodsconf['audio_device_dac'];
	$audioinfo_tcmodsconf_device_arch = $_tcmodsconf['audio_device_arch'];
	$audioinfo_tcmodsconf_device_iface = $_tcmodsconf['audio_device_iface'];
	$audioinfo_tcmodsconf_device_other = $_tcmodsconf['audio_device_other'];

	// SYSTEM INFO: architecture, cpu util, temp and freq
	$_cpuload = shell_exec("top -bn 2 -d 0.5 | grep 'Cpu(s)' | tail -n 1 | awk '{print $2 + $4 + $6}'");
	$systeminfo_cpuload = number_format($_cpuload,0,'.','');
	$systeminfo_cputemp = substr(shell_exec('cat /sys/class/thermal/thermal_zone0/temp'), 0, 2);
	$_cpufreq = (float)shell_exec('cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq');
	if ($_cpufreq < 1000000) {
		$_cpufreq = $_cpufreq / 1000;
		$systeminfo_cpufreq = number_format($_cpufreq,0,'.','');
		$systeminfo_cpufreq .= " MHz";
	} else {
		$_cpufreq = $_cpufreq / 1000000;
		$systeminfo_cpufreq = number_format($_cpufreq,1,'.','');
		$systeminfo_cpufreq .= " GHz";
	}
	// TC (Tim Curtis) 2015-02-25: processor architecture
	$systeminfo_arch = trim(shell_exec('uname -m'));

	// unlock session file
	playerSession('unlock');
	// Set template html
	$tpl = "audioinfo.html";
	eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
}
