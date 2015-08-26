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

function mpdTouchFiles() {
	return sysCmd("find '" . MPD_LIB . 'WEBRADIO' . "' -name \"" . "*.pls" . "\"" . " -exec touch {} \+");
}

// Get options- cmd line or GET
$options = getopt('c:p:', array('cmd:', 'path:'));
$cmd = isset($options['c']) ? $options['c'] : isset($options['cmd']) ? $options['cmd'] : null;
$path = isset($options['p']) ? $options['p'] : isset($options['path']) ? $options['path'] : null;

if (empty($cmd)) {
	if (!isset($_GET['cmd'])) {
		die('Error: missing or invalid command');
	}

	$cmd = $_GET['cmd'];
	$path = isset($_POST['path']) ? $_POST['path'] : null;
}

if (!$mpd) {
	die('Error: connection to MPD failed');
}

// Commands
switch ($cmd) {
	case 'filepath':
		$res = (null !== $path)
			? searchDB($mpd, 'filepath', $path)
			: searchDB($mpd, 'filepath');
		break;

	// - delete radio station file
	case 'deleteradiostn':
		if (null !== $path) {
			$res = array('syscmd' => array());
			$res['syscmd'][] = sysCmd("rm '" . MPD_LIB . $path . "'");
			// update time stamp on files so MPD picks up the change and commits the update
			$res['syscmd'][] = mpdTouchFiles();
		}
		break;

	// - add radio station file, also used for update
	case 'addradiostn':
		if (null !== $path) {
			$res = array('syscmd' => array());

			// create new file if none exists, or open existing file for overwrite
			$_file = MPD_LIB . 'WEBRADIO/' . $path . '.pls';
			if (false === ($handle = fopen($_file, 'w'))) {
				die('db/index.php: file create failed on '.$_file);
			}

			// format .pls lines
			$data = '[playlist]' . "\n";
			$data .= 'numberofentries=1' . "\n";
			$data .= 'File1='.$_POST['url'] . "\n";
			$data .= 'Title1=' . $path . "\n";
			$data .= 'Length1=-1' . "\n";
			$data .= 'version=2' . "\n";
			// write data, close file
			fwrite($handle, $data);
			fclose($handle);

			// reset file permissions
			$res['syscmd'][] = sysCmd("chmod 777 \"" .$_file . "\"");
			// update time stamp on files so MPD picks up the change and commits the update
			$res['syscmd'][] = mpdTouchFiles();
		}
		break;

	// - list contents of saved playlist
	case 'listsavedpl':
		if (null !== $path) {
			$res = mpdListPlayList($mpd, $path);
		}
		break;

	// - delete saved playlist
	case 'deletesavedpl':
		if (null !== $path) {
			$res = mpdRemovePlayList($mpd, $path);
		}
		break;

	case 'playlist':
		$res = mpdQueueInfo($mpd);
		break;

	case 'add':
		if (null !== $path) {
			$res = mpdQueueAdd($mpd, $path);
		}
		break;

	case 'addplay':
		if (null !== $path) {
			$status = _parseStatusResponse(mpdStatus($mpd));
			$pos = $status['playlistlength'] ;
			mpdQueueAdd($mpd, $path);
			$res = execMpdCommand($mpd, 'play '.$pos);
		}
		break;

	case 'addreplaceplay':
		if (null !== $path) {
			$res = execMpdCommand($mpd, 'clear');
			mpdQueueAdd($mpd, $path);
			$res = execMpdCommand($mpd, 'play');
		}
		break;

	case 'update':
		if (null !== $path) {
			$res = execMpdCommand($mpd, 'update "' . html_entity_decode($path) . '"');
		}
		break;

	case 'trackremove':
		if (isset($_GET['songid']) && $_GET['songid'] != '') {
			$res = mpdQueueRemoveTrack($mpd,$_GET['songid']);
		}
		break;

	// - move playlist tracks
	case 'trackmove':
		if (isset($_GET['songid']) && $_GET['songid'] != '') {
			$_args = $_GET['songid'].' '.$_GET['newpos'];
			execMpdCommand($mpd, 'move '.$_args);
			$res = 'track move args= '.$_args;
		}
		break;

	case 'savepl':
		if (isset($_GET['plname']) && $_GET['plname'] != '') {
			$res = execMpdCommand($mpd, 'rm "' . html_entity_decode($_GET['plname']) . '"');
			$res = execMpdCommand($mpd, 'save "' . html_entity_decode($_GET['plname']) . '"');
		}
		break;

	case 'search':
		if (isset($_POST['query']) && $_POST['query'] != '' &&
			isset($_GET['querytype']) && $_GET['querytype'] != '')
		{
			$res = searchDB($mpd,$_GET['querytype'],$_POST['query']);
		}
		break;

	case 'loadlib':
		$res = loadAllLib($mpd);
		break;

	case 'addall':
		if (null !== $path) {
			$res = mpdQueueAddMultiple($mpd, array_column($path, 'file')); // nested array
		}
		break;

	// - added code to set the playlist song pos for play
	case 'playall':
		if (null !== $path) {
			$status = _parseStatusResponse(mpdStatus($mpd));
			$pos = $status['playlistlength'] ;

			$res = mpdQueueAddMultiple($mpd, array_column($path, 'file')); // nested array
			execMpdCommand($mpd, 'play ' . $pos);
		}
		break;

	// - library panel Add/replace/playall btn
	case 'addallreplaceplay':
		if (null !== $path) {
			execMpdCommand($mpd, 'clear');
			$res = mpdQueueAddMultiple($mpd, array_column($path, 'file')); // nested array
			execMpdCommand($mpd, 'play');
		}
		break;

	case 'currentsong':
		$res = _parseMpdCurrentSong(execMpdCommand($mpd, 'currentsong'));
		break;

	default:
		// execute any mpd command
		$res = execMpdCommand($mpd, $cmd);
}

closeMpdSocket($mpd);

header('Content-type: application/json');
echo json_encode($res);
