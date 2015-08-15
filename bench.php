<?php

error_reporting(E_ALL);

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
	global $cnt;

echo("loadAllLib\n");
	// TC (Tim Curtis) 2015-06-26: debug
	$debug_flags = str_replace("\n", '', explode(',', file_get_contents("/var/www/liblog.conf")));
	// write out the debug flags
	libLog("debug flags= ".$debug_flags[0].",".$debug_flags[1].",".$debug_flags[2].",".$debug_flags[3].",".$debug_flags[4], true); 

	// sendMpdCommand($sock, "find modified-since 36500"); // number of days
	// $response = readMpdResponse($sock);
	// $debug_fhand = fopen("/var/www/lib.txt",'w');
	// // fwrite($debug_fhand, print_r($lib, true));
	// fwrite($debug_fhand, $response);
	// fclose($debug_fhand);
	// echo($response);die;

	$lib = array();
	if (false !== ($cnt = _loadDirForLib($sock, $lib, $debug_flags))) {
		// TC (Tim Curtis) 2015-06-26: debug, #0 total $cnt of files
		if ($debug_flags[0] == "y") {
			libLog("_loadAllLib() $cnt= ".$cnt);
		}

		return json_encode($lib);
	}
}

// AG (Andreas Goetz) 2015-08-10: less memory-intensive library parsing
function _loadDirForLib($sock, &$lib, $debug_flags) {
echo("_loadDirForLib\n");
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
echo "error";
		if ($debug_flags[0] == "y") {
			libLog("_loadDirForLib() mpd error= ".$response);
		}
		return false; // empty
	}

	$libCount = 0;
	$item = array();
echo "starting\n";

	while ($line = readMpdResponse($sock, true)) {
		// TC (Tim Curtis) 2014-09-17: add limit 2 to explode to avoid case where string contains more than one ":" (colon)
		list($element, $value) = explode(": ", rtrim($line), 2);
		if ($element == "file") {
			if (count($item)) {
				_libAddItem($lib, $item);
				$libCount++;
if ($libCount > 1000) return $libCount;
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

function songTime($sec) {
	$minutes = sprintf('%02d', floor($sec / 60));
	$seconds = sprintf(':%02d', (int) $sec % 60);
	return $minutes.$seconds;
}

$sock = openMpdSocket('127.0.0.1', 6600);
echo($sock."\n");

$t = microtime(true);
sendMpdCommand($sock, "find modified-since 36500"); // number of days
readMpdResponse($sock);
$t = microtime(true) - $t;
printf("%fs read time\n", $t);

$t = microtime(true);
loadAllLib($sock);
$t = microtime(true) - $t;
printf("%fs %f ms/item\n", $t, 1000*$t/$cnt);
