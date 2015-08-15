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
 *	file:					player_engine.php
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 *	TC (Tim Curtis) 2015-01-27, r1.5
 *	- shovel & broom
 *
 */
 
// Common include
include('inc/connection.php');
playerSession('open',$db,'',''); 
?>

<?php
// Main processing section	
if (!$mpd) {
    echo 'Error Connecting to MPD';
} else {
	// Fetch MPD status
	$status = _parseStatusResponse(MpdStatus($mpd));
	
	// Check for CMediaFix
	if (isset($_SESSION['cmediafix']) && $_SESSION['cmediafix'] == 1) {
		$_SESSION['lastbitdepth'] = $status['audio'];
	}
	
	// Check for Ramplay
	if (isset($_SESSION['ramplay']) && $_SESSION['ramplay'] == 1) {
		// Record "lastsongid" in PHP SESSION
		$_SESSION['lastsongid'] = $status['songid'];
		$_SESSION['nextsongid'] = $status['nextsongid']; 
	}

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
	} else {
		$path = parseFileStr($curTrack[0]['file'],'/');
		$status['fileext'] = parseFileStr($curTrack[0]['file'],'.');
		$status['currentartist'] = "";
		$status['currentsong'] = $song;
		$status['currentalbum'] = "path: ".$path;
	}
		
	// CMediaFix
	if (isset($_SESSION['cmediafix']) && $_SESSION['cmediafix'] == 1 && $status['state'] == 'play' ) {
		$status['lastbitdepth'] = $_SESSION['lastbitdepth'];
		if ($_SESSION['lastbitdepth'] != $status['audio']) {
			sendMpdCommand($mpd,'cmediafix');
		}
	}
	
	// Ramplay
	if (isset($_SESSION['ramplay']) && $_SESSION['ramplay'] == 1) {
		// copio il pezzo in /dev/shm
		$path = rp_copyFile($status['nextsongid'],$mpd);
		// lancio update mdp locazione ramplay
		rp_updateFolder($mpd);
		// lancio addandplay canzone
		rp_addPlay($path,$mpd,$status['playlistlength']);
	}

	// JSON response for GUI
	echo json_encode($status);
		
closeMpdSocket($mpd);	
}