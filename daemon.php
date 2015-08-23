#!/usr/bin/php5
<?php
/**
 * PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 * Tsunamp Team
 * http://www.tsunamp.com
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

// Common TCMODS
$TCMODS_REL = "r21"; // Current release, used in path for theme change
$TCMODS_CONSUMEMODE_ON = "1"; // Used for run-once fix for mpd consume mode sometimes being on after boot/reboot
$TCMODS_CLOCKRAD_RETRY = 3; // Num times to retry the stop cmd

// Common include
require_once dirname(__FILE__) . '/../inc/player.php';
require_once dirname(__FILE__) . '/../inc/worker.php';

ini_set('display_errors', '1');
ini_set('error_log', '/var/log/php_errors.log');
$db = 'sqlite:/var/www/db/player.db';
error_reporting(E_ALL);

// command line options
$options = getopt('th', array('test', 'help'));
$opt_test = isset($options['t']) || isset($options['test']);

$lock = fopen('/run/player_wrk.pid', 'c+');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
	die('already running');
}

// --- DEMONIZE --- only if not in test mode
if (false === $opt_test) {
	switch ($pid = pcntl_fork()) {
		case -1:
			die('unable to fork');
		case 0: // This is the child process
			break;
		default: // Otherwise this is the parent process
			fseek($lock, 0);
			ftruncate($lock, 0);
			fwrite($lock, $pid);
			fflush($lock);
			exit;
	}

	if (posix_setsid() === -1) {
		die('could not setsid');
	}

	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);

	$stdIn = fopen('/dev/null', 'r'); // set fd/0
	$stdOut = fopen('/dev/null', 'w'); // set fd/1
	$stdErr = fopen('php://stdout', 'w'); // a hack to duplicate fd/1 to 2

	pcntl_signal(SIGTSTP, SIG_IGN);
	pcntl_signal(SIGTTOU, SIG_IGN);
	pcntl_signal(SIGTTIN, SIG_IGN);
	pcntl_signal(SIGHUP, SIG_IGN);
}

// --- INITIALIZE ENVIRONMENT --- //
// reset file permissions
sysCmd('chmod 777 /run');
sysCmd('chmod 777 /run/sess*');
sysCmd('chmod 777 /var/lib/mpd/music/WEBRADIO/*.*');
sysCmd('chmod 777 /var/lib/mpd/music/SDCARD');
sysCmd('chmod 777 /var/www/tcmods.conf');
sysCmd('chmod 777 /var/www/playhistory.log');
sysCmd('chmod 777 /var/www/liblog.txt');
sysCmd('chmod -R 777 /var/www/db');
// mount all sources
wrk_sourcemount($db, 'mountall');

// start MPD daemon
sysCmd("service mpd start");

// - set symlink for album art lookup
sysCmd("ln -s /var/lib/mpd/music /var/www/coverroot");


// load session
playerSession('open', $db, '', '');

// make sure session vars are set
$vars = array('w_active', 'w_lock', 'w_queue', 'w_queueargs', 'debug', 'debugdata');
foreach ($vars as $var) {
	if (!isset($_SESSION[$var])) {
		$_SESSION[$var] = '';
	}
}


logWorker("[daemon] Startup");
logWorker("[daemon] w_active    " . $_SESSION['w_active']);
logWorker("[daemon] w_lock      " . $_SESSION['w_lock']);
logWorker("[daemon] w_queue     " . $_SESSION['w_queue']);
logWorker("[daemon] w_queueargs " . print_r($_SESSION['w_queueargs'],1));

// check Architecture
$arch = wrk_getHwPlatform();
if ($arch != $_SESSION['hwplatformid']) {
	// reset playerID if architectureID not match. This condition "fire" another first-install process
	playerSession('write', $db, 'playerid', '');
}
// --- END INITIALIZE ENVIRONMENT --- //

// --- PLAYER FIRST INSTALLATION PROCESS --- //
if (isset($_SESSION['playerid']) && $_SESSION['playerid'] == '') {
	// register HW architectureID and playerID
	wrk_setHwPlatform($db);
	// destroy actual session
	playerSession('destroy', $db, '', '');
	// reload session data
	playerSession('open', $db, '', '');
	// reset ENV parameters
	wrk_sysChmod();

	// reset netconf to defaults
	$value = array('ssid' => '', 'encryption' => '', 'password' => '');
	$dbh = cfgdb_connect($db);
	cfgdb_update('cfg_wifisec', $dbh, '', $value);
	$file = '/etc/network/interfaces';
	$fp = fopen($file, 'w');
	$netconf = "auto lo\n";
	$netconf .= "iface lo inet loopback\n";
	$netconf .= "\n";
	$netconf .= "auto eth0\n";
	$netconf .= "iface eth0 inet dhcp\n";
	$netconf .= "\n";
	//$netconf .= "auto wlan0\n";
	//$netconf .= "iface wlan0 inet dhcp\n";
	fwrite($fp, $netconf);
	fclose($fp);
	// update hash
	$hash = md5_file('/etc/network/interfaces');
	playerSession('write', $db, 'netconfhash', $hash);
	// restart wlan0 interface
	if (strpos($netconf, 'wlan0') != false) {
		$cmd = "ip addr list wlan0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1";
		$ip_wlan0 = sysCmd($cmd);
		if (!empty($ip_wlan0[0])) {
			$_SESSION['netconf']['wlan0']['ip'] = $ip_wlan0[0];
		}
		else {
			$_SESSION['netconf']['wlan0']['ip'] = wrk_checkStrSysfile('/proc/net/wireless', 'wlan0')
				? '--- NO IP ASSIGNED ---'
				: '--- NO INTERFACE PRESENT ---';
		}
	}

	sysCmd('service networking restart');

	// reset sourcecfg to defaults
	wrk_sourcecfg($db, 'reset');
	sendMpdCommand($mpd, 'update');

	// reset mpdconf to defaults
	$mpdconfdefault = cfgdb_read('', $dbh, 'mpdconfdefault');
	foreach($mpdconfdefault as $element) {
		cfgdb_update('cfg_mpd', $dbh, $element['param'], $element['value_default']);
	}

	// tell worker to write new MPD config
	wrk_mpdconf('/etc', $db);
	sysCmd('service mpd restart');
	$dbh = null;

	// disable minidlna / samba / MPD startup
	sysCmd("update-rc.d -f minidlna remove");
	sysCmd("update-rc.d -f ntp remove");
	sysCmd("update-rc.d -f smbd remove");
	sysCmd("update-rc.d -f nmbd remove");
	sysCmd("update-rc.d -f mpd remove");
	sysCmd("echo 'manual' > /etc/init/minidlna.override");
	sysCmd("echo 'manual' > /etc/init/ntp.override");
	sysCmd("echo 'manual' > /etc/init/smbd.override");
	sysCmd("echo 'manual' > /etc/init/nmbd.override");
	sysCmd("echo 'manual' > /etc/init/mpd.override");

	// stop services
	sysCmd('service minidlna stop');
	//sysCmd('service minidlna ntp'); // TC (Tim Curtis) 2015-04-29: bug?
	sysCmd('service samba stop');
	sysCmd('service mpd stop');
// --- END PLAYER FIRST INSTALLATION PROCESS --- //
}

// TC (Tim Curtis) 2015-07-31: shovel & broom change to /etc/samba/smb.conf instead of _OS_SETTINGS/etc/...
sysCmd('/usr/sbin/smbd -D --configfile=/var/www/etc/samba/smb.conf');
sysCmd('/usr/sbin/nmbd -D --configfile=/var/www/etc/samba/smb.conf');

// initialize kernel profile
if ($_SESSION['dev'] == 0) {
	$cmd = "/var/www/command/orion_optimize.sh ".$_SESSION['orionprofile']." startup" ;
	sysCmd($cmd);
}

// check current eth0 / wlan0 IP Address
$ip_eth0 = sysCmd("ip addr list eth0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
$ip_wlan0 = sysCmd("ip addr list wlan0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
$ip_fallback = "192.168.10.110";

// check IP for minidlna assignment and add to session
if (isset($ip_eth0) && !empty($ip_eth0)) {
	$ip = $ip_eth0[0];
	$_SESSION['netconf']['eth0']['ip'] = $ip;
}
elseif (isset($ip_wlan0) && !empty($ip_wlan0)) {
	$ip = $ip_wlan0[0];
	$_SESSION['netconf']['wlan0']['ip'] = $ip;
}
else {
	$ip = $ip_fallback;
}


// minidlna.conf
$dlna = file_get_contents('/etc/minidlna.conf');
$dlna = preg_replace('/^#?presentation_url.*$/', 'presentation_url=http://' . $ip . ':80', $dlna);
file_put_contents('/etc/minidlna.conf', $dlna);

// Start minidlna service
if (isset($_SESSION['djmount']) && $_SESSION['djmount'] == 1) {
	sysCmd('/usr/bin/minidlna -f /run/minidlna.conf');
}


// unlock session files
playerSession('unlock', $db, '', '');

// Shairport (Airplay receiver service)
if (isset($_SESSION['shairport']) && $_SESSION['shairport'] == 1) {
	$output = '';
	$dbh = cfgdb_connect($db);
	$query_cfg = "SELECT param,value_player FROM cfg_mpd WHERE value_player!=''";
	$mpdcfg = sdbquery($query_cfg, $dbh);
	$dbh = null;
	foreach ($mpdcfg as $cfg) {
		if ($cfg['param'] == 'audio_output_format' && $cfg['value_player'] == 'disabled'){
			$output .= '';
		}
		else if ($cfg['param'] == 'device') {
			$device = $cfg['value_player'];
		}
		else {
			$output .= $cfg['param']." \t\"".$cfg['value_player']."\"\n";
		}
	}
	// Start Shairport
	// TC (Tim Curtis) 2014-08-23: set shairport friendly name
	$cmd = '/usr/local/bin/shairport -a "Moode" -w -B "mpc stop" -o alsa -- -d "hw:"'.$device.'",0" > /dev/null 2>&1 &';
	sysCmd($cmd);
}

// DLNA server
if (isset($_SESSION['djmount']) && $_SESSION['djmount'] == 1) {
	$cmd = 'djmount -o allow_other,nonempty,iocharset=utf-8 /mnt/UPNP > /dev/null 2>&1 &';
	sysCmd($cmd);
}

// UPnP renderer
if (isset($_SESSION['upnpmpdcli']) && $_SESSION['upnpmpdcli'] == 1) {
	$cmd = '/etc/init.d/upmpdcli start > /dev/null 2>&1 &';
	sysCmd($cmd);
}

// TC (Tim Curtis) 2014-12-23: read tcmods.conf file for clock radio settings
$_tcmods_conf = getTcmodsConf();
$clock_radio_starttime = $_tcmods_conf['clock_radio_starttime'];
$clock_radio_stoptime = $_tcmods_conf['clock_radio_stoptime'];

// TC (Tim Curtis) 2015-02-25: update tcmods.conf sys_ items
$_tcmods_conf['sys_kernel_ver'] = strtok(shell_exec('uname -r'),"\n");
$_tcmods_conf['sys_processor_arch'] = strtok(shell_exec('uname -m'),"\n");
$_ver_str = explode(": ", strtok(shell_exec('dpkg-query -p mpd | grep Version'),"\n"));
$_tcmods_conf['sys_mpd_ver'] = $_ver_str[1];
$rtn = _updTcmodsConf($_tcmods_conf);
// store in DB and $_SESSION[kernelver], $_SESSION[procarch] vars
playerSession('write', $db, 'kernelver', $_tcmods_conf['sys_kernel_ver']);
playerSession('write', $db, 'procarch', $_tcmods_conf['sys_processor_arch']);

// Ensure audio output is unmuted
if ($_SESSION['i2s'] == 'IQaudIO Pi-AMP+') {
	sysCmd("/var/www/command/unmute.sh pi-ampplus");
}
else if ($_SESSION['i2s'] == 'IQaudIO Pi-DigiAMP+') {
	sysCmd("/var/www/command/unmute.sh pi-digiampplus");
}
else {
	sysCmd("/var/www/command/unmute.sh default");
}

// TC (Tim Curtis) 2015-04-29: store PCM (alsamixer) volume, picked up by settings.php ALSA volume field
// TC (Tim Curtis) 2015-06-26: set simple mixer name based on kernel version and i2s vs USB
$mixername = getMixerName(getKernelVer($_SESSION['kernelver']), $_SESSION['i2s']);

$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh get-pcmvol ".$mixername;
$rtn = sysCmd($cmd);
if (substr($rtn[0], 0, 6 ) == 'amixer') {
	playerSession('write', $db, 'pcm_volume', 'none');
}
else {
	$rtn[0] = str_replace("%", "", $rtn[0]);
	playerSession('write', $db, 'pcm_volume', $rtn[0]);
}

// --- END NORMAL STARTUP --- //

session_write_close();

// --- WORKER MAIN LOOP --- //
while (1) {
	session_start();

	logWorker('[daemon] session_id ' . session_id());

	if (!count($_SESSION)) {
		logWorker('[daemon] session ' . session_id() . ' empty');
	}

	// TC (Tim Curtis) 2014-10-31: start with consume mode off, only runs once after player start
	if ($TCMODS_CONSUMEMODE_ON == "1") {
		$TCMODS_CONSUMEMODE_ON = "0";
		$mpd = openMpdSocket('localhost', 6600);
		sendMpdCommand($mpd, 'consume 0');
		closeMpdSocket($mpd);
	}

	// TC (Tim Curtis) 2014-12-23: check clock radio for scheduled playback
	if ($_tcmods_conf['clock_radio_enabled'] == "Yes") {
		$current_time = date("hi A");
		if ($current_time == $clock_radio_starttime) {
			$clock_radio_starttime = '';
			$mpd = openMpdSocket('localhost', 6600);

			// TC (Tim Curtis) 2015-06-26: new volume control with optional logarithmic mapping of knob 0-100 range to hardware range
			$_tcmods_conf = getTcmodsConf(); // read in conf file
			$level = $_tcmods_conf['clock_radio_volume'];

			if ($_tcmods_conf['volume_mixer_type'] == "hardware" && $_tcmods_conf['volume_curve_logarithmic'] == "Yes") {
				$maxLevel = $_tcmods_conf['volume_max_percent'] * .01; // default is 100, for capping max volume level
				$curveFactor = $_tcmods_conf['volume_curve_factor']; // adjusts curve to be more or less aggressive
				if ($level > 1) {
					$level = floor(($curveFactor * log10($level)) - (2 * $curveFactor) + 100); // round down
					$level = round($level * $maxLevel); // round up
				}
			}

			// TC (Tim Curtis) 2015-07-31: match code in player_lib.js setVolume()
			if ($level < 0) {$level = 0;} // negative values occure when curveFactor > 56

			$_tcmods_conf['volume_knob_setting'] = $_tcmods_conf['clock_radio_volume'];
			$rtn = _updTcmodsConf($_tcmods_conf); // update conf file
			if ($_tcmods_conf['volume_muted'] == 0) { // unmuted
				execMpdCommand($mpd, 'setvol '.$level);
			}

			execMpdCommand($mpd, 'play '.$_tcmods_conf['clock_radio_playitem']);
			closeMpdSocket($mpd);
		}
		else if ($current_time == $clock_radio_stoptime) {
			//$_tcmods_conf['clock_radio_stoptime'] = '';
			$mpd = openMpdSocket('localhost', 6600);
			execMpdCommand($mpd, 'stop');
			closeMpdSocket($mpd);
			// retry stop cmd to improve robustness
			if ($TCMODS_CLOCKRAD_RETRY == 0) {
				$clock_radio_stoptime = '';
				$TCMODS_CLOCKRAD_RETRY = 3;
			}
			else {
				--$TCMODS_CLOCKRAD_RETRY; // decrement
			}
			// shutdown requested
			if ($_tcmods_conf['clock_radio_shutdown'] == "Yes") {
				sysCmd('poweroff');
			}
		}
	}

	// TC (Tim Curtis) 2015-05-30: update playback history log
	if ($_tcmods_conf['play_history_enabled'] == "Yes") {
		// Get MPD currentsong data
		$mpd = openMpdSocket('localhost', 6600);
		$resp = execMpdCommand($mpd, 'currentsong');
		closeMpdSocket($mpd);

		$currentsong = _parseMpdCurrentSong($resp);

		// TC (Tim Curtis) 2015-07-31: updated logic
		// Logic modeled after player_lib.js getPlaylist();
		// RADIO STATION
		if (isset($currentsong['Name']) || (substr($currentsong['file'], 0, 4) == "http" && !isset($currentsong['Artist']))) {
			if (!isset($currentsong['Title'])) {
				$title = "Streaming source";
			}
			else {
				$title = $currentsong['Title'];
				$searchStr = str_replace('-', ' ', $title);
				$searchStr = str_replace('&', ' ', $searchStr);
				$searchStr = preg_replace('!\s+!', '+', $searchStr);
			}
			$artist = "<i class=\"icon-microphone\"></i>";
			$dbh = cfgdb_connect($db);
			$result = cfgdb_read('cfg_radio', $dbh, $currentsong['file']);

			if (0 == count($result)) {  // station not in db
				$album = isset($currentsong['Name'])
					? $currentsong['Name']
					: "Unknown station";
			}
			else {
				$album = $result[0]['name'];
			}
		// SONG FILE OR UPNP SONG URL
		}
		else {
			$title = (isset($currentsong['Title']))
				? $currentsong['Title']
				: pathinfo($currentsong['file'], PATHINFO_FILENAME);
			$artist = isset($currentsong['Artist'])
				? $currentsong['Artist']
				: "Unknown artist";
			$album = isset($currentsong['Album'])
				? $currentsong['Album']
				: "Unknown album";

			// search string
			if ($artist == "Unknown artist" && $album == "Unknown album") {
				$searchStr = $title;
			}
			else if ($artist == "Unknown artist") {
				$searchStr = $album."+".$title;
			}
			else if ($album == "Unknown album") {
				$searchStr = $artist."+".$title;
			}
			else {
				$searchStr = $artist."+".$album;
			}
		}
		// SEARCH URL AND TERMS
		if ($title == "Streaming source") {
			$searchUrl = "<span class=\"playhistory-link\"><i class=\"icon-external-link\"></i></span>";
		}
		else {
			$searchEngine = "http://www.google.com/search?q=";
			$searchUrl = "<a href=\"".$searchEngine.$searchStr."\" class=\"playhistory-link\" target=\"_blank\"><i class=\"icon-external-link-sign\"></i></a>";
		}

		// When song changes, update playback history log
		// TC (Tim Curtis) 2015-07-31: add $title not blank test
		if ($title != '' && $title != $_tcmods_conf['play_history_currentsong']) {
			// Update tcmods.conf file with curentsong
			$_tcmods_conf = getTcmodsConf(); // re-read to get most current data
			$_tcmods_conf['play_history_currentsong'] = $title;
			$rtn = _updTcmodsConf($_tcmods_conf);

			// Update playback history log
			$history_item = "<li class=\"playhistory-item\"><div>".date("Y-m-d H:i").$searchUrl.$title."</div><span>".$artist." - ".$album."</span></li>";
			//ORIGINAL $history_item = "<li class=\"playhistory-item\"><div>".date("Y-m-d H:i").$searchUrl.$title_log."</div><span>".$artist.", ".$album."</span></li>";
			$rtn = _updatePlayHistory($history_item);
		}
	}

	// Monitor loop
	if (false !== ($task = workerPopTask())) {
		logWorker("[daemon] Task active: " . $task);

		// switch command queue for predefined jobs
		switch ($task) {
			case 'reboot':
				$cmd = 'mpc stop && reboot';
				sysCmd($cmd);
				break;

			case 'poweroff':
				$cmd = 'mpc stop && poweroff';
				sysCmd($cmd);
				break;

			case 'phprestart':
				$cmd = 'service php5-fpm restart';
				sysCmd($cmd);
				break;

			case 'workerrestart':
				$cmd = 'killall ' . pathinfo(__FILE__, PATHINFO_BASENAME);
				sysCmd($cmd);
				break;

			case 'syschmod':
				wrk_syschmod();
				break;

			case 'orionprofile':
				if ($_SESSION['dev'] == 1) {
					$_SESSION['w_queueargs'] = 'dev';
				}
				$cmd = "/var/www/command/orion_optimize.sh ".$_SESSION['w_queueargs'];
				sysCmd($cmd);
				break;

			case 'netcfg':
				$file = '/etc/network/interfaces';
				$fp = fopen($file, 'w');
				$netconf = "auto lo\n";
				$netconf .= "iface lo inet loopback\n";
				//$netconf .= "\n";
				//$netconf .= "auto eth0\n";
				$netconf = $netconf.$_SESSION['w_queueargs'];
				fwrite($fp, $netconf);
				fclose($fp);

				// restart wlan0 interface
				if (strpos($netconf, 'wlan0') != false) {
				$cmd = "ip addr list wlan0 |grep \"inet \" |cut -d' ' -f6|cut -d/ -f1";
				$ip_wlan0 = sysCmd($cmd);
					if (!empty($ip_wlan0[0])) {
						$_SESSION['netconf']['wlan0']['ip'] = $ip_wlan0[0];
					}
					else {
						$_SESSION['netconf']['wlan0']['ip'] = wrk_checkStrSysfile('/proc/net/wireless', 'wlan0')
							? '--- NO IP ASSIGNED ---'
							: '--- NO INTERFACE PRESENT ---';
					}
				}
				sysCmd('service networking restart');
				break;

			case 'netcfgman':
				file_put_contents('/etc/network/interfaces', $_SESSION['w_queueargs']);
				break;

			case 'mpdcfg':
				// TC (Tim Curtis) 2015-06-26: add kernel version, i2s args
				// TC (Tim Curtis) 2015-06-26: use getKernelVer()  
				wrk_mpdconf('/etc', $db, getKernelVer($_SESSION['kernelver']), $_SESSION['i2s']);
				sysCmd('killall mpd');
				sysCmd('service mpd start');
				break;

			case 'mpdcfgman':
				// write mpd.conf file
				$fh = fopen('/etc/mpd.conf', 'w');
				fwrite($fh, $_SESSION['w_queueargs']);
				fclose($fh);
				sysCmd('killall mpd');
				sysCmd('service mpd start');
				break;

			case 'sourcecfg':
				wrk_sourcecfg($db, $_SESSION['w_queueargs']);
				break;

			// TC (Tim Curtis) 2014-08-23: process theme change requests
			// TC (Tim Curtis) 2015-04-29: streamline theme change code
			// TC (Tim Curtis) 2015-04-29: add 6 new theme colors
			// TC (Tim Curtis) 2015-05-30: streamline theme change code
			case 'themechange':
				// set colov values
				if ($_SESSION['w_queueargs'] == "amethyst") {$hexlight = "9b59b6"; $hexdark = "8e44ad";}
				else if ($_SESSION['w_queueargs'] == "bluejeans") {$hexlight = "436bab"; $hexdark = "1f4788";}
				else if ($_SESSION['w_queueargs'] == "carrot") {$hexlight = "e67e22"; $hexdark = "d35400";}
				else if ($_SESSION['w_queueargs'] == "emerald") {$hexlight = "2ecc71"; $hexdark = "27ae60";}
				else if ($_SESSION['w_queueargs'] == "fallenleaf") {$hexlight = "e5a646"; $hexdark = "cb8c3e";}
				else if ($_SESSION['w_queueargs'] == "grass") {$hexlight = "90be5d"; $hexdark = "7ead49";}
				else if ($_SESSION['w_queueargs'] == "herb") {$hexlight = "48929b"; $hexdark = "317589";}
				else if ($_SESSION['w_queueargs'] == "lavender") {$hexlight = "9a83d4"; $hexdark = "876dc6";}
				else if ($_SESSION['w_queueargs'] == "river") {$hexlight = "3498db"; $hexdark = "2980b9";}
				else if ($_SESSION['w_queueargs'] == "rose") {$hexlight = "d479ac"; $hexdark = "c1649b";}
				else if ($_SESSION['w_queueargs'] == "turquoise") {$hexlight = "1abc9c"; $hexdark = "16a085";}
				// change to new theme color
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh ".$_SESSION['w_queueargs']." ".$hexlight." ".$hexdark;
				sysCmd($cmd);
				 // reload tcmods.conf data
				$_tcmods_conf = getTcmodsConf();
				break;

			// TC (Tim Curtis) 2015-05-30: reload tcmods config data
			case 'reloadtcmodsconf':
				$_tcmods_conf = getTcmodsConf();
				break;

			// TC (Tim Curtis) 2014-12-23: reload clock radio settings from conf file
			case 'reloadclockradio':
				$_tcmods_conf = getTcmodsConf();
				$clock_radio_starttime = $_tcmods_conf['clock_radio_starttime'];
				$clock_radio_stoptime = $_tcmods_conf['clock_radio_stoptime'];
				break;

			// TC (Tim Curtis) 2015-02-25: process i2s driver select request
			case 'i2sdriver':
				// Remove any existing dtoverlay line(s)
				sysCmd('sed -i /dtoverlay/d /boot/config.txt');
				// Set i2s driver
				// TC (Tim Curtis) 2015-06-26: use getKernelVer()
				$kernelver = getKernelVer($_SESSION['kernelver']);
				if ($kernelver == '3.18.5+' || $kernelver == '3.18.11+' || $kernelver == '3.18.14+') {
					_setI2sDtoverlay($db, $_SESSION['w_queueargs']); // Dtoverlay (/boot/config.txt)
				}
				else {
					_setI2sModules($db, $_SESSION['w_queueargs']); // Modules (/etc/modules)
				}
				break;

			// TC (Tim Curtis) 2015-02-25: process kernel select request
			case 'kernelver':
				// TC (Tim Curtis) 2015-06-26: use getKernelVer()
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh install-kernel ".getKernelVer($_SESSION['w_queueargs']);
				$rtn = sysCmd($cmd);
				break;

			// TC (Tim Curtis) 2015-04-29: process timezone select request
			case 'timezone':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh set-timezone ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			// TC (Tim Curtis) 2015-04-29: process host name change request
			case 'host_name':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh chg-name host ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			case 'browser_title':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh chg-name browsertitle ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			case 'airplay_name':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh chg-name airplay ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			case 'upnp_name':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh chg-name upnp ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			case 'dlna_name':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh chg-name dlna ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			// TC (Tim Curtis) 2015-04-29: handle PCM volume change
			case 'pcm_volume':
				// TC (Tim Curtis) 2015-06-26: set simple mixer name based on kernel version and i2s vs USB
				$mixername = getMixerName(getKernelVer($_SESSION['kernelver']), $_SESSION['i2s']);
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/tcmods.sh set-pcmvol ".$mixername." ".$_SESSION['w_queueargs'];
				$rtn = sysCmd($cmd);
				break;

			// TC (Tim Curtis) 2015-05-30: add clear system and playback history logs
			case 'clearsyslogs':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/utility.sh clear-logs";
				$rtn = sysCmd($cmd);
				break;

			case 'clearplayhistory':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/utility.sh clear-playhistory";
				$rtn = sysCmd($cmd);
				break;

			// TC (Tim Curtis) 2015-07-31: expand sd card storage
			case 'expandsdcard':
				$cmd = "/var/www/tcmods/".$TCMODS_REL."/cmds/resizefs.sh start";
				$rtn = sysCmd($cmd);
				break;

		} // end switch

		logWorker("[daemon] Task done");

		workerFinishTask();
	}
	else {
		logWorker("[daemon] Sleeping");
	}

	session_write_close();
	sleep(5);
}
// --- END WORKER MAIN LOOP --- //
