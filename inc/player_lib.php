<?php

// Predefined MPD Response messages
define("MPD_RESPONSE_ERR", "ACK");
define("MPD_RESPONSE_OK",  "OK");

// v2
function openMpdSocket($host, $port) {
	$sock = stream_socket_client('tcp://'.$host.':'.$port.'', $errorno, $errorstr, 30 );
	$response = readMpdResponse($sock);
	if ($response = '') {
		sysCmd('command/shell.sh '.$response);
		exit;
	} else {
		return $sock;
	}
} //end openMpdSocket()

function closeMpdSocket($sock) {
	sendMpdCommand($sock,"close");
	fclose($sock);
}

// v2
function sendMpdCommand($sock,$cmd) {
	if ($cmd == 'cmediafix') {
		$cmd = "pause\npause\n";
		fputs($sock, $cmd);
	} else {
		$cmd = $cmd."\n";
		fputs($sock, $cmd);	
	}
}

// v3
// AG (Andreas Goetz) 2015-08-10: add stream parameter for reading one line at a time (NOTE: doesn't allow empty lines!)
function readMpdResponse($sock, $stream = false) {
	$output = "";
	// read one line at a time
	if ($stream) {
		if (!feof($sock)) {
			$output = fgets($sock,1024);
		}
	}
	else while(!feof($sock)) {
		$response =  fgets($sock,1024);
		$output .= $response;
		if (strncmp(MPD_RESPONSE_OK,$response,strlen(MPD_RESPONSE_OK)) == 0) {
			break;
		}
		if (strncmp(MPD_RESPONSE_ERR,$response,strlen(MPD_RESPONSE_ERR)) == 0) {
			$output = "MPD error: $response";
			break;
		}
	}
	return $output;
}

function chainMpdCommands($sock, $commands) {
    foreach ($commands as $command) {
        sendMpdCommand($sock, $command);
        readMpdResponse($sock);
    }
}

function libLog($str, $overwrite = false) {
	$debug_fhand = fopen("/var/www/liblog.txt", $overwrite ? 'w' : 'a'); // write or append
	fwrite($debug_fhand, $str."\n");
	fclose($debug_fhand);
}

// TC (Tim Curtis) 2015-06-26: add debug logging
function loadAllLib($sock) {
	// TC (Tim Curtis) 2015-06-26: debug
	$debug_flags = str_replace("\n", '', explode(',', file_get_contents("/var/www/liblog.conf")));
	// write out the debug flags
	libLog("debug flags= ".$debug_flags[0].",".$debug_flags[1].",".$debug_flags[2].",".$debug_flags[3].",".$debug_flags[4], true); 

// 	sendMpdCommand($sock, "find modified-since 36500"); // number of days
// 	$response = readMpdResponse($sock);
// 	$debug_fhand = fopen("/var/www/lib.txt",'w');
// 	// fwrite($debug_fhand, print_r($lib, true));
// 	fwrite($debug_fhand, $response);
// 	fclose($debug_fhand);
// echo($response);die;

	$lib = array();
	if (false !== ($count = _loadDirForLib($sock, $lib, $debug_flags))) {
		// TC (Tim Curtis) 2015-06-26: debug, #0 total count of files
		if ($debug_flags[0] == "y") {
			libLog("_loadAllLib() count= ".$count);
		}

		return json_encode($lib);
	}
}

// AG (Andreas Goetz) 2015-08-10: less memory-intensive library parsing
function _loadDirForLib($sock, &$lib, $debug_flags) {
	if ($debug_flags[4] == "1") {
		sendMpdCommand($sock, "find modified-since 36500"); // number of days
	} else if ($debug_flags[4] == "2") {
		sendMpdCommand($sock, "find modified-since 1901-01-01T00:00:00Z"); // full time stamp
	} else {
		sendMpdCommand($sock, "find modified-since 36500"); // default: number of days
	}

	$response = readMpdResponse($sock, true);
	if (strncmp(MPD_RESPONSE_OK, $response, strlen(MPD_RESPONSE_OK)) == 0 ||
		strncmp(MPD_RESPONSE_ERR, $response, strlen(MPD_RESPONSE_ERR)) == 0) 
	{
		if ($debug_flags[0] == "y") {
			libLog("_loadDirForLib() mpd error= ".$response);
		}
		return false; // empty
	}

	$libCount = 0;
	$item = array();

	while ($line = readMpdResponse($sock, true)) {
		// TC (Tim Curtis) 2014-09-17: add limit 2 to explode to avoid case where string contains more than one ":" (colon)
		list($element, $value) = explode(": ", rtrim($line), 2);
		if ($element == "file") {
			if (count($item)) {
				_libAddItem($lib, $item);
				$libCount++;
// if ($libCount > 1000) return $libCount;
				$item = array();
			}

			// TC (Tim Curtis) 2015-06-26: debug, #1 file name
			if ($debug_flags[1] == "y") {
				libLog("_loadDirForLib() item= ".$libCount.", file= ".$value);
			}
		}

		// TC (Tim Curtis) 2015-06-26: debug, #2 all elements
		if ($debug_flags[2] == "y") {
			libLog("_loadDirForLib() item= ".$libCount.", element= ".$element.", value= ".$value);
		}

		// TC (Tim Curtis) 2015-06-26: note, could thrift $element = Last-Modified, Date and Disc since not used in Player
		$item[$element] = $value;
	} 

	if (count($item)) {
		_libAddItem($lib, $item);
		$libCount++;
		$item = array();
	}

	return $libCount;
}

function _libAddItem(&$lib, $item) {
	$genre = $item["Genre"] ?: "Unknown";
	$artist = $item["Artist"] ?: "Unknown";
	$album = $item["Album"] ?: "Unknown";

	// placeholder for compilation data
	$albumInvalid = null;

	// compilation?
	if ($item['AlbumArtist'] && $item['Artist'] && 
		$item['AlbumArtist'] !== $item['Artist'])
	{
		// use AlbumArtist instead of Artist
		$artistInvalid = $artist;
		$artist = $item['AlbumArtist'];
		$albumInvalid = array();

		// Artist/Album need be remapped to AlbumArtist/Album
		if (isset($lib[$genre][$artistInvalid][$album])) {
			$albumInvalid = $lib[$genre][$artistInvalid][$album];
			unset($lib[$genre][$artistInvalid][$album]);

			// no more albums for invalid Artist
			if (0 === count($lib[$genre][$artistInvalid])) {
				unset($lib[$genre][$artistInvalid]);
			}
		}
	}

	if (!$lib[$genre]) {
		$lib[$genre] = array();
	}
	if (!$lib[$genre][$artist]) {
		$lib[$genre][$artist] = array();
	}
	if (!$lib[$genre][$artist][$album]) {
	    $lib[$genre][$artist][$album] = array();
	}

	if (isset($albumInvalid)) {
		$lib[$genre][$artist][$album] = array_merge($lib[$genre][$artist][$album], $albumInvalid);
	}

	$libItem = array(
		"file" => $item['file'], 
		"display" => ($item['Track'] ? $item['Track']." - " : "").$item['Title'], 
		"time" => $item['Time'],
		"time2" => songTime($item['Time'])
		// ,"x" => $item
	);

	array_push($lib[$genre][$artist][$album], $libItem);
}

// TC (Tim Curtis) 2014-09-17
// - comment out the clear command, move to playAllReplace()
// - comment out sending "play", its sent in the the caller (index.php)
function playAll($sock, $json) {
	$commands = array();
	//array_push($commands, "clear");
	foreach ($json as $song) {
		$path = $song["file"];
		array_push($commands, "add \"".html_entity_decode($path)."\"");
	}
	//array_push($commands, "play");
	chainMpdCommands($sock, $commands);
}
// TC (Tim Curtis) 2014-09-17
// - add/replece/playall
function playAllReplace($sock, $json) {
	$commands = array();
	array_push($commands, "clear");
	foreach ($json as $song) {
		$path = $song["file"];
		array_push($commands, "add \"".html_entity_decode($path)."\"");
	}
	array_push($commands, "play");
	chainMpdCommands($sock, $commands);
}

function enqueueAll($sock, $json) {
	$commands = array();
    foreach ($json as $song) {
		$path = $song["file"];
		array_push($commands, "add \"".html_entity_decode($path)."\"");
    }
	chainMpdCommands($sock, $commands);
}

// v2
function sendMpdIdle($sock) {
	sendMpdCommand($sock,"idle"); 
	$response = readMpdResponse($sock);
	return true;
}

function monitorMpdState($sock) {
	if (sendMpdIdle($sock)) {
		$status = _parseStatusResponse(MpdStatus($sock));
		return $status;
	}
}

function getTrackInfo($sock,$songID) {
	// set currentsong, currentartis, currentalbum
	sendMpdCommand($sock,"playlistinfo ".$songID);
	$track = readMpdResponse($sock);
	return _parseFileListResponse($track);
}

function getPlayQueue($sock) {
	sendMpdCommand($sock,"playlistinfo");
	$playqueue = readMpdResponse($sock);
	return _parseFileListResponse($playqueue);
}

// TC (Tim Curtis) 2014-09-17
// - list contents of saved playlist
// - remove saved playlist
function listPlayList($sock, $plname) {
	sendMpdCommand($sock,"listplaylist "." \"".$plname."\"");
	$plcontents = readMpdResponse($sock);
	return _parseFileListResponse($plcontents);
}
function removePlayList($sock, $plname) {
	sendMpdCommand($sock,"rm "." \"".$plname."\"");
	$response = readMpdResponse($sock);
	return $response;
}

function getTemplate($template) {
	return str_replace("\"","\\\"",implode("",file($template)));
}

function echoTemplate($template) {
	echo $template;
}

function searchDB($sock,$querytype,$query) {
	switch ($querytype) {
		case "filepath":
			if (isset($query) && !empty($query)){
				sendMpdCommand($sock,"lsinfo \"".html_entity_decode($query)."\"");
				break;
			} else {
				sendMpdCommand($sock,"lsinfo");
				break;
			}
		case "album":
		case "artist":
		case "title":
		case "file":
			sendMpdCommand($sock,"search ".$querytype." \"".html_entity_decode($query)."\"");
			//sendMpdCommand($sock,"search any \"".html_entity_decode($query)."\"");
			break;	
	}	
	//$response =  htmlentities(readMpdResponse($sock),ENT_XML1,'UTF-8');
	//$response = htmlspecialchars(readMpdResponse($sock));
	$response = readMpdResponse($sock);
	return _parseFileListResponse($response);
}

// TC (Tim Curtis) 2014-12-23
// - modify for track range begpos:endpos
// - return range instead of path
function remTrackQueue($sock,$songpos) {
	//$datapath = findPLposPath($songpos,$sock);
	$datapath = $songpos;
	sendMpdCommand($sock,"delete ".$songpos);
	$response = readMpdResponse($sock);
	return $datapath;
}

function addQueue($sock,$path) {
	$fileext = parseFileStr($path,'.');
	if ($fileext == 'm3u' OR $fileext == 'pls' OR strpos($path, '/') === false) {
		sendMpdCommand($sock,"load \"".html_entity_decode($path)."\"");
	} else {
		sendMpdCommand($sock,"add \"".html_entity_decode($path)."\"");
	}
	$response = readMpdResponse($sock);
	return $response;
}

function MpdStatus($sock) {
	sendMpdCommand($sock,"status");
	$status= readMpdResponse($sock);
	return $status;
}

// create JS like Timestamp
function jsTimestamp() {
	$timestamp = round(microtime(true) * 1000);
	return $timestamp;
}

function songTime($sec) {
	$minutes = sprintf('%02d', floor($sec / 60));
	$seconds = sprintf(':%02d', (int) $sec % 60);
	return $minutes.$seconds;
}

function phpVer() {
	$version = phpversion();
	return substr($version, 0, 3); 
}

// fix sessioni per ambienti PHP 5.3 (il solito WAMP di ACX...)
if (phpVer() == '5.3') {
	function session_status() {
		if (session_id()) {
			return 1;
		} else {
			return 2;
		}
	}
}

function sysCmd($syscmd) {
	exec($syscmd." 2>&1", $output);
	return $output;
}

// format Output for "playlist"
function _parseFileListResponse($resp) {
	if ( is_null($resp) ) {
		return NULL;
	} else {
		$plistArray = array();
		$plistLine = strtok($resp,"\n");
		$plistFile = "";
		$plCounter = -1;
		while ( $plistLine ) {
			// TC (Tim Curtis) 2014-09-17
			// add limit 2 to explode to handle case where string contains more than one ":" (colon)
			list ( $element, $value ) = explode(": ",$plistLine, 2);
			// TC (Tim Curtis) 2014-09-17, remove OR playlist in original stmt below
			// if ( $element == "file" OR $element == "playlist") {
			if ( $element == "file" ) {
				$plCounter++;
				$plistFile = $value;
				$plistArray[$plCounter]["file"] = $plistFile;
				$plistArray[$plCounter]["fileext"] = parseFileStr($plistFile,'.');
			} else if ( $element == "directory" ) {
				$plCounter++;
				// record directory index for further processing
				$dirCounter++;
				$plistFile = $value;
				$plistArray[$plCounter]["directory"] = $plistFile;
			// TC (Tim Curtis) 2014-09-17
			// - differentiate saved playlists from WEBRADIO playlist files
			} else if ( $element == "playlist" ) {
				if ( substr($value, 0, 8 ) == "WEBRADIO") {
					$plCounter++;
					$plistFile = $value;
					$plistArray[$plCounter]["file"] = $plistFile;
					$plistArray[$plCounter]["fileext"] = parseFileStr($plistFile,'.');
				} else {
					$plCounter++;
					$plistFile = $value;
					$plistArray[$plCounter]["playlist"] = $plistFile;
				}
			} else {
				$plistArray[$plCounter][$element] = $value;
				$plistArray[$plCounter]["Time2"] = songTime($plistArray[$plCounter]["Time"]);
			}

			$plistLine = strtok("\n");
		}
		
		// reverse MPD list output
		if (isset($dirCounter) && isset($plistArray[0]["file"]) ) {
			$dir = array_splice($plistArray, -$dirCounter);
			$plistArray = $dir + $plistArray;
		}
	}
	return $plistArray;
}

// format Output for "status"
// TC (Tim Curtis) 2015-06-26: add cases 22050 and 32000
function _parseStatusResponse($resp) {
	if ( is_null($resp) ) {
		return NULL;
	} else {
		$plistArray = array();
		$plistLine = strtok($resp,"\n");
		$plistFile = "";
		$plCounter = -1;
		while ( $plistLine ) {
			// TC (Tim Curtis) 2014-09-17
			// add limit 2 to explode to avoid case where string contains more than one ":" (colon)
			list ( $element, $value ) = explode(": ",$plistLine, 2);
			$plistArray[$element] = $value;
			$plistLine = strtok("\n");
		} 
		// "elapsed time song_percent" added to output array
		$time = explode(":", $plistArray['time']);
		if ($time[0] != 0) {
			$percent = round(($time[0]*100)/$time[1]);	
		} else {
			$percent = 0;
		}
		$plistArray["song_percent"] = $percent;
		$plistArray["elapsed"] = $time[0];
		$plistArray["time"] = $time[1];

		 // "audio format" output
	 	$audio_format = explode(":", $plistArray['audio']);
	 	// TC (Tim Curtis) 2015-06-26: add case 384000
		switch ($audio_format[0]) {
			// integer format
			case '32000';
			case '48000':
			case '96000':
			case '192000':
			case '384000':
			$plistArray['audio_sample_rate'] = rtrim(rtrim(number_format($audio_format[0]),0),',');
			break;
			// decimal format
			case '22050':
				$plistArray['audio_sample_rate'] = '22.05';
				break;
			case '44100':
			case '88200':
			case '176400':
			case '352800':
			$plistArray['audio_sample_rate'] = rtrim(number_format($audio_format[0],0,',','.'),0);
			break;
		}
		// format "audio_sample_depth" string
	 	$plistArray['audio_sample_depth'] = $audio_format[1];
	 	// format "audio_channels" string
	 	if ($audio_format[2] == "2") $plistArray['audio_channels'] = "Stereo";
	 	if ($audio_format[2] == "1") $plistArray['audio_channels'] = "Mono";
	 	if ($audio_format[2] > 2) $plistArray['audio_channels'] = "Multichannel";

	}
	return $plistArray;
}

// get file extension
function parseFileStr($strFile,$delimiter) {
	$pos = strrpos($strFile, $delimiter);
	$str = substr($strFile, $pos+1);
	return $str;
}

// cfg engine and session management
function playerSession($action,$db,$var = null, $value = null) {
	$status = session_status();	
	// open new PHP SESSION
	if ($action == 'open') {
		// check the PHP SESSION status
		if($status != 2) {
			// check presence of sessionID into SQLite datastore
			//debug
			// echo "<br>---------- READ SESSION -------------<br>";
			$sessionid = playerSession('getsessionid',$db);
			if (!empty($sessionid)) {
				// echo "<br>---------- SET SESSION ID-------------<br>";
				session_id($sessionid);
				session_start();
			} else {
				session_start();
				// echo "<br>---------- STORE SESSION -------------<br>";
				playerSession('storesessionid',$db);
			}
		}
		$dbh  = cfgdb_connect($db);
		// scan cfg_engine and store values in the new session
		$params = cfgdb_read('cfg_engine',$dbh);
		foreach ($params as $row) {
			$_SESSION[$row['param']] = $row['value'];
		}
		//debug
		//print_r($_SESSION);
	// close SQLite handle
	$dbh  = null;
	}

	// unlock PHP SESSION file
	if ($action == 'unlock') {
		session_write_close();
		// if (session_write_close()) {
			// return true;
		// }
	}
	
	// unset and destroy current PHP SESSION
	if ($action == 'destroy') {
	session_unset();
		if (session_destroy()) {
			$dbh  = cfgdb_connect($db);
			if (cfgdb_update('cfg_engine',$dbh,'sessionid','')) {
				$dbh = null;
				return true;
			} else {
				echo "cannot reset session on SQLite datastore";
				return false;
			}
		}
	}
	
	// store a value in the cfgdb and in current PHP SESSION
	if ($action == 'write') {
		$_SESSION[$var] = $value;
		$dbh  = cfgdb_connect($db);
		cfgdb_update('cfg_engine',$dbh,$var,$value);
		$dbh = null;
	}
	
	// record actual PHP Session ID in SQLite datastore
	if ($action == 'storesessionid') {
		$sessionid = session_id();
		playerSession('write',$db,'sessionid',$sessionid);
	}
	
	// read PHP SESSION ID stored in SQLite datastore and use it to "attatch" the same SESSION (used in worker)
	if ($action == 'getsessionid') {
		$dbh  = cfgdb_connect($db);
		$result = cfgdb_read('cfg_engine',$dbh,'sessionid');
		$dbh = null;
		return $result['0']['value'];
	}
	
}

function cfgdb_connect($dbpath) {
	if ($dbh  = new PDO($dbpath)) {
		return $dbh;
	} else {
		echo "cannot open the database";
		return false;
	}
}

function cfgdb_read($table,$dbh,$param = null,$id = null) {
	if(!isset($param)) {
		$querystr = 'SELECT * from '.$table;
	} else if (isset($id)) {
		$querystr = "SELECT * from ".$table." WHERE id='".$id."'";
	} else if ($param == 'mpdconf'){
		$querystr = "SELECT param,value_player FROM cfg_mpd WHERE value_player!=''";
	} else if ($param == 'mpdconfdefault') {
		$querystr = "SELECT param,value_default FROM cfg_mpd WHERE value_default!=''";
	// TC (Tim Curtis) 2015-03-21: add for audio device lookup
	// TC (Tim Curtis) 2015-07-31: specify fields instead of *
	} else if ($table == 'cfg_audiodev') {
		$querystr = 'SELECT name, dacchip, arch, iface, other from '.$table.' WHERE name="'.$param.'"';
	// TC (Tim Curtis) 2015-07-31: radio station table
	} else if ($table == 'cfg_radio') {
		$querystr = 'SELECT station, name, logo from '.$table.' WHERE station="'.$param.'"';
	} else {
		$querystr = 'SELECT value from '.$table.' WHERE param="'.$param.'"';
	}
	//debug
	error_log(">>>>> cfgdb_read(".$table.",dbh,".$param.",".$id.") >>>>> \n".$querystr, 0);
	$result = sdbquery($querystr,$dbh);
	return $result;
}

function cfgdb_update($table,$dbh,$key,$value) {
	switch ($table) {
		case 'cfg_engine':
			$querystr = "UPDATE ".$table." SET value='".$value."' where param='".$key."'";
			break;
		
		case 'cfg_lan':
			$querystr = "UPDATE ".$table." SET dhcp='".$value['dhcp']."', ip='".$value['ip']."', netmask='".$value['netmask']."', gw='".$value['gw']."', dns1='".$value['dns1']."', dns2='".$value['dns2']."' where name='".$value['name']."'";
			break;
		
		case 'cfg_mpd':
			$querystr = "UPDATE ".$table." set value_player='".$value."' where param='".$key."'";
			break;
		
		case 'cfg_wifisec':
			$querystr = "UPDATE ".$table." SET ssid='".$value['ssid']."', security='".$value['encryption']."', password='".$value['password']."' where id=1";
			break;
		
		case 'cfg_source':
			$querystr = "UPDATE ".$table." SET name='".$value['name']."', type='".$value['type']."', address='".$value['address']."', remotedir='".$value['remotedir']."', username='".$value['username']."', password='".$value['password']."', charset='".$value['charset']."', rsize='".$value['rsize']."', wsize='".$value['wsize']."', options='".$value['options']."', error='".$value['error']."' where id=".$value['id'];
			break;
	}
	//debug
	error_log(">>>>> cfgdb_update(".$table.",dbh,".$key.",".$value.") >>>>> \n".$querystr, 0);
	if (sdbquery($querystr,$dbh)) {
		return true;
	} else {
		return false;
	}
}

function cfgdb_write($table,$dbh,$values) {
	$querystr = "INSERT INTO ".$table." VALUES (NULL, ".$values.")";
	//debug
	error_log(">>>>> cfgdb_write(".$table.",dbh,".$values.") >>>>> \n".$querystr, 0);
	if (sdbquery($querystr,$dbh)) {
		return true;
	} else {
		return false;
	}
}

function cfgdb_delete($table,$dbh,$id) {
	if (!isset($id)) {
		$querystr = "DELETE FROM ".$table;
	} else {
		$querystr = "DELETE FROM ".$table." WHERE id=".$id;
	}
	//debug
	error_log(">>>>> cfgdb_delete(".$table.",dbh,".$id.") >>>>> \n".$querystr, 0);
	if (sdbquery($querystr,$dbh)) {
		return true;
	} else {
		return false;
	}
}

function sdbquery($querystr,$dbh) {
	$query = $dbh->prepare($querystr);
	if ($query->execute()) {
		$result = array();
		$i = 0;
		foreach ($query as $value) {
			$result[$i] = $value;
			$i++;
		}
		$dbh = null;
		if (empty($result)) {
			return true;
		} else {
			return $result;
		}
	} else {
		return false;
	}
}

// Ramplay functions
function rp_checkPLid($id,$mpd) {
	$_SESSION['DEBUG'] .= "rp_checkPLid:$id |";
	sendMpdCommand($mpd,'playlistid '.$id);
	$response = readMpdResponse($mpd);
	echo "<br>debug__".$response;
	echo "<br>debug__".stripos($response,'MPD error');
	if (stripos($response,'OK')) {
		return true;
	} else {
		return false;
	}
}

//## unire con findPLposPath
function rp_findPath($id,$mpd) {
	//$_SESSION['DEBUG'] .= "rp_findPath:$id |";
	sendMpdCommand($mpd,'playlistid '.$id);
	$idinfo = _parseFileListResponse(readMpdResponse($mpd));
	$path = $idinfo[0]['file'];
	//$_SESSION['DEBUG'] .= "Path:$path |";
	return $path;
}

//## unire con rp_findPath()
function findPLposPath($songpos,$mpd) {
	//$_SESSION['DEBUG'] .= "rp_findPath:$id |";
	sendMpdCommand($mpd,'playlistinfo '.$songpos);
	$idinfo = _parseFileListResponse(readMpdResponse($mpd));
	$path = $idinfo[0]['file'];
	//$_SESSION['DEBUG'] .= "Path:$path |";
	return $path;
}

function rp_deleteFile($id,$mpd) {
$_SESSION['DEBUG'] .= "rp_deleteFile:$id |";
	if (unlink(rp_findPath($id,$mpd))) {
		return true;
	} else {
		return false;
	}
}

function rp_copyFile($id,$mpd) {
	$_SESSION['DEBUG'] .= "rp_copyFile: $id|";
	$path = rp_findPath($id,$mpd);
	$song = parseFileStr($path,"/");
	$realpath = "/mnt/".$path;
	$ramplaypath = "/dev/shm/".$song;
	$_SESSION['DEBUG'] .= "rp_copyFilePATH: $path $ramplaypath|";
	if (copy($realpath, $ramplaypath)) {
		$_SESSION['DEBUG'] .= "rp_addPlay:$id $song $path $pos|";
		return $path;
	} else {
		return false;
	}
}

function rp_updateFolder($mpd) {
	$_SESSION['DEBUG'] .= "rp_updateFolder: |";
	sendMpdCommand($mpd,"update ramplay");
}

function rp_addPlay($path,$mpd,$pos) {
	$song = parseFileStr($path,"/");
	$ramplaypath = "ramplay/".$song;
	$_SESSION['DEBUG'] .= "rp_addPlay:$id $song $path $pos|";
	addQueue($mpd,$ramplaypath);
	sendMpdCommand($mpd,'play '.$pos);
}

function rp_clean() {
	$_SESSION['DEBUG'] .= "rp_clean: |";
	recursiveDelete('/dev/shm/');
}

function recursiveDelete($str){
	if (is_file($str)) {
		return @unlink($str);
		// aggiungere ricerca path in playlist e conseguente remove from playlist
	}
	else if (is_dir($str)) {
		$scan = glob(rtrim($str,'/').'/*');
		foreach($scan as $index=>$path){
			recursiveDelete($path);
		}
	}
}

function pushFile($filepath) {
	if (file_exists($filepath)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($filepath));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filepath));
		ob_clean();
		flush();
		readfile($filepath);
		return true;
	} else {
		return false;
	}
}

// check if mpd.conf or interfaces was modified outside
function hashCFG($action,$db) {
	playerSession('open',$db);
	switch ($action) {
		
//		case 'check_net':
//		$hash = md5_file('/etc/network/interfaces');
//		if ($hash != $_SESSION['netconfhash']) {
//			if ($_SESSION['netconf_advanced'] != 1) {
//			playerSession('write',$db,'netconf_advanced',1); 
//			}
//		return false;
//		} else {
//			if ($_SESSION['netconf_advanced'] != 0) {
//			playerSession('write',$db,'netconf_advanced',0);
//			}
//		}
//		break;
		
//		case 'check_mpd':
//		$hash = md5_file('/etc/mpd.conf');
//		if ($hash != $_SESSION['mpdconfhash']) {
//			if ($_SESSION['mpdconf_advanced'] != 1) {
//			playerSession('write',$db,'mpdconf_advanced',1); 
//			}
//		return false;
//		} else {
//			if ($_SESSION['mpdconf_advanced'] != 0) {
//			playerSession('write',$db,'mpdconf_advanced',0); 
//			}
//		}
//		break;
		
//		case 'check_source':
//		$hash = md5_file('/etc/auto.nas');
//		if ($hash != $_SESSION['sourceconfhash']) {
//			if ($_SESSION['sourceconf_advanced'] != 1) {
//			playerSession('write',$db,'sourceconf_advanced',1); 
//			}
//		return false;
//		} else {
//			if ($_SESSION['sourceconf_advanced'] != 0) {
//			playerSession('write',$db,'sourceconf_advanced',0); 
//			}
//		}
//		break;
		
//		case 'hash_net':
//		$hash = md5_file('/etc/network/interfaces');
//		playerSession('write',$db,'netconfhash',$hash); 
//		break;
		
//		case 'hash_mpd':
//		$hash = md5_file('/etc/mpd.conf');
//		playerSession('write',$db,'mpdconfhash',$hash); 
//		break;
		
//		case 'hash_source':
//		$hash = md5_file('/etc/auto.nas');
//		playerSession('write',$db,'sourceconfhash',$hash); 
//		break;
	} 
	playerSession('unlock');
	return true;
}

// debug functions
function debug($input) {
	session_start();
	// if $input = 1 clear SESSION debug data else load debug data into session
	if (isset($input) && $input == 1) {
		$_SESSION['debugdata'] = '';
	} else {
		$_SESSION['debugdata'] = $input;
	}
	session_write_close();
}

function debug_footer($db) {
	if ($_SESSION['debug'] > 0) {
		debug_output();
		debug(1);
		echo "\n";
		echo "###### System info ######\n";
		echo  file_get_contents('/proc/version');
		echo "\n";
		echo  "system load:\t".file_get_contents('/proc/loadavg');
		echo "\n";
		echo "HW platform:\t".$_SESSION['hwplatform']." (".$_SESSION['hwplatformid'].")\n";
		echo "\n";
		echo "playerID:\t".$_SESSION['playerid']."\n";
		echo "\n";
		echo "\n";
		echo "###### Audio backend ######\n";
		echo  file_get_contents('/proc/asound/version');
		echo "\n";
		echo "Card list: (/proc/asound/cards)\n";
		echo "--------------------------------------------------\n";
		echo  file_get_contents('/proc/asound/cards');
		echo "\n";
		echo "ALSA interface #0: (/proc/asound/card0/pcm0p/info)\n";
		echo "--------------------------------------------------\n";
		echo  file_get_contents('/proc/asound/card0/pcm0p/info');
		echo "\n";
		echo "ALSA interface #1: (/proc/asound/card1/pcm0p/info)\n";
		echo "--------------------------------------------------\n";
		echo  file_get_contents('/proc/asound/card1/pcm0p/info');
		echo "\n";
		echo "interface #0 stream status: (/proc/asound/card0/stream0)\n";
		echo "--------------------------------------------------------\n";
		$streaminfo = file_get_contents('/proc/asound/card0/stream0');
		if (empty($streaminfo)) {
		echo "no stream present\n";
		} else {
		echo $streaminfo;
		}
		echo "\n";
		echo "interface #1 stream status: (/proc/asound/card1/stream0)\n";
		echo "--------------------------------------------------------\n";
		$streaminfo = file_get_contents('/proc/asound/card1/stream0');
		if (empty($streaminfo)) {
		echo "no stream present\n";
		} else {
		echo $streaminfo;
		}
		echo "\n";
		echo "\n";
		echo "###### Kernel optimization parameters ######\n";
		echo "\n";
		echo "hardware platform:\t".$_SESSION['hwplatform']."\n";
		echo "current orionprofile:\t".$_SESSION['orionprofile']."\n";
		echo "\n";
		// 		echo  "kernel scheduler for mmcblk0:\t\t".((empty(file_get_contents('/sys/block/mmcblk0/queue/scheduler'))) ? "\n" : file_get_contents('/sys/block/mmcblk0/queue/scheduler'));
		echo  "kernel scheduler for mmcblk0:\t\t".file_get_contents('/sys/block/mmcblk0/queue/scheduler');
		echo  "/proc/sys/vm/swappiness:\t\t".file_get_contents('/proc/sys/vm/swappiness');
		echo  "/proc/sys/kernel/sched_latency_ns:\t".file_get_contents('/proc/sys/kernel/sched_latency_ns');
		echo  "/proc/sys/kernel/sched_rt_period_us:\t".file_get_contents('/proc/sys/kernel/sched_rt_period_us');
		echo  "/proc/sys/kernel/sched_rt_runtime_us:\t".file_get_contents('/proc/sys/kernel/sched_rt_runtime_us');
		echo "\n";
		echo "\n";
		echo "###### Filesystem mounts ######\n";
		echo "\n";
		echo  file_get_contents('/proc/mounts');
		echo "\n";
		echo "\n";
		echo "###### mpd.conf ######\n";
		echo "\n";
		echo file_get_contents('/etc/mpd.conf');
		echo "\n";
		}
		if ($_SESSION['debug'] > 1) {
		echo "\n";
		echo "\n";
		echo "###### PHP backend ######\n";
		echo "\n";
		echo "php version:\t".phpVer()."\n";
		echo "debug level:\t".$_SESSION['debug']."\n";
		echo "\n";
		echo "\n";
		echo "###### SESSION ######\n";
		echo "\n";
		echo "STATUS:\t\t".session_status()."\n";
		echo "ID:\t\t".session_id()."\n"; 
		echo "SAVE PATH:\t".session_save_path()."\n";
		echo "\n";
		echo "\n";
		echo "###### SESSION DATA ######\n";
		echo "\n";
		print_r($_SESSION);
		}
		if ($_SESSION['debug'] > 2) {
		$connection = new pdo($db);
		$querystr="SELECT * FROM cfg_engine";
		$data['cfg_engine'] = sdbquery($querystr,$connection);
		$querystr="SELECT * FROM cfg_lan";
		$data['cfg_lan'] = sdbquery($querystr,$connection);
		$querystr="SELECT * FROM cfg_wifisec";
		$data['cfg_wifisec'] = sdbquery($querystr,$connection);
		$querystr="SELECT * FROM cfg_mpd";
		$data['cfg_mpd'] = sdbquery($querystr,$connection);
		$querystr="SELECT * FROM cfg_source";
		$data['cfg_source'] = sdbquery($querystr,$connection);
		$connection = null;
		echo "\n";
		echo "\n";
		echo "###### SQLite datastore ######\n";
		echo "\n";
		echo "\n";
		echo "### table CFG_ENGINE ###\n";
		print_r($data['cfg_engine']);
		echo "\n";
		echo "\n";
		echo "### table CFG_LAN ###\n";
		print_r($data['cfg_lan']);
		echo "\n";
		echo "\n";
		echo "### table CFG_WIFISEC ###\n";
		print_r($data['cfg_wifisec']);
		echo "\n";
		echo "\n";
		echo "### table CFG_SOURCE ###\n";
		print_r($data['cfg_source']);
		echo "\n";
		echo "\n";
		echo "### table CFG_MPD ###\n";
		print_r($data['cfg_mpd']);
		echo "\n";
		}
		if ($_SESSION['debug'] > 0) {
		echo "\n";
		printf("Page created in %.5f seconds.", (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']));
		echo "\n";
		echo "\n";
	}
}

function debug_output($clear) {
	if (!empty($_SESSION['debugdata'])) {
		$output = print_r($_SESSION['debugdata']);
	}
	echo $output;
}

function waitWorker($sleeptime,$section) {
	if ($_SESSION['w_active'] == 1) {
		do {
			sleep($sleeptime);
			session_start();
			session_write_close();
		} while ($_SESSION['w_active'] != 0);

		switch ($section) {
			case 'sources':
			$mpd = openMpdSocket('localhost', 6600);
			sendMpdCommand($mpd,'update');
			closeMpdSocket($mpd);
			break;
		}
	}
} 

// search a string in a file and replace with another string the whole line.
function wrk_replaceTextLine($file,$pos_start,$pos_stop,$strfind,$strrepl) {
	$fileData = file($file);
	$newArray = array();
	foreach($fileData as $line) {
		// find the line that starts with $strfind (search offset $pos_start / $pos_stop)
		if (substr($line, $pos_start, $pos_stop) == $strfind OR substr($line, $pos_start++, $pos_stop) == $strfind) {
			// replace presentation_url with current IP address
			$line = $strrepl."\n";
	  	}
	  	$newArray[] = $line;
	}
	return $newArray;
}

// make device TOTALBACKUP (with switch DEV copy all /etc)
function wrk_backup($bktype) {
	if ($bktype == 'dev') {
		$filepath = "/run/totalbackup_".date('Y-m-d').".tar.gz";
		$cmdstring = "tar -czf ".$filepath." /var/lib/mpd /boot/cmdline.txt /var/www /etc";
	} else {
		$filepath = "/run/backup_".date('Y-m-d').".tar.gz";
		$cmdstring = "tar -czf ".$filepath." /var/lib/mpd /etc/auto.nas /etc/mpd.conf /var/www/db/player.db";
	}
	
sysCmd($cmdstring);
	return $filepath;
}

function wrk_restore($backupfile) {
	$path = "/run/".$backupfile;
	$cmdstring = "tar xzf ".$path." --overwrite --directory /";
	if (sysCmd($cmdstring)) {
		recursiveDelete($path);
	}
}

function wrk_jobID() {
	$jobID = md5(uniqid(rand(), true));
	return "job_".$jobID;
}

function wrk_checkStrSysfile($sysfile,$searchstr) {
	$file = stripcslashes(file_get_contents($sysfile));
	// debug
	//error_log(">>>>> wrk_checkStrSysfile(".$sysfile.",".$searchstr.") >>>>> ",0);
	if (strpos($file, $searchstr)) {
		return true;
	} else {
		return false;
	}
}

function wrk_mpdconf($outpath, $db, $kernelver, $i2s) {
	// extract mpd.conf from SQLite datastore
	$dbh = cfgdb_connect($db);
	$query_cfg = "SELECT param,value_player FROM cfg_mpd WHERE value_player!=''";
	$mpdcfg = sdbquery($query_cfg,$dbh);
	$dbh = null;
	
	// set mpd.conf file header
	// TC (Tim Curtis) 2015-04-29: remove tabs, cleanup formatting
	$output =  "#########################################\n";
	$output .= "# This file is automatically generated by\n";
	$output .= "# the Player via MPD Configuration page. \n";
	$output .= "#########################################\n";
	$output .= "\n";
	/*
	// parse DB output (original)
	foreach ($mpdcfg as $cfg) {
		if ($cfg['param'] == 'audio_output_format' && $cfg['value_player'] == 'disabled'){
			$output .= '';
		} else if ($cfg['param'] == 'dsd_usb') {
			$dsd = $cfg['value_player'];
		} else if ($cfg['param'] == 'device') {
			$device = $cfg['value_player'];
			var_export($device);
			// $output .= '';
		} else if ($cfg['param'] == 'mixer_type' && $cfg['value_player'] == 'hardware' ) { 
			// $hwmixer['device'] = 'hw:0';
			$hwmixer['control'] = alsa_findHwMixerControl($device, $kernelver, $i2s);
			// $hwmixer['index'] = '1';
		}  else {
			$output .= $cfg['param']." \"".$cfg['value_player']."\"\n";
		}
	}
	*/
	
	// parse DB output
	// TC (Tim Curtis) 2015-06-26; store volume mixer type in tcmods.conf
	// TC (Tim Curtis) 2015-06-26; use GetMixerName() instead of alsa_findHwMixerControl()
	foreach ($mpdcfg as $cfg) {
		if ($cfg['param'] == 'audio_output_format' && $cfg['value_player'] == 'disabled'){
			$output .= '';
		} else if ($cfg['param'] == 'dsd_usb') {
			$dsd = $cfg['value_player'];
		} else if ($cfg['param'] == 'device') {
			$device = $cfg['value_player'];
			var_export($device);
			// $output .= '';
		} else if ($cfg['param'] == 'mixer_type') {
			// store volume mixer type in tcmods.conf
			$_tcmods_conf = _parseTcmodsConf(shell_exec('cat /var/www/tcmods.conf')); // read in conf file
			if ($_tcmods_conf['volume_mixer_type'] != $cfg['value_player']) {
				$_tcmods_conf['volume_mixer_type'] = $cfg['value_player'];
				$rtn = _updTcmodsConf($_tcmods_conf); // update conf file
			} 

			// get volume mixer control name
			if ($cfg['value_player'] == 'hardware' ) { 
				$hwmixer['control'] = getMixerName($kernelver, $i2s);
			} else {
				$output .= $cfg['param']." \"".$cfg['value_player']."\"\n";
			}
		} else {
			$output .= $cfg['param']." \"".$cfg['value_player']."\"\n";
		}
	}

	// format audio input / output interfaces
	$output .= "max_connections \"20\"\n";
	$output .= "\n";
	$output .= "decoder {\n";
	$output .= "plugin \"ffmpeg\"\n";
	$output .= "enabled \"yes\"\n";
	$output .= "}\n";
	$output .= "\n";
	$output .= "input {\n";
	$output .= "plugin \"curl\"\n";
	$output .= "}\n";
	$output .= "\n";
	$output .= "audio_output {\n";
	$output .= "type \"alsa\"\n";
	$output .= "name \"Output\"\n";
	$output .= "device \"hw:".$device.",0\"\n";
	if (isset($hwmixer)) {
		//$output .= "\t\t mixer_device \t\"".$hwmixer['device']."\"\n";
		$output .= "mixer_control \"".$hwmixer['control']."\"\n";
		$output .= "mixer_device \"hw:".$device."\"\n";
		$output .= "mixer_index \"0\"\n";
		//$output .= "\t\t mixer_index \t\"".$hwmixer['index']."\"\n";
	}
	$output .= "dsd_usb \"".$dsd."\"\n";
	$output .= "}\n";

	// write mpd.conf file
	$fh = fopen($outpath."/mpd.conf", 'w');
	fwrite($fh, $output);
	fclose($fh);
}
// TC (Tim Curtis) 2015-06-26: deprecated
/*
function alsa_findHwMixerControl($device, $kernelver, $i2s) {
	// TC (Tim Curtis) 2015-06-26: replace original code with a kernel version check to determine $hwmixerdev = PCM or Digital
	//playerSession('open',$db);
	//if ($_SESSION['kernelver'] == '3.18.11-v7+' || $_SESSION['kernelver'] == '3.18.14-v7+' || $_SESSION['kernelver'] == '3.18.11+' || $_SESSION['kernelver'] == '3.18.14+') {

	// TC (Tim Curtis) 2015-06-26: set simple mixer name based on kernel version and i2s vs USB
	$mixername = getMixerName($kernelver, $i2s);

	//if ($kernelver == '3.18.5+' || $kernelver == '3.18.11+' || $kernelver == '3.18.14+') {
	//	if ($i2s != 'I2S Off') {
	//		$mixername = 'Digital'; // i2s device 
	//	} else {
	//		$mixername = 'PCM'; // USB device 
	//	}
	//} else {
	//	$mixername = 'PCM'; // i2s and USB devices
	//}
	
	//$cmd = "amixer -c ".$device." |grep \"mixer control\"";
	//$str = sysCmd($cmd);
	//$hwmixerdev = substr(substr($str[0], 0, -(strlen($str[0]) - strrpos($str[0], "'"))), strpos($str[0], "'")+1);
	//return $hwmixerdev;
	
	return $mixername;
}
*/

function wrk_sourcemount($db,$action,$id) {
	switch ($action) {
		case 'mount':
			$dbh = cfgdb_connect($db);
			$mp = cfgdb_read('cfg_source',$dbh,'',$id);
			sysCmd("mkdir \"/mnt/NAS/".$mp[0]['name']."\"");
			if ($mp[0]['type'] == 'cifs') {
				// smb/cifs mount
				// TC (Tim Curtis) 2015-02-25: add single quotes around cifs mount password in case special chars like ; are used in pwd
				$mountstr = "mount -t cifs \"//".$mp[0]['address']."/".$mp[0]['remotedir']."\" -o username=".$mp[0]['username'].",password='".$mp[0]['password']."',rsize=".$mp[0]['rsize'].",wsize=".$mp[0]['wsize'].",iocharset=".$mp[0]['charset'].",".$mp[0]['options']." \"/mnt/NAS/".$mp[0]['name']."\"";
			} else {
				// nfs mount
				$mountstr = "mount -t nfs -o ".$mp[0]['options']." \"".$mp[0]['address'].":/".$mp[0]['remotedir']."\" \"/mnt/NAS/".$mp[0]['name']."\"";
			}
			// debug
			error_log(">>>>> mount string >>>>> ".$mountstr,0);
			$sysoutput = sysCmd($mountstr);
			error_log(var_dump($sysoutput),0);
			if (empty($sysoutput)) {
				if (!empty($mp[0]['error'])) {
					$mp[0]['error'] = '';
					cfgdb_update('cfg_source',$dbh,'',$mp[0]);
				}
				$return = 1;
			} else {
				sysCmd("rmdir \"/mnt/NAS/".$mp[0]['name']."\"");
				$mp[0]['error'] = implode("\n",$sysoutput);
				cfgdb_update('cfg_source',$dbh,'',$mp[0]);
				$return = 0;
			}	
			break;
		
		case 'mountall':
			$dbh = cfgdb_connect($db);
			$mounts = cfgdb_read('cfg_source',$dbh);
			foreach ($mounts as $mp) {
				if (!wrk_checkStrSysfile('/proc/mounts',$mp['name']) ) {
					$return = wrk_sourcemount($db,'mount',$mp['id']);
				}
			}
			$dbh = null;
			break;
	}
	return $return;
}

function wrk_sourcecfg($db,$queueargs) {
	$action = $queueargs['mount']['action'];
	unset($queueargs['mount']['action']);
	switch ($action) {
		case 'reset': 
			$dbh = cfgdb_connect($db);
			$source = cfgdb_read('cfg_source',$dbh);
				foreach ($source as $mp) {
					sysCmd("umount -f \"/mnt/NAS/".$mp['name']."\"");
					sysCmd("rmdir \"/mnt/NAS/".$mp['name']."\"");
				}
			if (cfgdb_delete('cfg_source',$dbh)) {
				$return = 1;
			} else {
				$return = 0;
			}
			$dbh = null;
			break;

		case 'add':
			$dbh = cfgdb_connect($db);
			print_r($queueargs);
			unset($queueargs['mount']['id']);
			// format values string
			foreach ($queueargs['mount'] as $key => $value) {
				if ($key == 'error') {
					$values .= "'".SQLite3::escapeString($value)."'";
					error_log(">>>>> values on line 1014 >>>>> ".$values, 0);
				} else {
					$values .= "'".SQLite3::escapeString($value)."',";
					error_log(">>>>> values on line 1016 >>>>> ".$values, 0);
				}
			}
			error_log(">>>>> values on line 1019 >>>>> ".$values, 0);
			// write new entry
			cfgdb_write('cfg_source',$dbh,$values);
			$newmountID = $dbh->lastInsertId();
			$dbh = null;
			if (wrk_sourcemount($db,'mount',$newmountID)) {
				$return = 1;
			} else {
				$return = 0;
			}
			break;
		
		case 'edit':
			$dbh = cfgdb_connect($db);
			$mp = cfgdb_read('cfg_source',$dbh,'',$queueargs['mount']['id']);
			cfgdb_update('cfg_source',$dbh,'',$queueargs['mount']);	
			sysCmd("umount -f \"/mnt/NAS/".$mp[0]['name']."\"");
			if ($mp[0]['name'] != $queueargs['mount']['name']) {
				sysCmd("rmdir \"/mnt/NAS/".$mp[0]['name']."\"");
				sysCmd("mkdir \"/mnt/NAS/".$queueargs['mount']['name']."\"");
			}
			if (wrk_sourcemount($db,'mount',$queueargs['mount']['id'])) {
				$return = 1;
			} else {
				$return = 0;
			}
			error_log(">>>>> wrk_sourcecfg(edit) exit status = >>>>> ".$return, 0);
			$dbh = null;
			break;
		
		case 'delete':
			$dbh = cfgdb_connect($db);
			$mp = cfgdb_read('cfg_source',$dbh,'',$queueargs['mount']['id']);
			sysCmd("umount -f \"/mnt/NAS/".$mp[0]['name']."\"");
			sysCmd("rmdir \"/mnt/NAS/".$mp[0]['name']."\"");
			if (cfgdb_delete('cfg_source',$dbh,$queueargs['mount']['id'])) {
				$return = 1;
			} else {
				$return = 0;
			}
			$dbh = null;
			break;
	}

	return $return;
}

function wrk_getHwPlatform() {
	$file = '/proc/cpuinfo';
	$fileData = file($file);
	foreach($fileData as $line) {
		if (substr($line, 0, 8) == 'Hardware') {
			$arch = trim(substr($line, 11, 50)); 
			switch($arch) {
				
				// RaspberryPi
				case 'BCM2708':
					$arch = '01';
					break;
				
				// UDOO
				case 'SECO i.Mx6 UDOO Board':
					$arch = '02';
					break;
				
				// CuBox
				case 'Marvell Dove (Flattened Device Tree)':
					$arch = '03';
					break;
				
				// BeagleBone Black
				case 'Generic AM33XX (Flattened Device Tree)':
					$arch = '04';
					break;
				
				// Compulab Utilite
				case 'Compulab CM-FX6':
					$arch = '05';
					break;
				
				// Wandboard
				case 'Freescale i.MX6 Quad/DualLite (Device Tree)':
					$arch = '06';
					break;
				
				default:
					$arch = '--';
					break;
			}
		}
	}
	if (!isset($arch)) {
		$arch = '--';
	}
	return $arch;
}

function wrk_setHwPlatform($db) {
	$arch = wrk_getHwPlatform();
	$playerid = wrk_playerID($arch);
	// register playerID into database
	playerSession('write',$db,'playerid',$playerid);
	// register platform into database
	switch($arch) {
		case '01':
			playerSession('write',$db,'hwplatform','RaspberryPi');
			playerSession('write',$db,'hwplatformid',$arch);
			break;
		
		case '02':
			playerSession('write',$db,'hwplatform','UDOO');
			playerSession('write',$db,'hwplatformid',$arch);
			break;
		
		case '03':
			playerSession('write',$db,'hwplatform','CuBox');
			playerSession('write',$db,'hwplatformid',$arch);
			break;
		
		case '04':
			playerSession('write',$db,'hwplatform','BeagleBone Black');
			playerSession('write',$db,'hwplatformid',$arch);
			break;
		
		case '05':
			playerSession('write',$db,'hwplatform','Compulab Utilite');
			playerSession('write',$db,'hwplatformid',$arch);
			break;
		
		case '06':
			playerSession('write',$db,'hwplatform','Wandboard');
			playerSession('write',$db,'hwplatformid',$arch);
			break;
		
		default:
			playerSession('write',$db,'hwplatform','unknown');
			playerSession('write',$db,'hwplatformid',$arch);
	}
}

function wrk_playerID($arch) {
	// $playerid = $arch.md5(uniqid(rand(), true)).md5(uniqid(rand(), true));
	$playerid = $arch.md5_file('/sys/class/net/eth0/address');
	return $playerid;
}

function wrk_sysChmod() {
	sysCmd('chmod -R 777 /var/www/db');
	sysCmd('chmod a+x /var/www/command/orion_optimize.sh');
	// TC (Tim Curtis) 2015-01-27
	// - moved from /home/... dir
	sysCmd('chmod a+x /var/www/command/unmute.sh');
	sysCmd('chmod 777 /run');
	sysCmd('chmod 777 /run/sess*');
	sysCmd('chmod a+rw /etc/mpd.conf');
}

// TC (Tim Curtis) 2015-07-31: shovel & broom remove
/*
function wrk_sysEnvCheck($arch,$install) {
	if ($arch == '01' OR $arch == '02' OR $arch == '03' OR $arch == '04' OR $arch == '05' OR $arch == '06') {
		// /etc/rc.local
		//$a = '/etc/rc.local';
		//$b = '/var/www/_OS_SETTINGS/etc/rc.local';
		//if (md5_file($a) != md5_file($b)) {
		//	sysCmd('cp '.$b.' '.$a);
		//}
		 
		// /etc/samba/smb.conf
		//$a = '/etc/samba/smb.conf';
		//$b = '/var/www/_OS_SETTINGS/etc/samba/smb.conf';
		//if (md5_file($a) != md5_file($b)) {
		//	sysCmd('cp '.$b.' '.$a.' ');
		//}
		// /etc/nginx.conf
		$a = '/etc/nginx/nginx.conf';
		$b = '/var/www/_OS_SETTINGS/etc/nginx/nginx.conf';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			// stop nginx
			sysCmd('killall -9 nginx');
			// start nginx
			sysCmd('nginx');
		}
		// /etc/php5/cli/php.ini
		$a = '/etc/php5/cli/php.ini';
		$b = '/var/www/_OS_SETTINGS/etc/php5/cli/php.ini';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			$restartphp = 1;
		}
		// /etc/php5/fpm/php-fpm.conf
		$a = '/etc/php5/fpm/php-fpm.conf';
		$b = '/var/www/_OS_SETTINGS/etc/php5/fpm/php-fpm.conf';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			$restartphp = 1;
		}
		// /etc/php5/fpm/php.ini
		$a = '/etc/php5/fpm/php.ini';
		$b = '/var/www/_OS_SETTINGS/etc/php5/fpm/php.ini';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			$restartphp = 1;
		}
	 
		if ($install == 1) {
			// remove autoFS for NAS mount
			sysCmd('cp /var/www/_OS_SETTINGS/etc/auto.master /etc/auto.master');
			sysCmd('rm /etc/auto.nas');
			sysCmd('service autofs restart');
			// /etc/php5/mods-available/apc.ini
			sysCmd('cp /var/www/_OS_SETTINGS/etc/php5/mods-available/apc.ini /etc/php5/mods-available/apc.ini');
			// /etc/php5/fpm/pool.d/ erase
			sysCmd('rm /etc/php5/fpm/pool.d/*');
			// /etc/php5/fpm/pool.d/ copy
			sysCmd('cp /var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/* /etc/php5/fpm/pool.d/');
			$restartphp = 1;
		}
		
		// /etc/php5/fpm/pool.d/command.conf
		$a = '/etc/php5/fpm/pool.d/command.conf';
		$b = '/var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/command.conf';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			$restartphp = 1;
		}
		// /etc/php5/fpm/pool.d/db.conf
		$a = '/etc/php5/fpm/pool.d/db.conf';
		$b = '/var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/db.conf';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			$restartphp = 1;
		}
		// /etc/php5/fpm/pool.d/display.conf
		$a = '/etc/php5/fpm/pool.d/display.conf';
		$b = '/var/www/_OS_SETTINGS/etc/php5/fpm/pool.d/display.conf';
		if (md5_file($a) != md5_file($b)) {
			sysCmd('cp '.$b.' '.$a.' ');
			$restartphp = 1;
		}
		// (RaspberryPi arch)
		//		if ($arch == '01') {
		//		$a = '/boot/cmdline.txt';
		//			$b = '/var/www/_OS_SETTINGS/boot/cmdline.txt';
		//			if (md5_file($a) != md5_file($b)) {
		//			sysCmd('cp '.$b.' '.$a.' ');
		// /etc/fstab
		//			$a = '/etc/fstab';
		//			$b = '/var/www/_OS_SETTINGS/etc/fstab_raspberry';
		//			if (md5_file($a) != md5_file($b)) {
		//				sysCmd('cp '.$b.' '.$a.' ');
		//				$reboot = 1;
		//				}
		//			}
		//		}

		if (isset($restartphp) && $restartphp == 1) {
			sysCmd('service php5-fpm restart');
		}
		if (isset($reboot) && $reboot == 1) {
			sysCmd('reboot');
		}
	}	
}
*/

// TC (Tim Curtis) 2014-12-23: add delay: 2000 (2 secs)
// TC (Tim Curtis) 2015-02-25: add optional delay duration arg
function ui_notify($notify) {
	$output .= "<script>";
	$output .= "jQuery(document).ready(function() {";
	$output .= "$.pnotify.defaults.history = false;";
	$output .= "$.pnotify({";
	$output .= "title: '".$notify['title']."',";
	$output .= "text: '".$notify['msg']."',";
	$output .= "icon: 'icon-ok',";
	if (isset($notify['duration'])) {	
		$output .= "delay: ".strval($notify['duration'] * 1000).",";
	} else {
		$output .= "delay: '2000',";
	}
	$output .= "opacity: .9});";
	$output .= "});";
	$output .= "</script>";
	echo $output;
}

function ui_lastFM_coverart($artist,$album,$lastfm_apikey) {
	$url = "http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=".$lastfm_apikey."&artist=".urlencode($artist)."&album=".urlencode($album)."&format=json";
	// debug
	//echo $url;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$output = curl_exec($ch);
	$output = json_decode($output,true);
	curl_close($ch);
	/* debug
	echo "<pre>";
	print_r($output);
	echo "</pre>";
	echo "<br>";
	*/
	// key [3] == extralarge last.fm image
	return $output['album']['image'][3]['#text'];
}

// ACX Functions
function sezione() {
	echo '<pre><strong>sezione</strong> = '.$GLOBALS['sezione'].'</pre>';
}

function ami($sz=null) {
	switch ($sz) {
		case 'index':
			echo (in_array($GLOBALS['sezione'], array(
				'index'
				))?'active':'');
			break;
		case 'sources':
			echo (in_array($GLOBALS['sezione'], array(
				'sources', 'sources-add', 'sources-edit'
				))?'active':'');
			break;
		case 'mpd-config':
			echo (in_array($GLOBALS['sezione'], array(
				'mpd-config'
				))?'active':'');
			break;
		case 'mpd-config-network':
			echo (in_array($GLOBALS['sezione'], array(
				'mpd-config-network'
				))?'active':'');
			break;
		case 'system':
			echo (in_array($GLOBALS['sezione'], array(
				'system'
				))?'active':'');
			break;
		case 'help':
			echo (in_array($GLOBALS['sezione'], array(
				'help'
				))?'active':'');
			break;
		case 'credits':
			echo (in_array($GLOBALS['sezione'], array(
				'credits'
				))?'active':'');
			break;
	}	
}

function current_item($sez=null) {
	echo (($GLOBALS['sezione'] == $sez)?' class="current"':'');
}
// end ACX Functions

// TC (Tim Curtis) 2014-12-23
// - tcmods functions

// OUTPUT: parse HW_PARAMS
function _parseHwParams($resp) {
	if (is_null($resp)) {
		return 'Error, _parseHwParams response is null';
	} else if ($resp != "closed\n") {
		$tcArray = array();
		$tcLine = strtok($resp,"\n");
		$tcFile = "";
		while ( $tcLine ) {
			list ( $element, $value ) = explode(": ",$tcLine);
			$tcArray[$element] = $value;
			$tcLine = strtok("\n");
		} 
		// format sample rate, ex: "44100 (44100/1)"
		// TC (Tim Curtis) 2015-06-26: add cases 22050, 32000, 384000
	 	$rate = substr($tcArray['rate'], 0, strpos($tcArray['rate'], ' ('));
	 	$_rate = (float)$rate;
		switch ($rate) {
			// integer format
			case '32000':
			case '48000':
			case '96000':
			case '192000':
			case '384000':
				$tcArray['rate'] = rtrim(rtrim(number_format($rate),0),',');
				break;
			// decimal format
			case '22050':
				$tcArray['rate'] = '22.05';
				break;
			case '44100':
			case '88200':
			case '176400':
			case '352800':
				$tcArray['rate'] = rtrim(number_format($rate,0,',','.'),0);
				break;
		}
		// format sample depth, ex "S24_3LE"
		$tcArray['format'] = substr($tcArray['format'], 1, 2);
		$_bits = (float)$tcArray['format'];
		// format channels, ex "2"
		$_chans = (float)$tcArray['channels'];
	 	if ($tcArray['channels'] == "2") $tcArray['channels'] = "Stereo";
	 	if ($tcArray['channels'] == "1") $tcArray['channels'] = "Mono";
	 	if ($tcArray['channels'] > 2) $tcArray['channels'] = "Multichannel";

		$tcArray['status'] = 'active';
		$tcArray['calcrate'] = number_format((($_rate * $_bits * $_chans) / 1000000),3,'.','');	 
	} else {
		$tcArray['status'] = 'closed';
		$tcArray['calcrate'] = '0 bps';	 
	}
	return $tcArray;
}

// INPUT: parse MPD currentsong
// TC (Tim Curtis) 2015-05-30: add limit 2 to explode to avoid incorrect parsing where string contains more than one ":" (colon)
function _parseMpdCurrentSong($mpd) {
	sendMpdCommand($mpd, 'currentsong');
	$resp = readMpdResponse($mpd);

	if (is_null($resp) ) {
		return 'Error, _parseMpdCurrentSong response is null';
	} else {
		$tcArray = array();
		$tcLine = strtok($resp,"\n");

		while ( $tcLine ) {
			list ( $element, $value ) = explode(": ",$tcLine, 2);
			$tcArray[$element] = $value;
			$tcLine = strtok("\n");
		}
	return $tcArray;
	}
}

// DSP: parse MPD Conf
function _parseMpdConf($dbh) {
	// read in mpd conf settings
	$mpdconf = cfgdb_read('',$dbh,'mpdconf');
	// prepare array
	$_mpd = array (
		'port' => '',
		'gapless_mp3_playback' => '',
		'auto_update' => '',
		'samplerate_converter' => '',
		'auto_update_depth' => '',
		'zeroconf_enabled' => '',
		'zeroconf_name' => '',
		'audio_output_format' => '',
		'mixer_type' => '',
		'audio_buffer_size' => '',
		'buffer_before_play' => '',
		'dsd_usb' => '',
		'device' => '',
		'volume_normalization' => ''
	);					
	
	// parse output for template
	foreach ($mpdconf as $key => $value) {
		foreach ($_mpd as $key2 => $value2) {
			if ($value['param'] == $key2) {
				$_mpd[$key2] = $value['value_player'];	
			}
		}
	}
		
	// parse audio output format, ex "44100:16:2"
 	$audio_format = explode(":", $_mpd['audio_output_format']);
 	// TC (Tim Curtis) 2015-06-26: add sample rate 384000
	switch ($audio_format[0]) {
		// integer format
		case '48000':
		case '96000':
		case '192000':
		case '384000':
			$_mpd['audio_sample_rate'] = rtrim(rtrim(number_format($audio_format[0]),0),',');
			break;
		
		// decimal format
		case '44100':
		case '88200':
		case '176400':
		case '352800':
			$_mpd['audio_sample_rate'] = rtrim(number_format($audio_format[0],0,',','.'),0);
			break;
	}
	// add sample depth, ex "16"
 	$_mpd['audio_sample_depth'] = $audio_format[1];
 	// add channels, ex "2"
 	if ($audio_format[2] == "2") $_mpd['audio_channels'] = "Stereo";
 	if ($audio_format[2] == "1") $_mpd['audio_channels'] = "Mono";
 	if ($audio_format[2] > 2) $_mpd['audio_channels'] = "Multichannel";
	
	return $_mpd;
}
	
// DEVICE: parse /var/www/tcmods.conf
function _parseTcmodsConf($resp) {
		if (is_null($resp) ) {
			return 'Error, _parseTcmodsConf response is null';
		} else {
			$tcArray = array();
			$tcLine = strtok($resp,"\n");
			$tcFile = "";

			while ( $tcLine ) {
				list ( $element, $value ) = explode(": ",$tcLine);
				$tcArray[$element] = $value;
				$tcLine = strtok("\n");
			} 
		}
	return $tcArray;
}

// TC (Tim Curtis) 2014-12-23
// parse radio station file
function _parseStationFile($resp) {
		if (is_null($resp) ) {
			return 'Error, _parseStationFile response is null';
		} else {
			$tcArray = array();
			$tcLine = strtok($resp,"\n");
			$tcFile = "";

			while ( $tcLine ) {
				list ( $element, $value ) = explode("=",$tcLine);
				$tcArray[$element] = $value;
				$tcLine = strtok("\n");
			} 
		}
	return $tcArray;
}

// TC (Tim Curtis) 2015-02-25: update tcmods.conf file
// TC (Tim Curtis) 2015-04-29: add theme_color element
// TC (Tim Curtis) 2015-05-30: add play_history_ elements
// TC (Tim Curtis) 2015-06-26: add volume_ elements to tcmods.conf for logarithmic volume control and improved mute
// TC (Tim Curtis) 2015-06-26: add albumart_lookup_method
function _updTcmodsConf($tcmconf) {
	// Open file for write, clears contents
	$_file = '/var/www/tcmods.conf';
	$handle = fopen($_file, 'w') or die('tcmods.php: file open failed on '.$_file); // creates file if none exists
	// format conf lines
	$data = 'albumart_lookup_method: '.$tcmconf['albumart_lookup_method']."\n";
	$data .= 'audio_device_name: '.$tcmconf['audio_device_name']."\n";
	$data .= 'audio_device_dac: '.$tcmconf['audio_device_dac']."\n";
	$data .= 'audio_device_arch: '.$tcmconf['audio_device_arch']."\n";
	$data .= 'audio_device_iface: '.$tcmconf['audio_device_iface']."\n";
	$data .= 'audio_device_other: '.$tcmconf['audio_device_other']."\n";
	$data .= 'clock_radio_enabled: '.$tcmconf['clock_radio_enabled']."\n";
	$data .= 'clock_radio_playitem: '.$tcmconf['clock_radio_playitem']."\n";
	$data .= 'clock_radio_playname: '.$tcmconf['clock_radio_playname']."\n";
	$data .= 'clock_radio_starttime: '.$tcmconf['clock_radio_starttime']."\n";
	$data .= 'clock_radio_stoptime: '.$tcmconf['clock_radio_stoptime']."\n";
	$data .= 'clock_radio_volume: '.$tcmconf['clock_radio_volume']."\n";
	$data .= 'clock_radio_shutdown: '.$tcmconf['clock_radio_shutdown']."\n";
	$data .= 'play_history_currentsong: '.$tcmconf['play_history_currentsong']."\n";
	$data .= 'play_history_enabled: '.$tcmconf['play_history_enabled']."\n";
	$data .= 'search_autofocus_enabled: '.$tcmconf['search_autofocus_enabled']."\n";
	$data .= 'sys_kernel_ver: '.$tcmconf['sys_kernel_ver']."\n";
	$data .= 'sys_processor_arch: '.$tcmconf['sys_processor_arch']."\n";
	$data .= 'sys_mpd_ver: '.$tcmconf['sys_mpd_ver']."\n";
	$data .= 'time_knob_countup: '.$tcmconf['time_knob_countup']."\n";
	$data .= 'theme_color: '.$tcmconf['theme_color']."\n";
	$data .= 'volume_curve_factor: '.$tcmconf['volume_curve_factor']."\n";
	$data .= 'volume_curve_logarithmic: '.$tcmconf['volume_curve_logarithmic']."\n";
	$data .= 'volume_knob_setting: '.$tcmconf['volume_knob_setting']."\n";
	$data .= 'volume_max_percent: '.$tcmconf['volume_max_percent']."\n";
	$data .= 'volume_mixer_type: '.$tcmconf['volume_mixer_type']."\n";
	$data .= 'volume_muted: '.$tcmconf['volume_muted']."\n";
	$data .= 'volume_warning_limit: '.$tcmconf['volume_warning_limit']."\n";
	// Write data, close file
	fwrite($handle, $data);
	fclose($handle);
	
	return '_updTcmodsConf: update tcmods.conf complete';
}

	
// TC (Tim Curtis) 2015-05-30: parse play history log
function _parsePlayHistory($resp) {
		if (is_null($resp) ) {
			return 'Error, _parsePlayHistory response is null';
		} else {
			$tcArray = array();
			$tcLine = strtok($resp,"\n");
			$i = 0;
			
			while ( $tcLine ) {
				$tcArray[$i] = $tcLine;
				$i++;
				$tcLine = strtok("\n");
			} 
		}
	return $tcArray;
}

// TC (Tim Curtis) 2015-05-30: update play history log
function _updatePlayHistory($currentsong) {
	// Open file for write w/append
	$_file = '/var/www/playhistory.log';
	$handle = fopen($_file, 'a') or die('tcmods.php: file open failed on '.$_file); // creates file if none exists
	// Append data, close file
	fwrite($handle, $currentsong."\n");
	fclose($handle);
	
	return '_updatePlayHistory: update playhistory.log complete';
}

// TC (Tim Curtis) 2015-02-25: for 3.18 kernels
// TC (Tim Curtis) 2015-03-21: add IQaudIO Pi-AMP+
// TC (Tim Curtis) 2015-04-29: add RaspyPlay4
// TC (Tim Curtis) 2015-04-29: add Durio Sound PRO
// TC (Tim Curtis) 2015-06-26: add IQaudIO Pi-DigiAMP+ and Hifimediy ES9023 
// TC (Tim Curtis) 2015-07-31: add Audiophonics I-Sabre DAC ES9023 TCXO
function _setI2sDtoverlay($db, $device) {
	$file = '/etc/modules';
	if ($device == 'I2S Off') {
		$text = "# I2S output deactivated\n";
		$text .= "snd-bcm2835\n";
		file_put_contents($file, $text);
	} else {
		$text = "# Device Tree Overlay being used\n";
		file_put_contents($file, $text);
		switch ($device) {
			case 'G2 Labs BerryNOS':
			case 'G2 Labs BerryNOS Red': // use hifiberry driver
				sysCmd('echo dtoverlay=hifiberry-dac >> /boot/config.txt');
				break;
			case 'Durio Sound PRO':
			case 'Hifimediy ES9023':
			case 'Audiophonics I-Sabre DAC ES9023 TCXO':
			case 'HiFiBerry DAC':
				sysCmd('echo dtoverlay=hifiberry-dac >> /boot/config.txt');
				break;
			case 'HiFiBerry DAC+':
				sysCmd('echo dtoverlay=hifiberry-dacplus >> /boot/config.txt');
				break;
			case 'HiFiBerry Digi(Digi+)':
				sysCmd('echo dtoverlay=hifiberry-digi >> /boot/config.txt');
				break;
			case 'HiFiBerry Amp(Amp+)':
				sysCmd('echo dtoverlay=hifiberry-amp >> /boot/config.txt');
				break;
			case 'RaspyPlay4':
			case 'IQaudIO Pi-DAC':
				sysCmd('echo dtoverlay=iqaudio-dac >> /boot/config.txt');
				break;
			case 'IQaudIO Pi-DAC+':
			case 'IQaudIO Pi-AMP+':
			case 'IQaudIO Pi-DigiAMP+':
				sysCmd('echo dtoverlay=iqaudio-dacplus >> /boot/config.txt');
				break;
			case 'RPi DAC': // exception since there is no dtoverlay driver for this dac in 3.18
				sysCmd('echo dtoverlay= >> /boot/config.txt');
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "snd_soc_bcm2708_i2s\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm5102a\n";
				$text .= "snd_soc_rpi_dac\n";
				file_put_contents($file, $text);
				break;
			case 'Generic': // use hifiberry driver
				sysCmd('echo dtoverlay=hifiberry-dac >> /boot/config.txt');
				break;
		}
	}
}
	
// TC (Tim Curtis) 2015-02-25: for pre 3.18 kernels	
function _setI2sModules($db, $device) {
	$file = '/etc/modules';
	if ($device == 'I2S Off') {
		$text = "# I2S output deactivated\n";
		$text .= "snd-bcm2835\n";
		file_put_contents($file, $text);
	} else {
		switch ($device) {
			case 'G2 Labs BerryNOS':
			case 'G2 Labs BerryNOS Red':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm5102a\n";
				$text .= "snd_soc_hifiberry_dac\n";
				file_put_contents($file, $text);
				break;
			case 'HiFiBerry DAC':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm5102a\n";
				$text .= "snd_soc_hifiberry_dac\n";
				file_put_contents($file, $text);
				break;
			case 'HiFiBerry DAC+':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm512x\n";
				$text .= "snd_soc_hifiberry_dacplus\n";
				file_put_contents($file, $text);
				break;
			case 'HiFiBerry Digi(Digi+)':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_hifiberry_digi\n";
				file_put_contents($file, $text);
				break;
			case 'HiFiBerry Amp(Amp+)':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_hifiberry_amp\n";
				file_put_contents($file, $text);
				break;
			case 'IQaudIO Pi-DAC':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "snd_soc_bcm2708_i2s\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm512x\n";
				$text .= "snd_soc_iqaudio_dac\n";
				file_put_contents($file, $text);
				break;
			case 'IQaudIO Pi-DAC+':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "snd_soc_bcm2708_i2s\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm512x\n";
				$text .= "snd_soc_iqaudio_dac\n";
				file_put_contents($file, $text);
				break;
			case 'RPi DAC':
				$text = "# ". $device."\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "snd_soc_bcm2708_i2s\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm5102a\n";
				$text .= "snd_soc_rpi_dac\n";
				file_put_contents($file, $text);
				break;
			case 'Generic':
				$text = "# Generic I2S driver\n";
				$text .= "snd_soc_bcm2708\n";
				$text .= "snd_soc_bcm2708_i2s\n";
				$text .= "bcm2708_dmaengine\n";
				$text .= "snd_soc_pcm5102a\n";
				$text .= "snd_soc_pcm512x\n";
				$text .= "snd_soc_hifiberry_dac\n";
				$text .= "snd_soc_rpi_dac\n";
				file_put_contents($file, $text);
				break;
		}
	}
}

// TC (Tim Curtis) 2015-06-26: return kernel version number without "-v7" suffix
function getKernelVer($kernel) {
	return str_replace('-v7', '', $kernel);
}

// TC (Tim Curtis) 2015-06-26: return mixer name based on kernel version and i2s vs USB
// TC (Tim Curtis) 2015-06-26: set mixer name to "Master" for Hifiberry Amp(Amp+)
function getMixerName($kernelver, $i2s) {
	if ($i2s != 'I2S Off') {
		if ($i2s == 'HiFiBerry Amp(Amp+)') {
			$mixername = 'Master'; // Hifiberry Amp(Amp+) i2s device 
		} else {				
			if ($kernelver == '3.18.11+' || $kernelver == '3.18.14+') {
				$mixername = 'Digital'; // default for these kernels
			} else {
				$mixername = 'PCM'; // default for 3.18.5+
			}
		}
	} else {
		$mixername = 'PCM'; // USB devices
	}

	return $mixername;
}
