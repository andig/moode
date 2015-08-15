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
 *	file:					db/index.php
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 * 	TC (Tim Curtis) 2014-09-17, r1.0
 *	- added case 'savedplaylist': to enumerate contents of saved playlist  
 *
 * 	TC (Tim Curtis) 2014-12-23, r1.3
 *	- added case 'deleteradiostn': to handle radio station delete
 *	- added case 'addradiostn': to handle radio station add and update
 *	- added case "trackmove' to handle moving playlist tracks
 *	- debug Library addAll $_POST not matching what was sent, turned out to be php.ini max_input_vars = 1000 limit
 *	- shovel & broom
 *
 */

// common include
include('../inc/connection.php');
error_reporting(ERRORLEVEL);

if (isset($_GET['cmd']) && $_GET['cmd'] != '') {
    if ( !$mpd ) {
       echo 'Error connecting to MPD server';
	} else {
		switch ($_GET['cmd']) {
			case 'filepath':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(searchDB($mpd,'filepath',$_POST['path']));
				} else {
					echo json_encode(searchDB($mpd,'filepath'));
				}
				break;
				
			// TC (Tim Curtis) 2014-11-30
			// - delete radio station file
			case 'deleteradiostn':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					sysCmd("rm \""."/var/lib/mpd/music/".$_POST['path']."\"");
					// update time stamp on files so MPD picks up the change and commits the update
					sysCmd("find /var/lib/mpd/music/WEBRADIO -name \""."*.pls"."\""." -exec touch {} \+");
				}
				break;
				
			// TC (Tim Curtis) 2014-12-23
			// - add radio station file, also used for update
			case 'addradiostn':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					// create new file if none exists, or open existing file for overwrite 
					$_file = '/var/lib/mpd/music/WEBRADIO/'.$_POST['path'].'.pls';
					$handle = fopen($_file, 'w') or die('db/index.php: file create failed on '.$_file);
					// format .pls lines
					$data = '[playlist]'."\n";
					$data .= 'numberofentries=1'."\n";
					$data .= 'File1='.$_POST['url']."\n";
					$data .= 'Title1='.$_POST['path']."\n";
					$data .= 'Length1=-1'."\n";
					$data .= 'version=2'."\n";
					// write data, close file
					fwrite($handle, $data);
					fclose($handle);
					// reset file permissions
					sysCmd("chmod 777 \"".$_file."\"");
					// update time stamp on files so MPD picks up the change and commits the update
					sysCmd("find /var/lib/mpd/music/WEBRADIO -name \""."*.pls"."\""." -exec touch {} \+");
				}
				break;
				
			// TC (Tim Curtis) 2014-09-17
			// - list contents of saved playlist
			// - delete saved playlist
			case 'listsavedpl':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(listPlayList($mpd, $_POST['path']));
				}
				break;
				
			case 'deletesavedpl':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(removePlayList($mpd, $_POST['path']));
				}
				break;
				
			case 'playlist':
				echo json_encode(getPlayQueue($mpd));
				break;
				
			case 'add':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					echo json_encode(addQueue($mpd,$_POST['path']));
				}
				break;
				
			case 'addplay':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					$status = _parseStatusResponse(MpdStatus($mpd));
					$pos = $status['playlistlength'] ;
					addQueue($mpd,$_POST['path']);
					sendMpdCommand($mpd,'play '.$pos);
					echo json_encode(readMpdResponse($mpd));
				}
				break;
				
			case 'addreplaceplay':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					sendMpdCommand($mpd,'clear');
					addQueue($mpd,$_POST['path']);
					sendMpdCommand($mpd,'play');
					echo json_encode(readMpdResponse($mpd));
				}
				break;
				
			case 'update':
				if (isset($_POST['path']) && $_POST['path'] != '') {
					sendMpdCommand($mpd,"update \"".html_entity_decode($_POST['path'])."\"");
					echo json_encode(readMpdResponse($mpd));
				}
				break;
				
			case 'trackremove':
				if (isset($_GET['songid']) && $_GET['songid'] != '') {
					echo json_encode(remTrackQueue($mpd,$_GET['songid']));
				}
				break;
			
			// TC (Tim Curtis) 2014-12-23
			// - move playlist tracks	
			case 'trackmove':
				if (isset($_GET['songid']) && $_GET['songid'] != '') {
					$_args = $_GET['songid'].' '.$_GET['newpos'];
					sendMpdCommand($mpd,'move '.$_args);
					echo json_encode('track move args= '.$_args);
				}
				break;

            case 'savepl':
                if (isset($_GET['plname']) && $_GET['plname'] != '') {
                    sendMpdCommand($mpd,"rm \"".html_entity_decode($_GET['plname'])."\"");
                    sendMpdCommand($mpd,"save \"".html_entity_decode($_GET['plname'])."\"");
                    echo json_encode(readMpdResponse($mpd));
                }
				break;
				
			case 'search':
				if (isset($_POST['query']) && $_POST['query'] != '' && isset($_GET['querytype']) && $_GET['querytype'] != '') {
					echo json_encode(searchDB($mpd,$_GET['querytype'],$_POST['query']));
				}
				break;
				
            case 'loadlib':
				echo loadAllLib($mpd);
            	break;
            	
            case 'addall':
                if (isset($_POST['path']) && $_POST['path'] != '') {
                    echo json_encode(enqueueAll($mpd,$_POST['path']));
				}
				break;
				
            // TC (Tim Curtis) 2014-09-17	
            // - added code to set the playlist song pos for play	
            case 'playall':
                if (isset($_POST['path']) && $_POST['path'] != '') {
					// TC just a copy/paste from addplay above 
					$status = _parseStatusResponse(MpdStatus($mpd));
					$pos = $status['playlistlength'] ;
                	// original code, did not set play posn
                	echo json_encode(playAll($mpd,$_POST['path']));
					sendMpdCommand($mpd,'play '.$pos);
					echo json_encode(readMpdResponse($mpd));
                }
				break;
				
			// TC (Tim Curtis) 2014-09-17
			// - library panel Add/replace/playall btn
            case 'addallreplaceplay':
                if (isset($_POST['path']) && $_POST['path'] != '') {
	               
	               // TC (Tim Curtis) 2014-12-23
	               // - to debug $_POST not matching what was sent
	               // - turned out to be php.ini max_input_vars = 1000 limit 
	               //echo json_encode($_POST['path']);
                   echo json_encode(playAllReplace($mpd,$_POST['path']));
				}
				break;
			} // end switch
			
		closeMpdSocket($mpd);
	}
} else {
	echo 'MPD DB INTERFACE<br>';
	echo 'INTERNAL USE ONLY<br>';
	echo 'hosted on raspyfi.local:81';
}
?>

