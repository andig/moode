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
 *  along with TsunAMP; see the file COPYING.  If not, see
 *  <http://www.gnu.org/licenses/>. 
 *
 *	TCMODS Edition 
 *
 *	TC (Tim Curtis) 2014-08-23, r1.0
 *	- get mpd currentsong data
 *	- called from player_lib.js
 *
 *	TC (Tim Curtis) 2014-09-17, r1.1
 *	- add limit 2 to explode to avoid incorrect parsing where string contains more than one ":" (colon)
 *	- this fixes bad parsing of song titles that contain a colon ":" (commonly found in the Classical genre)
 *
 */

// common include
include('../inc/connection.php');
error_reporting(ERRORLEVEL);

if ( !$mpd ) {
        echo 'Error Connecting to MPD daemon';
} else {
	sendMpdCommand($mpd, 'currentsong');
	$resp = readMpdResponse($mpd);

	if (is_null($resp) ) {
		echo 'Error, tcmods-cs.php response is null';
	} else {
		$tcArray = array();
		$tcLine = strtok($resp,"\n");

		while ( $tcLine ) {
			list ( $element, $value ) = explode(": ",$tcLine, 2);
			$tcArray[$element] = $value;
			$tcLine = strtok("\n");
		}

		echo json_encode($tcArray);
		closeMpdSocket($mpd);
	}
}
?>
