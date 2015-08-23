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
#	You should have received a copy of the GNU General Public License
#	along with this software.  If not, see
#	<http://www.gnu.org/licenses/>.
#
#	DESC: runs from player_wrk.php at player start to ensure output is unmuted
# 
#	TC (Tim Curtis) 2015-01-27, r1.5
#		- add this header
# 
#	TC (Tim Curtis) 2015-03-21, r1.7
#		- add input args and logic to handle default and other unmutes
# 
#	TC (Tim Curtis) 2015-06-26, r2.0
#		- add unmute for IQaudIO Pi-DigiAMP+
#
if [ -z "$1" ]; then
        echo "missing arg" >/var/www/unmute.log
        echo "~tcmods: unmute"
        echo "~tcmods: Valid args are default, pi-ampplus"
        exit
fi

# default unmute
if [ $1 = "default" ]; then
        echo "default unmute" >/var/www/unmute.log
        amixer scontrols | sed -e 's/^Simple mixer control//' | while read line; do
                amixer sset "$line" unmute;
                done
        exit
fi

# unmute IQaudIO Pi-AMP+, Pi-DigiAMP+
if [ $1 = "pi-ampplus" -o $1 = "pi-digiampplus" ]; then
    echo "unmute IQaudIO Pi-AMP+(DigiAMP+)" >/var/www/unmute.log
	echo "22" > /sys/class/gpio/export
	echo "out" >/sys/class/gpio/gpio22/direction
	echo "1" >/sys/class/gpio/gpio22/value	
	exit
fi