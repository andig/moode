/**
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
 * Rewrite by Tim Curtis and Andreas Goetz
 */

// GLOBAL DATA
// GUI array global
var GUI = {
	json: 0,
	cmd: 'status',
	playlist: null,
	currentsong: null,
	currentknob: null,
	state: '',
	currentpath: '',
	halt: 0,
	volume: null,
	currentDBpos: new Array(0,0,0,0,0,0,0,0,0,0,0),
	// TC (Tim Curtis) 2014-10-31: add DBentry[3] to store GUI row pos of song item so highlight can be removed after context menu action
	// TC (Tim Curtis) 2014-12-23: add DBentry[4] to store num playlist items for use by delete/move item modals
	DBentry: new Array('', '', '', '', ''),
	visibility: 'visible',
	DBupdate: 0
};

// Library search filter globals
filters = {
	artists: [],
	genres: [],
	albums: []
};
// TC (Tim Curtis): 2015-04-29: Library globals so renderSongs() can decide whether to display tracks
var totalSongs = 0;
var albumClicked = false;

// MPD currentsong globals
var MPDCS = {
	json: 0,
	artist: '',
	title: '',
	album: '',
	lasttitle: 'abc',
	stnlogoroot: 'images/webradio-logos/', // webradio station logos
	coverroot: 'coverroot/',
	defaultcover: 'images/default-cover.jpg',
	webradiocover: 'images/webradio-cover.jpg',
	coverurl: '',
	coverimg: ''
};

// TC (Tim Curtis) 2014-11-30: global for tcmods.conf file
// TC (Tim Curtis) 2015-01-27: add element volume_warning_limit
// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
// TC (Tim Curtis) 2015-01-27: add search_auto_focus
// TC (Tim Curtis) 2015-02-25: add sys_ items
// TC (Tim Curtis) 2015-04-29: add theme_color
// TC (Tim Curtis) 2015-05-30: add play_history_currentsong
// TC (Tim Curtis) 2015-06-26: add volume_ elements and albumart_lookup_method
var TCMCONF = {
	json: 0
};

// TC (Tim Curtis) 2014-11-30: global for total track time, used in Library meta area
var totalTime = 0;

// TC (Tim Curtis) 2015-01-27: global for indicating time knob slider paint is complete
var timeKnobPaintComplete = false;

// FUNCTIONS SECTION

// Get MPD currentsong data and format for GUI display as either Webradio or file source
// TC (Tim Curtis) 2014-08-23: initial version 
// TC (Tim Curtis) 2014-08-23: construct URL to cover art using song file path
// TC (Tim Curtis) 2014-10-31: use makeCoverURL() function instead of inline code
// TC (Tim Curtis) 2014-10-31: remove any embedded dbl quotes in webradio station names for display
// TC (Tim Curtis) 2014-10-31: add makeWebradioLogoURL() function for webradio station logo
// TC (Tim Curtis) 2015-06-26: add work-around logic to handle NTS Live station that sometimes does not xmit Name and Title, only file
// TC (Tim Curtis) 2015-07-31: updated logic throughout
function mpdCurrentSong() {
	$.ajax({
		type: 'GET',
		url: 'db/?cmd=currentsong',
		async: false, // Ensure data is current
		cache: false,
		success: function(data){
			GUI.halt = 1;
			MPDCS.json = data;
			//console.log('MPDCS.json=', MPDCS.json);

			// TC (Tim Curtis) 2015-07-31: updated logic
			if (typeof MPDCS.json.file != 'undefined') {
				// RADIO STATION
				if (typeof(MPDCS.json.Name) != 'undefined' || MPDCS.json.file.substr(0,4) == "http" && typeof(MPDCS.json.Artist) == 'undefined') {
					MPDCS.artist = 'Radio Station';
					MPDCS.title = (typeof(MPDCS.json.Title) == 'undefined') ? MPDCS.json.file : MPDCS.json.Title;

					var obj = getRadioInfo(MPDCS.json.file);
					if (obj == null) { // station not in db
						MPDCS.album = (typeof(MPDCS.json.Name) == 'undefined') ? "Unknown station name" : MPDCS.json.Name;
						MPDCS.coverurl = MPDCS.webradiocover; // default radio cover
					} else {
						MPDCS.album = (obj.name.substr(0,4) == "Soma") ? MPDCS.json.Name : obj.name; // use transmitted name for Soma stations
						if (obj.logo == "local") {
							MPDCS.coverurl = MPDCS.stnlogoroot + obj.name + ".png";
						} else {
							MPDCS.coverurl = obj.logo; // Soma stations
						}
					}
				// SONG FILE OR UPNP SONG URL
				} else {
					MPDCS.artist = (typeof(MPDCS.json.Artist) == 'undefined') ? "Unknown artist" : MPDCS.json.Artist;
					MPDCS.album = (typeof(MPDCS.json.Album) == 'undefined') ? "Unknown album" : MPDCS.json.Album;
					 // UPnP song url
					if (MPDCS.json.file.substr(0,4) == "http") {
						MPDCS.title = (typeof(MPDCS.json.Title) == 'undefined') ? MPDCS.json.file : MPDCS.json.Title;
						MPDCS.coverurl = makeUPNPCoverURL();
					// song file
					} else {
						if (typeof(MPDCS.json.Title) == 'undefined') { // use file name
							var pos = MPDCS.json.file.lastIndexOf(".");
							var filename = MPDCS.json.file.slice(0, pos);
							pos = filename.lastIndexOf("/");
							MPDCS.title = filename.slice(pos + 1);
						} else {
							MPDCS.title = MPDCS.json.Title; // use title
						}
						MPDCS.coverurl = makeCoverURL(MPDCS.json.file);
					}
				} 
			} else {
				MPDCS.coverurl = MPDCS.defaultcover; // default cover
			}
		},
		error: function() {
			console.log('Error: mpdCurrentSong() no data returned');
		}
	});
}

/**
 * Convert song file path to a URL for cover art display
 */
function makeCoverURL(filepath) {
	var cover = '/mpodcover.php/' + encodeURIComponent(filepath);
	return cover;
}

/**
 * Json POST to tcmods.php
 */
function tcmodsPostCmd(cmd, data) {
	var result;

	$.ajax({
		type: 'POST',
		url: 'tcmods.php?cmd=' + cmd,
		async: false,
		data: data,
		success: function(json) {
			result = json;
		},
		error: function() {
			console.error('Could not POST tcmods.php?cmd=' + cmd);
		}
	});

	return result;
}

// TC (Tim Curtis) 2015-05-30: query UPnP and get cover art URL if available
function makeUPNPCoverURL() {
	var upnpCoverUrl = '';

	$.ajax({
		type: 'POST',
		url: 'tcmods.php?cmd=getupnpcoverurl',
		async: false, // Ensure data is current
		cache: false,
		success: function(json) {
			console.log(json); // debug
			upnpCoverUrl = json.coverurl;
		},
		error: function() {
			console.log('Error: getUPNPCoverURL() no data returned');
		}
	});

	return (upnpCoverUrl.substr(0,4) != "http") ? MPDCS.defaultcover : upnpCoverUrl;
}

// TC (Tim Curtis) 2015-03-21: query cfg_audiodev table, return audio device description fields
function getAudioDevDesc(audiodev) {
	return tcmodsPostCmd('getaudiodevdesc', {
		'audiodev': audiodev
	});
}

// TC (Tim Curtis) 2015-07-31: query cfg_radio table, return radio info fields
function getRadioInfo(station) {
	return tcmodsPostCmd('getradioinfo', {
		'station': station
	});
}

/**
 * Read tcmods.conf file
 */
function readTcmConf() {
	return tcmodsPostCmd('readtcmconf');
}

/**
 * Update tcmods.conf file
 */
function updateTcmConf() {
	return tcmodsPostCmd('updatetcmconf', TCMCONF.json);
}

/**
 * Get MPD status
 */
function getMpdStatus() {
	return tcmodsPostCmd('getmpdstatus');
}

/**
 * Read radio station file
 */
function readStationFile(path) {
	return tcmodsPostCmd('readstationfile', {
		'path': path
	});
}

/**
 * Read play history file
 */
function readPlayHistory() {
	return tcmodsPostCmd('readplayhistory');
}

/**
 * Send Amixer set volume command
 */
function sendAlsaCmd(cmd, level, scale) {
	return tcmodsPostCmd('sendalsacmd', {
		'alsacmd':cmd,
		'volumelevel':level,
		'scale':scale
	});
}

// Send MPD command to command/index.php
// AG: cmd by contain url-encoded additional parameters (yuck!)
function sendMpdCmd(cmd) {
	$.ajax({
		type: 'GET',
		url: 'db/?cmd=' + cmd,
		async: true,
		cache: false,
		success: function(data){
			GUI.halt = 1;
		},
	});
}

// Player/MPD state synchronization
// Infinite recursive call to _player_engine.php which sets up an MPD idle timeout loop that ends each time something changes in MPD
function backendRequest() {
	$.ajax({
		type: 'GET',
		url: '_player_engine.php?state=' + GUI.state,
		async: true,
		cache: false,
		success: function(data) {
			renderUI(data);
			GUI.currentsong = GUI.json['currentsong'];
			backendRequest(GUI.state);
		},
		error: function() {
			setTimeout(function() {
				GUI.state = 'disconnected';
				$('#loader').show();
				$('#countdown-display').countdown('pause');
				window.clearInterval(GUI.currentKnob);
				backendRequest(GUI.state);
			}, 5000);
		}
	});
}

// Render GUI
function renderUI(json) {
	// Update global GUI array
	GUI.json = json;
	GUI.state = GUI.json['state'];
	//console.log('renderUI() GUI.json=', GUI.json);

	// Update UI
	updateGUI(GUI.json);

	if (GUI.state != 'disconnected') {
		$('#loader').hide();
	}

	// TC (Tim Curtis) 2014-11-30: count up or down depending on conf setting, radio always counts up
	// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
	if (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) {
		refreshTimer(parseInt(GUI.json['elapsed']), parseInt(GUI.json['time']), GUI.json['state']); // count up
	} else {
		refreshTimer(parseInt(GUI.json['time'] - parseInt(GUI.json['elapsed'])), 0, GUI.json['state']); // count down
	}

	// TC (Tim Curtis) 2015-01-27: only refresh knob if its display is not completly re-painted
	if (parseInt(GUI.json['time']) != 0) { // song file playing
		timeKnobPaintComplete = false;
	}
	if (GUI.json['state'] == 'play' || GUI.json['state'] == 'pause') {
		if (timeKnobPaintComplete == false) { // this will be true if radio station playing and knob paint is 100% complete
			refreshKnob(GUI.json);
		}
	}

	// Refresh playlist if indicated
	if (GUI.json['playlist'] != GUI.playlist) {
		getPlaylist(GUI.json);
		GUI.playlist = GUI.json['playlist'];
	}
	GUI.halt = 0;
}

// Get playlist
function getPlaylist(json) {
	$.getJSON('db/?cmd=playlist', function(data) {
		var i = 0;
		var content = '';
		var output = '';

		// Store num of playlist items in global for use by delete/move pl item modals
		if (typeof(data.length) != 'undefined') {
			GUI.DBentry[4] = data.length;
		} else {
			GUI.DBentry[4] = 0;
		}

		// Format playlist item
		if (data) {
			for (i = 0; i < data.length; i++) {
				// Playlist item active state
				if (json['state'] != 'stop' && i == parseInt(json['song'])) {
					content = '<li id="pl-' + (i + 1) + '" class="active clearfix">';
					//content = '<li id="pl-' + (i + 1) + '" class="active clearfix" draggable="true" ondragstart="drag(event)">';
				} else {
					content = '<li id="pl-' + (i + 1) + '" class="clearfix">';
					//content = '<li id="pl-' + (i + 1) + '" class="clearfix" draggable="true" ondragstart="drag(event)">';
				}
				// Action menu
				content += '<div class="pl-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-playlist-item"><i class="icon-reorder"></i></a></div>';

				// TC (Tim Curtis) 2015-07-31: updated logic throughout
				// RADIO STATION
				if (typeof(data[i].Name) != 'undefined' || (data[i].file.substr(0,4) == "http" && typeof(data[i].Artist) == 'undefined')) {
					// Playlist item 1st line, song title
					content += '<div class="pl-entry">';
					if (typeof(data[i].Title) == 'undefined') { // title
						content += "Streaming source";
					} else {
						content += data[i].Title + (typeof(data[i].Time) == 'undefined' ? '<em class="songtime"></em>' : ' <em class="songtime">' + timeConvert(data[i].Time) + '</em>');
					} 
					// Playlist item 2nd line, radio station name
					content += ' <span>';
					content += '<i class="icon-microphone"></i> '; // artist
					var obj = getRadioInfo(data[i].file);
					if (obj == null) { // station not in db
						content += (typeof(data[i].Name) == 'undefined') ? "Radio station" : data[i].Name;
					} else {
						content +=  obj.name;
					}
				// SONG FILE OR UPNP SONG URL
				} else {
					// Playlist item 1st line, song title
					content += '<div class="pl-entry">';
					if (typeof(data[i].Title) == 'undefined') { // use file name
						var pos = data[i].file.lastIndexOf(".");
						if (pos == -1) {
							content += data[i].file; // UPnP filenames have no .ext
						} else {
							var filename = data[i].file.slice(0, pos);
							pos = filename.lastIndexOf("/");
							content += filename.slice(pos + 1); // filename
						}
					} else {
						content += data[i].Title; // use title
					}
					// Playlist item 2nd line, artist - album
					content += ' <span>';
					content += (typeof(data[i].Artist) == 'undefined') ? "Unknown artist" : data[i].Artist;
					content += " - ";
					content += (typeof(data[i].Album) == 'undefined') ?  "Unknown album" : data[i].Album;
				}

				/*
				// ORIGINAL
				// Playlist item 1st line, song title
				content += '<div class="pl-entry">';
				if (typeof(data[i].Title) != 'undefined') {
					content += data[i].Title + (typeof(data[i].Time) == 'undefined' ? '<em class="songtime"></em>' : ' <em class="songtime">' + timeConvert(data[i].Time) + '</em>');
				} else {
					// TC (Tim Curtis) 2015-07-31: song file w/o title tag assigned to file instead of "Streaming source"
					if (typeof(data[i].Artist) == 'undefined') { // radio station
						content += "Streaming source";
					} else { // song file w/o title tag
						content += data[i].file;
					}
				}

				// Playlist item 2nd line, artist - album or radio station name
				content += ' <span>';
				if (typeof(data[i].Artist) == 'undefined') { // radio station
					content += '<i class="icon-microphone"></i> ';
				} else { // song file
					content += data[i].Artist;
				}

				// TC (Tim Curtis) 2015-07-31: get station name from db
				if (typeof(data[i].Album)  == 'undefined') { // radio station
					if (typeof(data[i].Name)  == 'undefined') {
						content += "Radio Station";
					} else {
						var obj = getRadioInfo(data[i].file);
						if (obj != null) { // station in db
							content +=  obj['name'];
						} else {
							content +=  data[i].Name;
						}
						//console.log('getRadioInfo3 obj=', obj);
					}
				} else { // song file
					content += ' - ' + data[i].Album;
				}
				*/

				content += '</span></div></li>';
				output = output + content;
			} // end for loop
		} // end if (data)

		$('ul.playlist').html(output);
	}); // end function (data)
}

// Parse file path
function parsePath(str) {
	var cutpos=str.lastIndexOf("/");

	if (cutpos !=-1) {
		var songpath = str.slice(0,cutpos);
	}  else {
		songpath = '';
	}

	return songpath;
}

// Parse response returned from MPD
function parseResponse(inputArr,respType,i,inpath) {
	switch (respType) {
		case 'playlist':
			// Placeholder
			break;

		case 'db':
			if (inpath == '' && typeof inputArr[i].file != 'undefined') {
				inpath = parsePath(inputArr[i].file)
			}
			if (typeof inputArr[i].file != 'undefined') {
				if (typeof inputArr[i].Title != 'undefined') {
					content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
					content += inputArr[i].file;
					// TC (Tim Curtis) 2014-09-17: data-target="#context-menu-item
					// TC (Tim Curtis) 2014-10-31: change to data-target="#context-menu-folder-item
					content += '"><div class="db-icon db-song db-browse"><i class="icon-music sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-folder-item"><i class="icon-reorder"></i></a></div><div class="db-entry db-song db-browse">';
					content += inputArr[i].Title + ' <em class="songtime">' + timeConvert(inputArr[i].Time) + '</em>';
					content += ' <span>';
					content +=  inputArr[i].Artist;
					content += ' - ';
					content +=  inputArr[i].Album;
					content += '</span></div></li>';
				} else {
					content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
					// TC (Tim Curtis) 2014-08-23: remove file extension (.pls, .m3u, etc.)
					// TC (Tim Curtis) 2014-09-17: don't touch if its a url (savedplaylist can contain url's)
					var filename = '';
					if (inputArr[i].file.substr(0,4) == "http") {
						filename = inputArr[i].file;
					} else {
						cutpos = inputArr[i].file.lastIndexOf(".");
						if (cutpos !=-1) {
							filename = inputArr[i].file.slice(0,cutpos);
						}
					}
					content += inputArr[i].file;
					// TC (Tim Curtis) 2014-09-17: data-target="#context-menu-item
					// TC (Tim Curtis) 2014-10-31: differentiate between WEBRADIO and Saved Playlist items and use appropriate action menu
					// TC (Tim Curtis) 2014-12-23: replace music icon with mic icon for radio station lines
					// TC (Tim Curtis) 2015-01-01: add item type text for 2nd line
					// TC (Tim Curtis) 2015-01-01: different icon for song file vs radio station in saved playlist
					var itemType = '';
					if(inputArr[i].file.substr(0,8) == "WEBRADIO") {
						content += '"><div class="db-icon db-song db-browse"><i class="icon-microphone sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-webradio-item"><i class="icon-reorder"></i></a></div><div class="db-entry db-song db-browse">';
						itemType = "Radio Station";
					} else {
						// TC (Tim Curtis) 2015-01-01: different icon and type text
						if (inputArr[i].file.substr(0,4) == "http") {
							content += '"><div class="db-icon db-song db-browse"><i class="icon-microphone sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-savedpl-item"><i class="icon-reorder"></i></a></div><div class="db-entry db-song db-browse">';
							itemType = "Radio Station";
						} else {
							content += '"><div class="db-icon db-song db-browse"><i class="icon-music sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-savedpl-item"><i class="icon-reorder"></i></a></div><div class="db-entry db-song db-browse">';
							itemType = "Song File";
						}
					}
					// TC (Tim Curtis) 2015-01-01: remove songtime, not needed
					// TC (Tim Curtis) 2015-01-01: change "path: WEBRADIO" to "Radio Station"
					content += filename.replace(inpath + '/', '');
					content += ' <span>';
					content += itemType;
					content += '</span></div></li>';
				}
			// TC (Tim Curtis) 2014-09-17: handle saved playlist
			} else if (typeof inputArr[i].playlist != 'undefined') {
				content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
				content += inputArr[i].playlist;
				content += '"><div class="db-icon db-folder db-browse"><i class="icon-list-ul icon-root sx db-browse"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-savedpl-root"><i class="icon-reorder"></i></a></div><div class="db-entry db-savedplaylist db-browse">';
				content += inputArr[i].playlist;
				content += '</div></li>';
			} else {
				content = '<li id="db-' + (i + 1) + '" class="clearfix" data-path="';
				content += inputArr[i].directory;
				if (inpath != '') {
					content += '"><div class="db-icon db-folder db-browse"><i class="icon-folder-open sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu"><i class="icon-reorder"></i></a></div><div class="db-entry db-folder db-browse">';
				} else {
					content += '"><div class="db-icon db-folder db-browse"><i class="icon-hdd icon-root sx"></i></div><div class="db-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-root"><i class="icon-reorder"></i></a></div><div class="db-entry db-folder db-browse">';
				}
				content += inputArr[i].directory.replace(inpath + '/', '');
				content += '</div></li>';
			}
		break;

	}
	return content;
} // end parseResponse()



function getDB(cmd, path, browsemode, uplevel) {
	var updateWebRadio = function() {
		return getDB('update', 'WEBRADIO').done(function() {
			return getDB('filepath', 'WEBRADIO');
		});
	};

	switch (cmd) {
		case 'filepath':
		case 'listsavedpl':
			return $.post('db/?cmd=' + cmd, {
				'path': path
			}, function(data) {
				populateDB(data, path, uplevel);
			}, 'json');
			break;
		case 'add':
		case 'addplay':
		case 'addreplaceplay':
		case 'update':
		case 'playall':
		case 'addall':
		case 'addallreplaceplay':
			return $.post('db/?cmd=' + cmd, {
				'path': path
			}, null, 'json');
			break;
		case 'deletesavedpl':
			return $.post('db/?cmd=' + cmd, {
				'path': path
			}, null, 'json').done(function() {
				return getDB('filepath', '', null, 0)
			});
			break;
		case 'deleteradiostn':
			return $.post('db/?cmd=' + cmd, {
				'path': path
			}, null, 'json').done(function() {
				return updateWebRadio();
			});
			break;
		case 'addradiostn':
		case 'updateradiostn':
			// TODO: check cmd sent- always addradiostn ?
			var arg = path.split("\n");
			return $.post('db/?cmd=addradiostn', {
				'path': arg[0],
				'url': arg[1]
			}, null, 'json').done(function() {
				// TODO: check if uplevel is required populateDB(data, 'WEBRADIO', 0);
				return updateWebRadio();
			});
			break;
		// TC (Tim Curtis) 2014-12-23: if no search keyword, dont post, clear search tally
		case 'search':
			var keyword = $('#db-search-keyword').val();
			if (keyword !== '') {
				return $.post('db/?querytype=' + browsemode + '&cmd=search', {
					'query': keyword
				}, function(data) {
					populateDB(data, path, uplevel, keyword);
				}, 'json');
			} else {
				$('#db-filter-results').html('');
			}
			break;
		default:
			console.error('Unknown command: ' + cmd);
	}
}

// Sleep (miliseconds)
// TC (Tim Curtis) 2014-11-30:  author "slhck" user/moderator on Super User forum
function sleep(milliseconds) {
  var start = new Date().getTime();
  for (var i = 0; i < 1e7; i++) {
	if ((new Date().getTime() - start) > milliseconds){
	  break;
	}
  }
}

// JSON object sort by property
// TC (Tim Curtis) 2014-08-23: author: Anthony Ryan Delorie, stackoverflow.com
// TC (Tim Curtis) 2015-01-01: add toLowerCase() to a, b to fix "case sensitive" bug
function sortJsonArrayByProperty(objArray, prop, direction){
	if (arguments.length<2) throw new Error("sortJsonArrayByProp requires 2 arguments");
	var direct = arguments.length>2 ? arguments[2] : 1; //Default to ascending

	if (objArray && objArray.constructor===Array){
		var propPath = (prop.constructor===Array) ? prop : prop.split(".");
		objArray.sort(function(a,b){
			for (var p in propPath){
				if (a[propPath[p]] && b[propPath[p]]){
					a = a[propPath[p]].toLowerCase();
					b = b[propPath[p]].toLowerCase();
					//console.log('a, b=', a + ', ' + b); // TC 2015-01-01
				}
			}
			// Convert numeric strings to integers
			a = a.match(/^\d+$/) ? +a : a;
			b = b.match(/^\d+$/) ? +b : b;
			return ( (a < b) ? -1*direct : ((a > b) ? 1*direct : 0) );
		});
	}
}

function populateDB(data, path, uplevel, keyword){
	if (path) {
		GUI.currentpath = path;
	}
	// TC (Tim Curtis) 2014-08-23: sort Webradio station list
	// TC (Tim Curtis) 2014-12-23: add test typeof(data[0]) != 'undefined', fixes bug causing 'undefined' error when search returns nothing
	if (typeof(data[0]) != 'undefined') {
		if (typeof(data[0].file) != 'undefined' && data[0].file.substring(0, 8) == "WEBRADIO") {
			sortJsonArrayByProperty(data, 'file')
		}
	}

	// TC (Tim Curtis) 2014-12-23: format search tally for dedicated div on right side
	// TC (Tim Curtis) 2014-12-23: clear results and search field when back btn
	// TC (Tim Curtis) 2015-01-01: clear radio station search field
	var DBlist = $('ul.database');
	DBlist.html('');

	if (keyword) {
		var results = (data.length) ? data.length : '0';
		var s = (data.length == 1) ? '' : 's';
		var text = results + ' item' + s;
		$("#db-back").show();
		$("#db-filter-results").html(text);
	} else if (path != '') {
		$("#db-back").show();
		$("#db-filter-results").html('');
		$("#db-search-keyword").val('');
		$("#rs-filter").val('');
	} else { // back to db root
		$("#db-back").hide();
		$("#db-filter-results").html('');
		$("#db-search-keyword").val('');
		$("#rs-filter").val('');
		// TC (Tim Curtis) 2015-01-01: unhide db search field
		$('#rs-search-input').addClass('hidden');
		$('#db-search-input').removeClass('hidden');
		$('#db-search').removeClass('db-form-hidden');
		// TC (Tim Curtis) 2015-01-27; close toolbars when back to db root
		$('.btnlist-top-db').addClass('hidden');
		$('.btnlist-bottom-db').addClass('hidden');
		//$('#playlist').css({"padding":"40px 0"}); // TC (Tim Curtis) 2015-04-29: no need since each panel has own toolbar
		$('#database').css({"padding":"40px 0"});
		$('#lib-content').css({"top":"40px"});
	}

	var content = '';
	var i = 0;
	for (i = 0; i < data.length; i++){
		content = parseResponse(data,'db',i,path);
		DBlist.append(content);
	}

	// TC (Tim Curtis) 2014-10-31: remove highlight
	$('#db-currentpath span').html(path);
	if (uplevel) {
		customScroll('db', GUI.currentDBpos[GUI.currentDBpos[10]]);
	} else {
		customScroll('db', 0, 0);
	}
}

// Update GUI
function updateGUI(json){
	//console.log('updateGUI() json=', json);

	mpdCurrentSong(); // TC (Tim Curtis) 2014-08-23: get mpd currentSong data
	TCMCONF.json = readTcmConf(); // TC (Tim Curtis) 2015-05-30: get saved currentSong title for Playback history log

	refreshState(GUI.state); // Update state of certain GUI elements

	// Check song update
	// TC (Tim Curtis) 2015-05-30: moved this code to after the code that makes the search url

	// Common actions
	// TC (Tim Curtis) 2014-11-30: add volume-2 updater line for 2nd voume control
	// TC (Tim Curtis) 2015-01-27: chg ? 100 to 0 (never programatically set vol = 100)

	// TC (Tim Curtis) 2015-06-26: Original code
	// vol = -1 is when MPD mixer_type = disabled
	//$('#volume').val((json['volume'] == '-1') ? 0 : json['volume']).trigger('change');
	//$('#volume-2').val((json['volume'] == '-1') ? 0 : json['volume']).trigger('change');

	// TC (Tim Curtis) 2015-06-26: for new setVolume() volume control
	// vol = -1 is when MPD mixer_type = disabled
	// Note: using the value from tcmods.conf means user ALSAMIXER changes will not be picked up by the UI
	$('#volume').val((json['volume'] == '-1') ? 0 : TCMCONF.json['volume_knob_setting']).trigger('change');
	$('#volume-2').val((json['volume'] == '-1') ? 0 : TCMCONF.json['volume_knob_setting']).trigger('change');

	// TC (Tim Curtis) 2015-06-26: new mute state management
	if (TCMCONF.json['volume_muted'] == 0) { // Unmuted
		$('#volumemute').removeClass('btn-primary');
		$('#volumemute-2').removeClass('btn-primary');
	} else {
		$('#volumemute').addClass('btn-primary'); // Muted
		$('#volumemute-2').addClass('btn-primary');
	}

	// TC (Tim Curtis) 2014-08-23: update gui with mpd currentSong data
	$('#currentartist').html(MPDCS.artist);
	$('#currentsong').html(MPDCS.title);
	$('#currentalbum').html(MPDCS.album);

	// TC (Tim Curtis) 2014-08-23: prevent unnecessary image reloads
	if (MPDCS.title != MPDCS.lasttitle) {
		// TC (Tim Curtis) 2014-08-23: add Search lookup unless title is a url (Webradio) or default-cover is diaplayed (playlist empty)
		if (MPDCS.title.substr(0, 4) == "http" || MPDCS.coverurl == MPDCS.defaultcover) {
			$('#coverart-url').html('<img class="coverart" ' + 'src="' + MPDCS.coverurl + '" ' + 'alt="Cover art not found"' + '>');
		} else {
			var searchStr = '';
			if (MPDCS.artist == 'Radio Station') {
				searchStr = MPDCS.title.replace(/-/g, " ");
				searchStr = searchStr.replace(/&/g, " ");
				searchStr = searchStr.replace(/\s+/g, "+");
			} else {
				searchStr = MPDCS.artist + "+" + MPDCS.album
			}
			// TC (Tim Curtis) 2014-12-23: change coverart-click search engine from Amazon to Google
			// var searchEngine = "http://www.amazon.com/s?url=search-alias%3Daps&field-keywords=";
			var searchEngine = "http://www.google.com/search?q=";
			$('#coverart-url').html('<a id="coverart-link" href=' + '"' + searchEngine + searchStr + '"' + ' target="_blank"> <img class="coverart" ' + 'src="' + MPDCS.coverurl + '" ' + 'alt="Cover art not found"' + '></a>');
		}

		MPDCS.lasttitle = MPDCS.title;
	}

	// Check song update
	//if (GUI.currentsong != json['currentsong']) {
	// TC (Tim Curtis) 2015-05-30: use this test instead since play_history_currentsong is persistent even if page is reloaded
	if (MPDCS.title != TCMCONF.json['play_history_currentsong']) {
		countdownRestart(0);
		if ($('#open-playback').hasClass('active')) { // TC (Tim Curtis) 2015-04-29: automatic scrollto when song changes
		//if ($('#open-panel-dx').hasClass('active')) {
			var current = parseInt(json['song']);
			customScroll('pl', current);
		}
	}

	if (json['repeat'] == 1) {
		$('#repeat').addClass('btn-primary');
	} else {
		$('#repeat').removeClass('btn-primary');
	}
	if (json['random'] == 1) {
		$('#random').addClass('btn-primary');
	} else {
		$('#random').removeClass('btn-primary');
	}
	if (json['consume'] == 1) {
		$('#consume').addClass('btn-primary');
	} else {
		$('#consume').removeClass('btn-primary');
	}
	if (json['single'] == 1) {
		$('#single').addClass('btn-primary');
	} else {
		$('#single').removeClass('btn-primary');
	}

	// TC (Tim Curtis) 2015-05-30: legacy code
	GUI.halt = 0;
	GUI.currentsong = json['currentsong'];
}

// Update status on playback and playlist panels
// TC (Tim Curtis) 2015-01-01: remove btn highlighting and implement play/pause toggle
// TC (Tim Curtis) 2015-01-27: fix item highlighting bug, add updCountDirInd() function
function refreshState (state) {
	if (state == 'play') {
		$("#play i").removeClass("icon-play").addClass("icon-pause"); // TC 2015-01-01
		updCountDirInd(timeConvert(GUI.json['time'])); // TC 2015-01-27
		$('.playlist li').removeClass('active');
		$('.playlist li:nth-child(' + (parseInt(GUI.json['song']) + 1) + ')').addClass('active');
	} else if (state == 'pause') {
		$("#play i").removeClass("icon-pause").addClass("icon-play"); // TC 2015-01-01
		updCountDirInd(timeConvert(GUI.json['time']));
		$('.playlist li').removeClass('active');
	} else if (state == 'stop') {
		$('#play i').removeClass('icon-pause').addClass('icon-play');
		$('#countdown-display').countdown('destroy');
		updCountDirInd('00:00');
		$('#time').val(0).trigger('change');
		$('.playlist li').removeClass('active');
	}

	// Show/clear UpdateDB icon
	if (typeof GUI.json['updating_db'] != 'undefined') {
		$('.open-panel-sx').html('<i class="icon-refresh icon-spin"></i> Updating');
	} else {
		$('.open-panel-sx').html('Browse');
	}
}

// Update count direction indicator, radio always counts up
// TC (Tim Curtis) 2015-01-27: initial version
// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
function updCountDirInd (time) {
	$('#total').html(time + ((TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) ? '<i class="icon-caret-up countdown-caret"></i>' : '<i class="icon-caret-down countdown-caret"></i>'));
}

// Update countdown
// TC (Tim Curtis) 2014-11-30: count up or down depending on conf setting, radio always couts up
// TC (Tim Curtis) 2015-01-27: combine play and pause in one if
// TC (Tim Curtis) 2015-01-27: add onTick and chg format 'MS' to 'hMS'
// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
function refreshTimer (startFrom, stopTo, state) {
	if (state == 'play' || state == 'pause') {
		$('#countdown-display').countdown('destroy');
		if (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) {
			$('#countdown-display').countdown({since: -(startFrom), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		} else {
			$('#countdown-display').countdown({until: startFrom, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		}
		if (state == 'pause') {
			$('#countdown-display').countdown('pause');
		}
	} else if (state == 'stop') {
		$('#countdown-display').countdown('destroy');
		if (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) {
			$('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		} else {
			$('#countdown-display').countdown({until: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
		}
		$('#countdown-display').countdown('pause');
	}
}

// Watch countdown timer (onTick callback)
// TC (Tim Curtis) 2015-01-27: initial version
// TC (Tim Curtis) 2015-04-29: use smaller fonts for new knob layout on playback panel w integrated playlist
function watchCountdown (period) {
	//console.log('period=', period);
	// If hours > 0 reduce font-size so time fits nicely within knob
	if (period[4] > 0) {
		if (period[4] > 9) { // 2 digits
			$('#countdown-display').css({"font-size":"24px"});  // orig value 28
		} else { // 1 digit
			$('#countdown-display').css({"font-size":"28px"}); // 32
		}
	} else {
		$('#countdown-display').css({"font-size":"36px"}); // 40
	}
}

// Update countdown time knob
// TC (Tim Curtis) 2015-01-27: when radio station is playing, stop painting the slider when its full
// TC (Tim Curtis) 2015-01-27: remove GUI.visibility test that sets different paint rates because its not really effective
function refreshKnob (json) {
	window.clearInterval(GUI.currentKnob)
	var initTime = json['song_percent'];
	var delta = json['time'] / 1000;

	$('#time').val(initTime * 10).trigger('change');
	if (GUI.state == 'play') {
		GUI.currentKnob = setInterval(function() {
			//console.log('refreshKnob() InitTime=', initTime);
			delta == 0 ? initTime = initTime + 0.5 : initTime = initTime + 0.1; // fast paint when radio station playing
			if (delta == 0 && initTime > 100) { // this stops the function() from needlessly running when radio (delta = 0) playing
				window.clearInterval(GUI.currentKnob)
				timeKnobPaintComplete = true;
				//console.log('refreshKnob() timeKnobPaintComplete=', timeKnobPaintComplete);
			}
			$('#time').val(initTime * 10).trigger('change');
		}, delta * 1000);
	}
}

// Update countdown timer knob (orig)
/*
function refreshKnob (json) {
	window.clearInterval(GUI.currentKnob)
	var initTime = json['song_percent'];
	var delta = json['time'] / 1000;

	$('#time').val(initTime * 10).trigger('change');
	if (GUI.state == 'play') {
		GUI.currentKnob = setInterval(function() {
			if (GUI.visibility == 'visible') {
				initTime = initTime + 0.1;
			} else {
				initTime = initTime + 100/json['time'];
			}
			$('#time').val(initTime*10).trigger('change');
		}, delta * 1000);
	}
}
*/

// Time conversion
// TC (Tim Curtis) 2015-01-01: add hours, use modulus to calc minutes and seconds
function timeConvert(seconds) {
	if(isNaN(seconds)) {
		display = '';
	} else {
		hours = Math.floor(seconds / 3600);
		minutes = Math.floor(seconds / 60);
		minutes = (minutes < 60) ? minutes : (minutes % 60);
		seconds = seconds % 60;

		hh = (hours > 0) ? (hours + ':') : '';
		mm = (minutes < 10) ? ('0' + minutes) : minutes;
		ss = (seconds < 10) ? ('0' + seconds) : seconds;

		display = hh + mm + ':' + ss;
	}
	return display;
}
// Format total track time for display
// TC (Tim Curtis) 2015-01-01: initial version
function totalTrackTime(seconds) {
	if(isNaN(seconds)) {
		display = '';
	} else {
		hours = Math.floor(seconds / 3600);
		minutes = Math.floor(seconds / 60);
		minutes = (minutes < 60) ? minutes : (minutes % 60);

		if (hours == 0) {
			hh = '';
		} else if (hours == 1) {
			hh = hours + ' hour';
		} else {
			hh = hours + ' hours';
		}

		if (minutes == 0) {
			mm = '';
		} else if (minutes == 1) {
			mm = minutes + ' minute';
		} else {
			mm = minutes + ' minutes';
		}

		// Format for display
		if (hours > 0) {
			if (minutes > 0) {
				display = hh + ', ' + mm;
			} else {
				display = hh;
			}
		} else {
			display = mm;
		}
	}

	return display;
}

// Reset countdown timer display
// TC (Tim Curtis) 2015-01-27: add onTick and chg format 'MS' to 'hMS'
function countdownRestart(startFrom) {
	$('#countdown-display').countdown('destroy');
	$('#countdown-display').countdown({since: (startFrom), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
}

// TC (Tim Curtis) 2015-06-26: new volume control with optional logarithmic mapping of knob 0-100 range to hardware range
function setVolume(level, event) {
	GUI.volume = level;
	GUI.halt = 1;

	// save knob setting
	TCMCONF.json = readTcmConf();

	if (TCMCONF.json['volume_muted'] == 0) { // UNMUTED, set volume incl 0
		TCMCONF.json['volume_knob_setting'] = level;
		//console.log('knob level=', level)

		if (TCMCONF.json['volume_mixer_type'] == "hardware" && TCMCONF.json['volume_curve_logarithmic'] == "Yes") {
			// HWvol = a x log10(volume) -2a + 100, where a = 56
			var maxLevel = TCMCONF.json['volume_max_percent'] * .01; // default is 100, for capping max volume level
			var curveFactor = TCMCONF.json['volume_curve_factor']; // adjusts curve to be more or less aggressive
			if (level > 1) {
				level = Math.floor((curveFactor * (Math.log(level) / Math.LN10)) - (2 * curveFactor) + 100); // round down
				level = Math.round(level * maxLevel); // round up
			}
		}
		//console.log('output level=', level)
		$('#output-level').text("output level= " + level);

		// negative values occure when curveFactor > 56
		if (level < 0) {level = 0;}

		sendMpdCmd('setvol ' + level);
	} else {
		if (level == 0 && event == "mute")	{
			sendMpdCmd('setvol ' + 0);
		} else {
			TCMCONF.json['volume_knob_setting'] = level; // MUTED, just store the volume for display
		}
	}

	var rtnString = updateTcmConf();
}

// Custom scrolling
// TC (Tim Curtis) 2015-01-01: compensate for variable item (pl-entry) height due to line wrapping by using (height of playlist / #items)
// TC (Tim Curtis) 2015-04-29: mods to handle playlist embedded within playback panel
function customScroll(list, itemnum, speed) {
	if (typeof(speed) === 'undefined') {
		speed = 500;
	}

	if (list == 'db') {
		var centerheight = parseInt($(window).height()/2);
		var scrolltop = $(window).scrollTop();
		var itemheight = parseInt(1 + $('#' + list + '-1').height());
		var scrollcalc = parseInt((itemnum) * itemheight - centerheight);
		var scrolloffset = scrollcalc;
		if (scrollcalc > 0) {
			$.scrollTo( scrolloffset , speed );
		} else {
			$.scrollTo( 0 , speed );
		}
	//$('#' + list + '-' + (itemnum + 1)).addClass('active');
	} else if (list == 'pl') {
		var centerheight = parseInt($('#container-playlist').height()/2);
		var scrolltop = $('#container-playlist').scrollTop();
		//var itemheight = parseInt(1 + $('#' + list + '-1').height());
		var itemheight = parseInt($('#playlist ul').height() / GUI.json['playlistlength']); // avg item height compensates for line wrap

		// TC (Tim Curtis) 2015-04-29: note that item height depends on font size for .playlist li/span
		// - font size: 18/14 = 53
		// - font size: 14/11 = 51
		if (itemheight == 51) { // nominal item height (no line wrapping on any item)
			var adjfactor = 1; // TC (Tim Curtis) 2015-04-29: original 1
		} else {
			var adjfactor = 3; // TC (Tim Curtis) 2015-04-29: original 3
		}

		var scrollcalc = parseInt((itemnum + adjfactor) * itemheight - centerheight);
		if (scrollcalc > scrolltop) {
			var scrolloffset = '+=' + Math.abs(scrollcalc - scrolltop) + 'px';
		} else {
			var scrolloffset = '-=' + Math.abs(scrollcalc - scrolltop) + 'px';
		}
		if (scrollcalc > 0) {
			$('#container-playlist').scrollTo( scrolloffset , speed );
		} else {
			$('#container-playlist').scrollTo( 0 , speed );
		}
	}

	// debug
	/*
	console.log('-------------------------------------------');
	console.log('customScroll parameters= ' + list + ', ' + itemnum + ', ' + speed);
	console.log('scrolltop= ', scrolltop);
	console.log('itemheight= ', itemheight);
	console.log('adjfactor= ', adjfactor);
	console.log('listheight= ', $('#playlist ul').height());
	console.log('numitems= ', GUI.json['playlistlength']);
	console.log('centerheight= ', centerheight);
	console.log('scrollcalc= ', scrollcalc);
	console.log('scrolloffset= ', scrolloffset);
	*/
}

// Random scrolling pl
// TC (Tim Curtis) 2015-01-27: only used by scripts-playback.js #pl-debug-btn
function randomScrollPL() {
	var n = $(".playlist li").size();
	var random = 1 + Math.floor(Math.random() * n);
	customScroll('pl', random);
}
// Random scrolling pl
// TC (Tim Curtis) 2015-01-27: cant find where this is called from...
function randomScrollDB() {
	var n = $(".database li").size();
	var random = 1 + Math.floor(Math.random() * n);
	customScroll('db', random);
}

// Change Library button icon to provide feedback when code is long running
// TC (Tim Curtis) 2014-09-17: initial version
// TC (Tim Curtis) 2014-11-30: no icon when done
// TC (Tim Curtis) 2014-11-30: change icon-spin sx to icon-spin
function libbtnIcon(type) {
	if (type == "working") {
		$('.open-panel-lib').html('<i class="icon-refresh icon-spin"></i> Library'); // spinning refresh icon
	} else if (type == "done") {
		$('.open-panel-lib').html('Library'); // default
		//$('.open-panel-lib').html('<i class="icon-music sx"></i> Library'); // default
	} else {
		$('.open-panel-lib').html('Library'); // place holder
		//$('.open-panel-lib').html('<i class="icon-music sx"></i> Library'); // place holder
	}
}

// Load library into GUI
function loadLibrary(data) {
	fullLib = data;
	// TC (Tim Curtis) 2014-09-17: debug
	//console.log("fullLib = ", data);

	// Generate library array
	filterLib();

	// TC (Tim Curtis) 2015-04-29: save off total num songs
	totalSongs = allSongs.length;

	// Insert data in DOM
	renderGenres();

	// TC (Tim Curtis) 2014-12-23: debug
	//console.log('player_lib.js: library load complete');
	//$('#open-panel-sx').hasClass('active') ? console.log('player_lib.js: Browse active= true') : console.log('player_lib.js: Browse active= false');
	//$('#open-panel-lib').hasClass('active') ? console.log('player_lib.js: Library active= true') : console.log('player_lib.js: Library active= false');
	//$('#open-playback').hasClass('active') ? console.log('player_lib.js: Playback active= true') : console.log('player_lib.js: Playback active= false');
	//$('#open-panel-dx').hasClass('active') ? console.log('player_lib.js: Playlist active= true') : console.log('player_lib.js: Playlist active= false');
}

// Generate library array
// TC (Tim Curtis) 2014-12-23: use allAlbumsTmp to improve efficiency
// TC (Tim Curtis) 2014-12-23: add allFiles to reduce amount of data to be passed to server in Library playlist add functions
function filterLib() {
console.log("filterLib");
console.log(filters);
	allGenres = [];
	allArtists = [];
	allAlbums = [];
	allAlbumsTmp = [];
	allSongs = [];
	allFiles = [];

	var needReload = false;

	for (var genre in fullLib) {
		if (allGenres.indexOf(genre) < 0) {
			allGenres.push(genre);
		}

		if (filters.genres.length == 0 || filters.genres.indexOf(genre) >= 0) {
			for (var artist in fullLib[genre]) {
				if (allArtists.indexOf(artist) < 0) {
					allArtists.push(artist);
				}

				if (filters.artists.length == 0 || filters.artists.indexOf(artist) >= 0) {
					for (var album in fullLib[genre][artist]) {
						var objAlbum = {"album": album, "artist": artist};
						allAlbumsTmp.push(objAlbum);

						// TC (Tim Curtis) 2014-09-17: console.log('filteLib 0:  objAlbum = ', objAlbum);
						if (filters.albums.length == 0 || filters.albums.indexOf(keyAlbum(objAlbum)) >= 0) {
							// TC (Tim Curtis) 2014-09-17: console.log('filterLib 1: filters.albums.length = ', filters.albums.length)
							// TC (Tim Curtis) 2014-09-17: console.log('filterLib 1: filters.albums.indexOf = ', filters.albums.indexOf(keyAlbum(objAlbum)))
							for (var i in fullLib[genre][artist][album]) {
								var song = fullLib[genre][artist][album][i];
								song.album = album;
								song.artist = artist;
								allSongs.push(song);
								allFiles.push({'file': song.file});
							}
						}
					}
				}
			}
		}
	}

	// Check filters validity
	var newFilters = checkFilters(filters.albums, allAlbumsTmp, function(o) { return keyAlbum(o); });
	if (newFilters.length != filters.albums.length) {
		needReload = true;
		filters.albums = newFilters;
	}
	newFilters = checkFilters(filters.artists, allArtists, function(o) { return o; });
	if (newFilters.length != filters.artists.length) {
		needReload = true;
		filters.artists = newFilters;
	}

	// TC (Tim Curtis) 2014-09-17: console.log('after filter validity check = ', filters.albums);
	// TC (Tim Curtis) 2014-09-17: console.log('need reload = ', needReload);

	if (needReload) {
console.log("needReload=true");
		filterLib();
	}
	else {
		// Sort lists
		allGenres.sort();
		allArtists.sort();
		allAlbumsTmp.sort(function(a, b) { return a.album.toLowerCase() > b.album.toLowerCase() ? 1 : -1; });

		// TC (Tim Curtis) 2014-09-17: rollup and tag all compilation albums, create a new allAlbums array
		// TC (Tim Curtis) 2014-10-31: adj allAlbums.length > 2 and var i = 2 by -1 since we removed embedded "ALL..." entries
		// TC (Tim Curtis) 2014-11-30: change yes/no to 1/0 for compilation album tag
		// TC (Tim Curtis) 2014-12-23: use allAlbumsTmp to improve efficiency
		var compAlbumStored = false;
		var objCompilationAlbum = {"album": '', "artist": '', "compilation": '1'}; // note: the "compilation" tag is used in the onClick for Albums

		if (allAlbumsTmp.length > 1) {
			for (var i = 1; i < allAlbumsTmp.length; i++) { // Start at 1 since first album starts at 0 now
				if (allAlbumsTmp[i].album == allAlbumsTmp[i - 1].album && allAlbumsTmp[i].album.toLowerCase() != "greatest hits") { // Current = previous -> compilation album
					if (compAlbumStored == false) {
						objCompilationAlbum = {"album": allAlbumsTmp[i].album, "artist": "Various Artists", "compilation": '1'}; // Store compilation album only once (rollup)
						allAlbums.push(objCompilationAlbum);
						compAlbumStored = true;
					}
				}
				else { // Current != previous -> lets check
					if (allAlbumsTmp[i - 1].album == objCompilationAlbum.album) { // Previous = last compilation album stored
						objCompilationAlbum = {"album": '', "artist": '', "compilation": '1'}; // Don't store it, just reset and move on
					}
					else {
						var objRegularAlbum = {"album": allAlbumsTmp[i - 1].album, "artist": allAlbumsTmp[i - 1].artist, "compilation": '0'}; // Previous is a regular album, store it
						allAlbums.push(objRegularAlbum);
					}

					if (i == allAlbumsTmp.length - 1) { // Last album
						var objRegularAlbum = {"album": allAlbumsTmp[i].album, "artist": allAlbumsTmp[i].artist, "compilation": '0'}; // Store last album
						allAlbums.push(objRegularAlbum);
					}

					compAlbumStored = false; // Reset flag
				}
			}

		// TC (Tim Curtis) 2014-12-23: replace else with elseif for case length = 1
		}
		else if (allAlbumsTmp.length == 1) { // Only one album in list
			// TC (Tim Curtis) 2014-10-31: chg allAlbums[1].album to [0] since we removed embedded "ALL..." entries
			var objRegularAlbum = {"album": allAlbumsTmp[0].album, "artist": allAlbumsTmp[0].artist, "compilation": '0'}; // Store the one and only album
			allAlbums.push(objRegularAlbum);
		} else { // Array length is 0 (empty) -> no music source defined
			//console.log('player_lib.js: allAlbumsTmp.length=', allAlbumsTmp.length);
		}
	}
}

// Check for invalid filters
function checkFilters(filters, collection, func) {
console.log("checkFilters");
	var newFilters = [];
	for (var filter in filters) {
console.log(filters[filter]);
		for (var obj in collection) {
			if (filters[filter] == func(collection[obj])) {
				newFilters.push(filters[filter]);
				break;
			}
		}
	}
	return newFilters;
}

// Generate album/artist key
function keyAlbum(objAlbum) {
	return objAlbum.album + "@" + objAlbum.artist;
}

// Render genres
var renderGenres = function() {
	var output = '';
	for (var i = 0; i < allGenres.length; i++) {
		output += '<li class="clearfix"><div class="lib-entry'
			+ (filters.genres.indexOf(allGenres[i]) >= 0 ? ' active' : '')
			+ '">' + allGenres[i] + '</div></li>';
	}
	$('#genresList').html(output);
	renderArtists();
}

// Render artists
// TC (Tim Curtis) 2015-01-01: add test || allArtists.length = 1 to automatically highlight if only 1 artist in list
var renderArtists = function() {
	var output = '';
	for (var i = 0; i < allArtists.length; i++) {
		output += '<li class="clearfix"><div class="lib-entry'
			+ ((filters.artists.indexOf(allArtists[i]) >= 0 || allArtists.length == 1) ? ' active' : '')
			+ '">' + allArtists[i] + '</div></li>';
	}
	$('#artistsList').html(output);
	renderAlbums();
}

// Render albums
// TC (Tim Curtis) 2015-01-01: add test || allAlbums.length = 1 to automatically highlight if only 1 album in list
var renderAlbums = function() {
	// TC (Tim Curtis) 2015-01-27: clear search filter and results
	$("#lib-album-filter").val('');
	$('#lib-album-filter-results').html('');

	var output = '';
	var tmp = '';
	for (var i = 0; i < allAlbums.length; i++) {
		// TC (Tim Curtis) 2015-04-29: for renderSongs() so it can decide whether to display tracks
		if (filters.albums.indexOf(keyAlbum(allAlbums[i])) >= 0 || allAlbums.length == 1) {
			tmp = ' active';
			albumClicked = true;
		} else {
			tmp = '';
		}
		output += '<li class="clearfix"><div class="lib-entry'
			+ tmp
			//+ ((filters.albums.indexOf(keyAlbum(allAlbums[i])) >= 0 || allAlbums.length == 1) ? ' active' : '')
			// TC (Tim Curtis) 2014-12-23: remove i > 0 condition to fix missing artist in first album li in Library panel
			// TC (Tim Curtis) 2014-12-23: + '">' + allAlbums[i].album + (i > 0 ? ' <span> ' + allAlbums[i].artist + '</span>' : '') + '</div></li>';
			+ '">' + allAlbums[i].album + ' <span> ' + allAlbums[i].artist + '</span></div></li>';
	}
	$('#albumsList').html(output);
	renderSongs();
}

// Render songs
// TC (Tim Curtis) 2014-09-17: use new lib-entry-song class
// TC (Tim Curtis) 2014-09-17: add <br> to posn artist, album under song title for dbl high songlist rows
// TC (Tim Curtis) 2014-10-31: use Action menu
// TC (Tim Curtis) 2015-01-01: add songtime and totalTime calculation and display
var renderSongs = function() {
	var output = '';
	totalTime = 0;

	// TC (Tim Curtis) 2015-04-29: option #1, only display tracks if less than the whole library
	// TC (Tim Curtis) 2015-04-29: option #2, only display tracks if album selected
	//if (allSongs.length < totalSongs) { // option #1
	if (albumClicked == true) { // option #2
		albumClicked = false;
		for (var i = 0; i < allSongs.length; i++) {
			output += '<li id="lib-song-' + (i + 1) + '" class="clearfix"><div class="lib-entry-song">' + allSongs[i].display
				+ '<span class="songtime"> ' + allSongs[i].time2 + '</span>'
				+ '<br><span> ' + allSongs[i].artist + ', '  + allSongs[i].album + '</span></div>'

				// TC (Tim Curtis) 2014-10-31: action menu
				// TC (Tim Curtis) 2014-10-31: move div lib-action from inside of div lib-entry-song to same level as div lib-entry-song
				+ '<div class="lib-action"><a class="btn" href="#notarget" title="Actions" data-toggle="context" data-target="#context-menu-lib-item"><i class="icon-reorder"></i></a></div>'
				+ '</li>';
				totalTime = totalTime + parseInt(allSongs[i].time);
		}
	} else {
		for (var i = 0; i < allSongs.length; i++) {
			totalTime = totalTime + parseInt(allSongs[i].time);
		}
	}
	$('#songsList').html(output);

	// TC (Tim Curtis) 2014-10-31: display Library panel cover art and metadata
	// TC (Tim Curtis) 2015-01-01: add total track time, genre and artist to lib metadata area
	if (allAlbums.length == 1 || filters.albums != '') {
		$('#lib-albumname').html(allSongs[0].album);
		$('#lib-artistname').html(allSongs[0].artist);
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + totalTrackTime(totalTime));
		$('#lib-coverart-img').html('<img class="lib-coverart" ' + 'src="' + makeCoverURL(allSongs[0].file) + '" ' + 'alt="Cover art not found"' + '>');
	} else {
		if (filters.genres == '') {
			if (filters.artists == '') {
				var album = 'Music Library';
				var artist = '';
			} else {
				var album = filters.artists;
				var artist = '';
			}
		} else {
			var album = filters.genres;
			var artist = filters.artists;
		}
		$('#lib-albumname').html(album);
		$('#lib-artistname').html(artist);
		$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + totalTrackTime(totalTime));
		//$('#lib-numtracks').html(allSongs.length + ' tracks, ' + totalTrackTime(totalTime));
		$('#lib-coverart-img').html('<img class="lib-coverart" ' + 'src="' + MPDCS.defaultcover + '">');
	}
}

// Default post-click handler for lib items
function clickedLibItem(event, item, currentFilter, renderFunc) {
	// TC (Tim Curtis) 2014-09-17: change to animated icon
	// TC (Tim Curtis) 2014-09-17: force a new call stack for potentially long running code, allowing repaint to complete before the new call stack begins execution
	// TC (Tim Curtis) 2014-09-17: cred to Brad Daily at Stack Overflow for the window.setTimeout approach
	// TC (Tim Curtis) 2014-09-17: http://stackoverflow.com/questions/4005096/force-immediate-dom-update-modified-with-jquery-in-long-running-function
	libbtnIcon("working");
	window.setTimeout(function() {

		// Beg original code
		if (item == undefined) {
			// All
			currentFilter.length = 0;
		} else if (event.ctrlKey) {
			currentIndex = currentFilter.indexOf(item);
			if (currentIndex >= 0) {
				currentFilter.splice(currentIndex, 1);
			} else {
				currentFilter.push(item);
			}
		} else {
			currentFilter.length = 0;
			currentFilter.push(item);
		}

		// Updated filters
		filterLib();
		// Render
		renderFunc();
		// End original code

		// Back to default icon
		libbtnIcon("done");
	}, 0);
}

// CLICK HANDLER SECTION

// Click on ALL GENRES header
// TC (Tim Curtis) 2014-10-31: initial version
$('#genreheader').on('click', '.lib-heading', function(e) {
	clickedLibItem(e, undefined, filters.genres, renderGenres);
});

// Click on ALL ARTISTS header
// TC (Tim Curtis) 2014-10-31: initial version
$('#artistheader').on('click', '.lib-heading', function(e) {
	clickedLibItem(e, undefined, filters.artists, renderArtists);
});

// Click on ALL ALBUMS header
// TC (Tim Curtis) 2014-10-31: initial version
$('#albumheader').on('click', '.lib-heading', function(e) {
	clickedLibItem(e, undefined, filters.albums, renderAlbums);
});

// Click on Genre
$('#genresList').on('click', '.lib-entry', function(e) {
	var pos = $('#genresList .lib-entry').index(this);
	clickedLibItem(e, allGenres[pos], filters.genres, renderGenres);
});

// Click on Artist
$('#artistsList').on('click', '.lib-entry', function(e) {
	var pos = $('#artistsList .lib-entry').index(this);
	clickedLibItem(e, allArtists[pos], filters.artists, renderArtists);
});

// Click on Album
$('#albumsList').on('click', '.lib-entry', function(e) {
	var pos = $('#albumsList .lib-entry').index(this);

	// TC (Tim Curtis) 2015-04-29: for renderSongs() so it can decide whether to display tracks
	albumClicked = true;

	// TC (Tim Curtis) 2015-01-27: different way to handle highlight, avoids regenerating the albums list
	$('#albumsList li').removeClass('active');
	$(this).parent().addClass('active');

	// TC (Tim Curtis) 2014-09-17: handle compilation album
	// TC (Tim Curtis) 2014-11-30: change yes/no to 1/0 for compilation album tag
	if (allAlbums[pos].compilation == "1") {
		// Generate song list for compilation album
		allCompilationSongs = [];
		renderFunc = renderSongs;
		filters.albums = [];

		filterLib();

		// TC (Tim Curtis) 2014-12-23: reset array to empty
		// TC (Tim Curtis) 2015-01-01: add totalTime calculation
		allFiles.length = 0;
		totalTime = 0;

		for (var i = 0; i < allSongs.length; i++) {
			if (allSongs[i].album == allAlbums[pos].album) {
				allCompilationSongs.push(allSongs[i]);
				// TC (Tim Curtis) 2014-12-23: store just the file path
				allFiles.push({'file': allSongs[i].file});
				totalTime = totalTime + allSongs[i].time;
			}
		}

		// Sort by song filepath (obj.file) and then render the songs
		allSongs = allCompilationSongs.sort(function(a, b) {return a.file.toLowerCase() > b.file.toLowerCase() ? 1 : -1;});
		renderFunc();

		// TC (Tim Curtis) 2014-10-31: display Library panel cover art and metadata
		// TC (Tim Curtis) 2015-01-01: add total track time
		if (pos > 0) {
			$('#lib-albumname').html(allAlbums[pos].album);
			$('#lib-artistname').html(allAlbums[pos].artist);
			$('#lib-numtracks').html(allSongs.length + ((allSongs.length == 1) ? ' track, ' : ' tracks, ') + totalTrackTime(totalTime));
			$('#lib-coverart-img').html('<img class="lib-coverart" ' + 'src="' + makeCoverURL(allSongs[0].file) + '" ' + 'alt="Cover art not found"' + '>');
		}
	} else {
		// Generate song list for regular album
		// TC (2015-01-27: change from renderAlbums to renderSongs since new highlighting method
		clickedLibItem(e, keyAlbum(allAlbums[pos]), filters.albums, renderSongs);
	}
});

// Library action menu btn click handler
// TC (Tim Curtis) 2014-10-31: save position
// TC (Tim Curtis) 2014-10-31: highlight row when clicked
$('#songsList').on('click', '.lib-action', function() {
	GUI.DBentry[0] = $('#songsList .lib-action').index(this); // Store position for use in action menu item click
	$('#songsList li').removeClass('active');
	$(this).parent().addClass('active');

	// TC (Tim Curtis) 2015-01-27: adjust menu position so its always visible
	var posTop = "-130px"; // new btn pos
	var relOfs = 310; // btn offset relative to window (minus lib meta area)
	if ($(window).height() - ($(this).offset().top - $(window).scrollTop()) <= relOfs) {
		$('#context-menus .dropdown-menu').css({"top":posTop}); // 4 menu items
	} else {
		$('#context-menus .dropdown-menu').css({"top":"0px"});
	}
});

// Library action menu item click handler
// TC (Tim Curtis) 2014-12-23: send '' instead of allSongs[GUI.DBentry[0]].display in notify()
// TC (Tim Curtis) 2015-01-27: addall
// TC (Tim Curtis) 2015-07-31: playall, addallreplaceplay
$('.context-menu-lib a').click(function(e) {
	if ($(this).data('cmd') == 'add') {
		getDB('add', allSongs[GUI.DBentry[0]].file);
		M.notify('add');
	}
	if ($(this).data('cmd') == 'addplay') {
		getDB('addplay', allSongs[GUI.DBentry[0]].file);
		M.notify('add');
	}
	if ($(this).data('cmd') == 'addreplaceplay') {
		getDB('addreplaceplay', allSongs[GUI.DBentry[0]].file);
		M.notify('addreplaceplay');
		$("#pl-saveName").val(""); // Clear saved playlist name if any
	}
	if ($(this).data('cmd') == 'addall') {
		getDB('addall', allFiles);
		M.notify('addall');
	}
	if ($(this).data('cmd') == 'playall') {
		getDB('playall', allFiles);
		M.notify('addall');
	}
	if ($(this).data('cmd') == 'addallreplaceplay') {
		getDB('addallreplaceplay', allFiles);
		M.notify('addallreplaceplay');
	}

	// Remove highlight
	$('#lib-song-' + (GUI.DBentry[0] + 1).toString()).removeClass('active');
});

// Remove highlight when clicking off-row
// TC (tim Curtis) 2014-10-31: initial version
$('#songsList').on('click', '.lib-entry-song', function() {
	$('#songsList li').removeClass('active');
});


// MAIN MENU CLICK HANDLERS
// TC (Tim Curtis) 2014-12-23: send '' instead of path in notify()
// TC (Tim Curtis) 2015-03-21: moved code from scripts-playback.js so all popups launch from main menu when on Config screens
$('.context-menu a').click(function(){
	var path = GUI.DBentry[0]; // File path or item num

	if ($(this).data('cmd') == 'setforclockradio' || $(this).data('cmd') == 'setforclockradio-m') {
		// Read in conf file data
		TCMCONF.json = readTcmConf();

		// Parse file data for display on modal form
		$('#clockradio-enabled span').text(TCMCONF.json['clock_radio_enabled']);

		if ($(this).data('cmd') == 'setforclockradio-m') {
			$('#clockradio-playname').val(TCMCONF.json['clock_radio_playname']); // Called from system menu
			GUI.DBentry[0] = '-1'; // For "Update Clock Radio" btn press so it knows what to do
		} else {
			$('#clockradio-playname').val(GUI.DBentry[3]); // Called from action menu
		}

		$('#clockradio-starttime-hh').val(TCMCONF.json['clock_radio_starttime'].substr(0, 2));
		$('#clockradio-starttime-mm').val(TCMCONF.json['clock_radio_starttime'].substr(2, 2));
		$('#clockradio-starttime-ampm span').text(TCMCONF.json['clock_radio_starttime'].substr(5, 2));

		$('#clockradio-stoptime-hh').val(TCMCONF.json['clock_radio_stoptime'].substr(0, 2));
		$('#clockradio-stoptime-mm').val(TCMCONF.json['clock_radio_stoptime'].substr(2, 2));
		$('#clockradio-stoptime-ampm span').text(TCMCONF.json['clock_radio_stoptime'].substr(5, 2));

		// TC (Tim Curtis) 2015-01-27: set max to volume warning limit
		// TC (Tim Curtis) 2015-01-27: set volume warning limit text
		$('#clockradio-volume').attr('max', TCMCONF.json['volume_warning_limit']);
		if (parseInt(TCMCONF.json['volume_warning_limit']) < 100) {
			$('#clockradio-volume-aftertext').html('warning limit ' + TCMCONF.json['volume_warning_limit']);
		} else {
			$('#clockradio-volume-aftertext').html('');
		}
		$('#clockradio-volume').val(TCMCONF.json['clock_radio_volume']);

		$('#clockradio-shutdown span').text(TCMCONF.json['clock_radio_shutdown']);

		// Launch form
		$('#clockradio-modal').modal();
	}

	// MAIN MENU, TCMODS CLICK HANDLER
	// TC (Tim Curtis) 2015-01-27: tcmods config editor
	// TC (Tim Curtis) 2015-01-27: set volume warning limit text
	// TC (Tim Curtis) 2015-01-27: add search-autofocus-enabled
	// TC (Tim Curtis) 2015-04-29: add theme color
	// TC (Tim Curtis) 2015-05-30: add play-history-enabled
	// TC (Tim Curtis) 2015-06-26: add volume_ elements for logarithmic volume control
	// TC (Tim Curtis) 2015-06-26: add albumart-lookup-method
	if ($(this).data('cmd') == 'tcmodsconfedit') {
		 // Read in conf file data
		TCMCONF.json = readTcmConf();
		// Parse file data for display on modal form
		// General settings
		$('#volume-warning-limit').val(TCMCONF.json['volume_warning_limit']);
		if (parseInt(TCMCONF.json['volume_warning_limit']) == 100) {
			$('#volume-warning-limit-aftertext').html('warning disabled');
		} else {
			$('#volume-warning-limit-aftertext').html('');
		}
		$('#search-autofocus-enabled span').text(TCMCONF.json['search_autofocus_enabled']);
		$('#theme-color span').text(TCMCONF.json['theme_color']); // TC (Tim Curtis) 2015-04-29: theme color
		$('#play-history-enabled span').text(TCMCONF.json['play_history_enabled']);
		$('#albumart-lookup-method span').text(TCMCONF.json['albumart_lookup_method']);

		// Hardware volume control
		$('#logarithmic-curve-enabled span').text(TCMCONF.json['volume_curve_logarithmic']);
		//$('#volume-curve-factor').val(TCMCONF.json['volume_curve_factor']);
		if (TCMCONF.json['volume_curve_factor'] == 56) {$('#volume-curve-factor span').text("Standard");}
		else if (TCMCONF.json['volume_curve_factor'] == 66) {$('#volume-curve-factor span').text("Less (-10)");}
		else if (TCMCONF.json['volume_curve_factor'] == 76) {$('#volume-curve-factor span').text("Less (-20)");}
		else if (TCMCONF.json['volume_curve_factor'] == 86) {$('#volume-curve-factor span').text("Less (-30)");}
		else if (TCMCONF.json['volume_curve_factor'] == 50) {$('#volume-curve-factor span').text("More (+06)");}
		else if (TCMCONF.json['volume_curve_factor'] == 44) {$('#volume-curve-factor span').text("More (+12)");}
		else if (TCMCONF.json['volume_curve_factor'] == 38) {$('#volume-curve-factor span').text("More (+18)");}
		$('#volume-max-percent').val(TCMCONF.json['volume_max_percent']);

		// Audio device description
		// TC (Tim Curtis) 2015-03-21: change to span and text() for audio device dropdown
		$('#audio-device-name span').text(TCMCONF.json['audio_device_name']);
		$('#audio-device-dac').val(TCMCONF.json['audio_device_dac']);
		$('#audio-device-arch').val(TCMCONF.json['audio_device_arch']);
		$('#audio-device-iface').val(TCMCONF.json['audio_device_iface']);
		$('#audio-device-other').val(TCMCONF.json['audio_device_other']);

		// Launch form
		$('#tcmodsconf-modal').modal();
	}
	// MAIN MENU, ABOUT CLICK HANDLER
	// TC (Tim Curtis) 2015-02-25: initial version
	if ($(this).data('cmd') == 'abouttcmods') {
		 // Read in conf file data
		TCMCONF.json = readTcmConf();
		// Parse file data for display on modal form
		$('#sys-kernel-ver').text(TCMCONF.json['sys_kernel_ver']);
		$('#sys-processor-arch').text(TCMCONF.json['sys_processor_arch'].replace("arm", "ARM")); // Uppercase arm for display
		$('#sys-mpd-ver').text(TCMCONF.json['sys_mpd_ver']);

		// Launch form
		$('#about-modal').modal();
	}

	// MAIN MENU, PLAY HISTORY CLICK HANDLER
	// TC (Tim Curtis) 2015-05-30: initial version
	if ($(this).data('cmd') == 'viewplayhistory') {
		// Read in playback history log
		var tmpObj = readPlayHistory();

		// Load history items
		var content = '';
		for (i = 1; i < tmpObj.length; i++) {
			content += tmpObj[i];
		}
		// Output history items
		$('ol.playhistory').html(content);

		// Launch form
		$('#playhistory-modal').modal();
	}

});

// BUTTON CLICK HANDLERS

// Update tcmods.conf settings from clock radio popup
$('.btn-clockradio-update').click(function(){
	// Re-read to get most current data before submitting update
	TCMCONF.json = readTcmConf();

	// Update values
	TCMCONF.json['clock_radio_enabled'] = $('#clockradio-enabled span').text();

	// Update header and menu icon color
	if (TCMCONF.json['clock_radio_enabled'] == "Yes") {
		$('#clockradio-icon').removeClass("clockradio-off")
		$('#clockradio-icon').addClass("clockradio-on")
		$('#clockradio-icon-m').removeClass("clockradio-off-m")
		$('#clockradio-icon-m').addClass("clockradio-on-m")
	} else {
		$('#clockradio-icon').removeClass("clockradio-on")
		$('#clockradio-icon').addClass("clockradio-off")
		$('#clockradio-icon-m').removeClass("clockradio-on-m")
		$('#clockradio-icon-m').addClass("clockradio-off-m")
	}

	// Note GUI.DBentry[0] set to '-1' if modal launched from system menu
	if (GUI.DBentry[0] != '-1') {TCMCONF.json['clock_radio_playitem'] = GUI.DBentry[0];}
	TCMCONF.json['clock_radio_playname'] = $('#clockradio-playname').val();

	var startHH, startMM, stopHH, stopMM;

	$('#clockradio-starttime-hh').val().length == 1 ? startHH = '0' + $('#clockradio-starttime-hh').val() : startHH = $('#clockradio-starttime-hh').val();
	$('#clockradio-starttime-mm').val().length == 1 ? startMM = '0' + $('#clockradio-starttime-mm').val() : startMM = $('#clockradio-starttime-mm').val();
	$('#clockradio-stoptime-hh').val().length == 1 ? stopHH = '0' + $('#clockradio-stoptime-hh').val() : stopHH = $('#clockradio-stoptime-hh').val();
	$('#clockradio-stoptime-mm').val().length == 1 ? stopMM = '0' + $('#clockradio-stoptime-mm').val() : stopMM = $('#clockradio-stoptime-mm').val();

	TCMCONF.json['clock_radio_starttime'] = startHH + startMM + " " + $('#clockradio-starttime-ampm span').text();
	TCMCONF.json['clock_radio_stoptime'] = stopHH + stopMM + " " + $('#clockradio-stoptime-ampm span').text();

	TCMCONF.json['clock_radio_volume'] = $('#clockradio-volume').val();
	TCMCONF.json['clock_radio_shutdown'] = $('#clockradio-shutdown span').text();

	// Update tcmods.conf file
	var rtnString = updateTcmConf();

	// Reload server-side data
	$.post('tcmods.php', {syscmd:'reloadclockradio'});
	M.notify('updateclockradio');
});

// TC (Tim Curtis) 2015-01-27: update tcmods.conf settings from tcmods editor popup
// TC (Tim Curtis) 2015-05-30: add play-history-enabled
// TC (Tim Curtis) 2015-06-26: add volume_ elements for logarithmic volume control
// TC (Tim Curtis) 2015-06-26: add albumart-lookup-method
$('.btn-tcmodsconf-update').click(function(){
	// Re-read to get most current data before submitting update
	TCMCONF.json = readTcmConf();

	// Update values
	// General settings
	TCMCONF.json['volume_warning_limit'] = $('#volume-warning-limit').val();
	// TC (Tim Curtis) 2015-01-27: update other volume settings
	if (parseInt(TCMCONF.json['clock_radio_volume']) > parseInt($('#volume-warning-limit').val())) {
		TCMCONF.json['clock_radio_volume'] = $('#volume-warning-limit').val();
	}
	TCMCONF.json['search_autofocus_enabled'] = $('#search-autofocus-enabled span').text();
	//TC (Tim Curtis): 2015-04-29: theme color
	if (TCMCONF.json['theme_color'] != $('#theme-color span').text()) { // user change
		var themeChange = true;
	} else {
		var themeChange = false;
	}
	TCMCONF.json['theme_color'] = $('#theme-color span').text();
	TCMCONF.json['play_history_enabled'] = $('#play-history-enabled span').text();
	TCMCONF.json['albumart_lookup_method'] = $('#albumart-lookup-method span').text();

	// Hardware volume control
	TCMCONF.json['volume_curve_logarithmic'] = $('#logarithmic-curve-enabled span').text();
	//TCMCONF.json['volume_curve_factor'] = $('#volume-curve-factor').val();
	if ($('#volume-curve-factor span').text() == "Standard") {TCMCONF.json['volume_curve_factor'] = 56;}
	else if ($('#volume-curve-factor span').text() == "Less (-10)") {TCMCONF.json['volume_curve_factor'] = 66;}
	else if ($('#volume-curve-factor span').text() == "Less (-20)") {TCMCONF.json['volume_curve_factor'] = 76;}
	else if ($('#volume-curve-factor span').text() == "Less (-30)") {TCMCONF.json['volume_curve_factor'] = 86;}
	else if ($('#volume-curve-factor span').text() == "More (+06)") {TCMCONF.json['volume_curve_factor'] = 50;}
	else if ($('#volume-curve-factor span').text() == "More (+12)") {TCMCONF.json['volume_curve_factor'] = 44;}
	else if ($('#volume-curve-factor span').text() == "More (+18)") {TCMCONF.json['volume_curve_factor'] = 38;}
	TCMCONF.json['volume_max_percent'] = $('#volume-max-percent').val();

	// Audio device description
	// TC (Tim Curtis) 2015-03-21: change to span and text() for audio device dropdown
	TCMCONF.json['audio_device_name'] = $('#audio-device-name span').text();
	TCMCONF.json['audio_device_dac'] = $('#audio-device-dac').val();
	TCMCONF.json['audio_device_arch'] = $('#audio-device-arch').val();
	TCMCONF.json['audio_device_iface'] = $('#audio-device-iface').val();
	TCMCONF.json['audio_device_other'] = $('#audio-device-other').val();

	// Update tcmods.conf file
	var rtnString = updateTcmConf();

	// TC (Tim Curtis) 2015-06-26: moved this from before updateTcmConf() to avoid double update/overwrite scenario
	if (GUI.volume > parseInt($('#volume-warning-limit').val())) {
		setVolume($('#volume-warning-limit').val());
	}

	if (themeChange == true) {
		// Change theme color, also reloads server-side data
		$.post('settings.php', {syscmd:TCMCONF.json['theme_color'].toLowerCase()});
		M.notify('themechange');
	} else {
		// Reload server-side data
		$.post('tcmods.php', {syscmd:'reloadtcmodsconf'});
		M.notify('updatetcmodsconf');
	}
});

// HANDLER FOR CUSTOM SELECT CONTROLS
// TC (Tim Curtis) 2014-12-23: initial version
// TC (Tim Curtis) 2015-01-27: add search-autofocus-enabled-yn
// TC (Tim Curtis) 2015-05-30: add play-history-enabled-yn
// TC (Tim Curtis) 2015-06-26: add logarithmic-curve-enabled-yn
// TC (Tim Curtis) 2015-06-26: add albumart-lookup-method elements
$('.dropdown-menu .tcmods-select a').click(function() {
	if ($(this).data('cmd') == 'clockradio-enabled-yn') {
		$('#clockradio-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'clockradio-starttime-ampm') {
		$('#clockradio-starttime-ampm span').text($(this).text());
	} else if ($(this).data('cmd') == 'clockradio-stoptime-ampm') {
		$('#clockradio-stoptime-ampm span').text($(this).text());
	} else if ($(this).data('cmd') == 'clockradio-shutdown-yn') {
		$('#clockradio-shutdown span').text($(this).text());
	} else if ($(this).data('cmd') == 'search-autofocus-enabled-yn') {
		$('#search-autofocus-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'theme-color-sel') { // TC (Tim Curtis) 2015-04-29: theme color select moved from System config page
		$('#theme-color span').text($(this).text());
	} else if ($(this).data('cmd') == 'play-history-enabled-yn') {
		$('#play-history-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'albumart-lookup-method') {
		$('#albumart-lookup-method span').text($(this).text());
	} else if ($(this).data('cmd') == 'logarithmic-curve-enabled-yn') {
		$('#logarithmic-curve-enabled span').text($(this).text());
	} else if ($(this).data('cmd') == 'volume-curve-factor') {
		$('#volume-curve-factor span').text($(this).text());
	// TC (Tim Curtis) 2015-03-21: audio device dropdown
	} else if ($(this).data('cmd') == 'audio-device-name-sel') {
		$('#audio-device-name span').text($(this).text());
		// DB lookup
		var audioDevObj = getAudioDevDesc($(this).text());
		// load fields
		$('#audio-device-dac').val(audioDevObj['dacchip']);
		$('#audio-device-arch').val(audioDevObj['arch']);
		$('#audio-device-iface').val(audioDevObj['iface']);
		$('#audio-device-other').val(audioDevObj['other']);
	}
});

// TC (Tim Curtis) 2015-01-27: set volume warning limit text
$("#volume-warning-limit").keyup(function() {
	if (parseInt($('#volume-warning-limit').val()) == 100) {
		$('#volume-warning-limit-aftertext').html('warning disabled');
	} else {
		$('#volume-warning-limit-aftertext').html('');
	}
});
$("#volume-warning-limit").change(function() {
	if (parseInt($('#volume-warning-limit').val()) == 100) {
		$('#volume-warning-limit-aftertext').html('warning disabled');
	} else {
		$('#volume-warning-limit-aftertext').html('');
	}
});

// TC (Tim Curtis) 2015-04-29: TESTING
// Drag & drop handlers for Playlist
/*
function allowDrop(ev) {
	ev.preventDefault();
}

function drag(ev) {
	ev.dataTransfer.setData("text", ev.target.id);
}

function drop(ev) {
	ev.preventDefault();
	var data = ev.dataTransfer.getData("text");
	ev.target.appendChild(document.getElementById(data));
}
