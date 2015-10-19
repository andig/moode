<?php

function debug_footer($db) {
	echo "\n";
	echo "###### System info ######\n";
	echo  file_get_contents('/proc/version');
	echo "\n";
	echo  "system load:\t".file_get_contents('/proc/loadavg');
	echo "\n";
	echo "HW platform:\t".$_SESSION['hwplatform']." (".$_SESSION['hwplatformid'].")\n";
	echo "\n";
	echo "playerID:\t".$_SESSION['playerid']."\n";
	echo "\n";
	echo "\n";
	echo "###### Audio backend ######\n";
	echo  file_get_contents('/proc/asound/version');
	echo "\n";
	echo "Card list: (/proc/asound/cards)\n";
	echo "--------------------------------------------------\n";
	echo  file_get_contents('/proc/asound/cards');
	echo "\n";
	echo "ALSA interface #0: (/proc/asound/card0/pcm0p/info)\n";
	echo "--------------------------------------------------\n";
	echo  file_get_contents('/proc/asound/card0/pcm0p/info');
	echo "\n";
	echo "ALSA interface #1: (/proc/asound/card1/pcm0p/info)\n";
	echo "--------------------------------------------------\n";
	echo  file_get_contents('/proc/asound/card1/pcm0p/info');
	echo "\n";
	echo "interface #0 stream status: (/proc/asound/card0/stream0)\n";
	echo "--------------------------------------------------------\n";
	$streaminfo = file_get_contents('/proc/asound/card0/stream0');
	if (empty($streaminfo)) {
	echo "no stream present\n";
	} else {
	echo $streaminfo;
	}
	echo "\n";
	echo "interface #1 stream status: (/proc/asound/card1/stream0)\n";
	echo "--------------------------------------------------------\n";
	$streaminfo = file_get_contents('/proc/asound/card1/stream0');
	if (empty($streaminfo)) {
	echo "no stream present\n";
	} else {
	echo $streaminfo;
	}
	echo "\n";
	echo "\n";
	echo "###### Kernel optimization parameters ######\n";
	echo "\n";
	echo "hardware platform:\t".$_SESSION['hwplatform']."\n";
	echo "current orionprofile:\t".$_SESSION['orionprofile']."\n";
	echo "\n";
	// 		echo  "kernel scheduler for mmcblk0:\t\t".((empty(file_get_contents('/sys/block/mmcblk0/queue/scheduler'))) ? "\n" : file_get_contents('/sys/block/mmcblk0/queue/scheduler'));
	echo  "kernel scheduler for mmcblk0:\t\t".file_get_contents('/sys/block/mmcblk0/queue/scheduler');
	echo  "/proc/sys/vm/swappiness:\t\t".file_get_contents('/proc/sys/vm/swappiness');
	echo  "/proc/sys/kernel/sched_latency_ns:\t".file_get_contents('/proc/sys/kernel/sched_latency_ns');
	echo  "/proc/sys/kernel/sched_rt_period_us:\t".file_get_contents('/proc/sys/kernel/sched_rt_period_us');
	echo  "/proc/sys/kernel/sched_rt_runtime_us:\t".file_get_contents('/proc/sys/kernel/sched_rt_runtime_us');
	echo "\n";
	echo "\n";
	echo "###### Filesystem mounts ######\n";
	echo "\n";
	echo  file_get_contents('/proc/mounts');
	echo "\n";
	echo "\n";
	echo "###### mpd.conf ######\n";
	echo "\n";
	echo file_get_contents('/etc/mpd.conf');
	echo "\n";
	}
	if ($_SESSION['debug'] > 1) {
	echo "\n";
	echo "\n";
	echo "###### PHP backend ######\n";
	echo "\n";
	echo "debug level:\t".$_SESSION['debug']."\n";
	echo "\n";
	echo "\n";
	echo "###### SESSION ######\n";
	echo "\n";
	echo "ID:\t\t".session_id()."\n"; 
	echo "SAVE PATH:\t".session_save_path()."\n";
	echo "\n";
	echo "\n";
	echo "###### SESSION DATA ######\n";
	echo "\n";
	print_r($_SESSION);
	}
	if ($_SESSION['debug'] > 2) {
	$connection = new pdo($db);
	$querystr="SELECT * FROM cfg_engine";
	$data['cfg_engine'] = sdbquery($querystr,$connection);
	$querystr="SELECT * FROM cfg_lan";
	$data['cfg_lan'] = sdbquery($querystr,$connection);
	$querystr="SELECT * FROM cfg_wifisec";
	$data['cfg_wifisec'] = sdbquery($querystr,$connection);
	$querystr="SELECT * FROM cfg_mpd";
	$data['cfg_mpd'] = sdbquery($querystr,$connection);
	$querystr="SELECT * FROM cfg_source";
	$data['cfg_source'] = sdbquery($querystr,$connection);
	$connection = null;
	echo "\n";
	echo "\n";
	echo "###### SQLite datastore ######\n";
	echo "\n";
	echo "\n";
	echo "### table CFG_ENGINE ###\n";
	print_r($data['cfg_engine']);
	echo "\n";
	echo "\n";
	echo "### table CFG_LAN ###\n";
	print_r($data['cfg_lan']);
	echo "\n";
	echo "\n";
	echo "### table CFG_WIFISEC ###\n";
	print_r($data['cfg_wifisec']);
	echo "\n";
	echo "\n";
	echo "### table CFG_SOURCE ###\n";
	print_r($data['cfg_source']);
	echo "\n";
	echo "\n";
	echo "### table CFG_MPD ###\n";
	print_r($data['cfg_mpd']);
	echo "\n";
	}
	if ($_SESSION['debug'] > 0) {
	echo "\n";
	printf("Page created in %.5f seconds.", (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']));
	echo "\n";
	echo "\n";
}
