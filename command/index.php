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

require_once dirname(__FILE__) . '/../inc/connection.php';

if (!$mpd) {
    die('Error: connection to MPD failed');
}

if (!isset($_GET['cmd'])) {
    die('Error: missing or invalid command');
}
$cmd = $_GET['cmd'];

sendMpdCommand($mpd, $cmd);
$resp = readMpdResponse($mpd);

// TC (Tim Curtis) 2015-01-01
// - to see if we can send back the error line when cmd=play and connect fails to radio station url
// - problem is that MPD automatically tries to play the next playlist item if play fails so the data
//   we get back reflects the next playlist item and not the error item...
/*
if ($resp == "OK\n") {
	$resp = json_encode(_parseStatusResponse(MpdStatus($mpd)));
} else {
    $resp = 'Error: command/index.php, sendMpdCommand resed >'.$resp.'<';
}
*/

closeMpdSocket($mpd);

// replace tcmods-cs
if ('currentsong' == $cmd) {
	$resp = _parseMpdCurrentSong($resp);
}

header('Content-type: application/json');
echo json_encode($resp);
