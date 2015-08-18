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
$status = _parseStatusResponse(mpdStatus($mpd));

// Register player state in session
$_SESSION['state'] = $status['state'];
session_write_close(); // Unlock SESSION file

// Check and compare GUI state with Backend state
// MPD idle timeout loop, mpdMonitorState() waits until something changes in MPD then returns status
if ($_GET['state'] == $status['state']) {
	$status = mpdMonitorState($mpd);
}

// get track info for currently playing track
$track = mpdQueueTrackInfo($mpd, $status['song']);

if (isset($track[0]['Title'])) {
	$status['fileext'] = parseFileStr($track[0]['file'], '.');

	$status['currentartist'] = $track[0]['Artist'];
	$status['currentsong'] = $track[0]['Title'];
	$status['currentalbum'] = $track[0]['Album'];
}
else {
	$path = parseFileStr($track[0]['file'], '/');
	$status['fileext'] = parseFileStr($track[0]['file'], '.');

	$status['currentartist'] = "";
	$status['currentsong'] = $song;
	$status['currentalbum'] = "path: ".$path;
}

closeMpdSocket($mpd);

header('Content-type: application/json');
echo json_encode($status);
