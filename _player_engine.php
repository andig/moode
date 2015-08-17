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

playerSession('open',$db,'',''); 

if (!$mpd) {
	die('Error: connection to MPD failed');
}

// Fetch MPD status
$status = _parseStatusResponse(MpdStatus($mpd));

// Register player state in session
$_SESSION['state'] = $status['state'];
session_write_close(); // Unlock SESSION file

// Check and compare GUI state with Backend state
// MPD idle timeout loop, monitorMpdState() waits until something changes in MPD then returns status
if ($_GET['state'] == $status['state']) {
	$status = monitorMpdState($mpd);
}

$curTrack = getTrackInfo($mpd,$status['song']);

if (isset($curTrack[0]['Title'])) {
	$status['currentartist'] = $curTrack[0]['Artist'];
	$status['currentsong'] = $curTrack[0]['Title'];
	$status['currentalbum'] = $curTrack[0]['Album'];
	$status['fileext'] = parseFileStr($curTrack[0]['file'],'.');
}
else {
	$path = parseFileStr($curTrack[0]['file'],'/');
	$status['fileext'] = parseFileStr($curTrack[0]['file'],'.');
	$status['currentartist'] = "";
	$status['currentsong'] = $song;
	$status['currentalbum'] = "path: ".$path;
}

closeMpdSocket($mpd);

echo json_encode($status);
