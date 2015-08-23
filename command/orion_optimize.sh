#!/bin/bash
#
#	This Program is free software; you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation; either version 3, or (at your option)
#	any later version.
#
#	This Program is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#	GNU General Public License for more details.
#
#	PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
#	Tsunamp Team
#	http://www.tsunamp.com
#
#	UI-design/JS code by: 	Andrea Coiutti (aka ACX)
#	PHP/JS code by:			Simone De Gregori (aka Orion)
# 
#	file:					orion_optimize.sh
# 	version:				1.0
#
#	TCMODS Edition 
#
#	TC (Tim Curtis) 2015-01-27
#	- add this header
#	- remove unmute.sh (moved to player_wrk.php)
#	- prune out some kernel profiles
#	- shovel & broom	
#
######################################
# Orion Tsunamp Optimize script v0.9 #
######################################
ver="0.9"

if [ "$2" == "startup" ]; then
	# kill useless system processes
	killall -9 exim4
	killall -9 ntpd
	killall -9 thd
	killall -9 udevd
	killall -9 cron
	killall -9 atd
	killall -9 startpar
	#killall -9 avahi-daemon
	#killall -9 dbus-daemon
	#killall -9 rpc.idmapd
	#killall -9 rpc.statd
	#killall -9 rpcbind
	#killall -9 automount
	#killall -9 dhclient
	echo "flush startup settings"
fi

##################
# sound profiles #
##################

# Default
if [ "$1" == "Default" ]; then
	echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
	ifconfig eth0 mtu 1500
	echo noop > /sys/block/mmcblk0/queue/scheduler
	echo 60 > /proc/sys/vm/swappiness
	echo 6000000 > /proc/sys/kernel/sched_latency_ns
	echo 1000000 > /proc/sys/kernel/sched_rt_period_us
	echo 950000 > /proc/sys/kernel/sched_rt_runtime_us
	echo "flush DEFAULT sound profile"
fi

## kernel latency settings (1.0 BetaACX - betaacx.img) MOD1
## "warm" sound but little less "focus" 

# mod1
if [ "$1" == "ACX" ]; then
	echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
	ifconfig eth0 mtu 1500
	echo noop > /sys/block/mmcblk0/queue/scheduler
	echo 0 > /proc/sys/vm/swappiness
	echo 1000000 > /proc/sys/kernel/sched_latency_ns
	echo 166666 > /proc/sys/kernel/sched_rt_period_us
	echo 158333 > /proc/sys/kernel/sched_rt_runtime_us
	echo "flush MOD1 sound profile 'warm'"
fi

## kernel latency settings (1.0 BetaACX - betaacx.img ) MOD2 
## very good sound "balance" and "transparency". My choice for current betaacx.img

# mod2
if [ "$1" == "Orion" ]; then
	echo -n performance > /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor
	ifconfig eth0 mtu 1500
	echo noop > /sys/block/mmcblk0/queue/scheduler
	echo 0 > /proc/sys/vm/swappiness
	echo 500000 > /proc/sys/kernel/sched_latency_ns
	echo 124999 > /proc/sys/kernel/sched_rt_period_us
	echo 118749 > /proc/sys/kernel/sched_rt_runtime_us
	echo "flush MOD2 sound profile 'balance and transparency'"
fi

# dev
if [ "$1" == "dev" ]; then
	echo "flush DEV sound profile 'fake'"
fi

if [ "$1" == "" ]; then
	echo "Orion Optimize Script v$ver" 
	echo "Usage: $0 {default|beta1|mod1|mod2} {startup}"
	exit 1
fi
