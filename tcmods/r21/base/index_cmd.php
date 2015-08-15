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
 *	file:					command/index.php
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 * 	TC (Tim Curtis) 2015-01-01, r1.4
 *	- testing readMpdResponse() and send status data back to caller
 *	- shovel & broom
 *
 */
 
// common include
include('../inc/connection.php');
error_reporting(ERRORLEVEL);

if (isset($_GET['cmd']) && $_GET['cmd'] != '') {
    if (!$mpd) {
	    $return = 'Error: command/index.php, connection failed to MPD';
	} else {
		sendMpdCommand($mpd, $_GET['cmd']);
		$return = readMpdResponse($mpd);
		
		// TC (Tim Curtis) 2015-01-01
		// - to see if we can send back the error line when cmd=play and connect fails to radio station url
		// - problem is that MPD automatically tries to play the next playlist item if play fails so the data
		//   we get back reflects the next playlist item and not the error item...
		/*
		if ($return == "OK\n") {
			$return = json_encode(_parseStatusResponse(MpdStatus($mpd)));
		} else {
		    $return = 'Error: command/index.php, sendMpdCommand returned >'.$return.'<';
		}			
		*/
		
		closeMpdSocket($mpd);
    }
} else {
	$return = 'Error: command/index.php, command missing';
}

echo $return;
?>

