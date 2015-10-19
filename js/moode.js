/**
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TsunAMP; see the file COPYING.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 * Tsunamp Team (http://www.tsunamp.com)
 *
 * UI-design/JS code by: 	Andrea Coiutti (aka ACX)
 * PHP/JS code by:			Simone De Gregori (aka Orion)
 *
 * This is a full rewrite of the TCMODS Edition by Tim Curtis
 * Copyright (c) Andreas Goetz <cpuidle@gmx.de>
 */

/**
 * Global Moode object
 */
var M = {
	// settings
	logLevel: 1,			// set <> 0 to show messages

	// variables
	backendTimeout: null
};

/**
 * Console logging if logLevel > 0
 */
M.log = function(obj, opt) {
	if (this.logLevel) {
		console.log(obj);
		if (opt !== undefined) {
			console.log(opt);
		}
	}
};

/**
 * UI notifications per cmd
 */
M.notify = function(cmd, msg) {
	M.log("[M.notify]");
	msg = msg || ''; // msg optional

	var map = {
		add: 'Added to playlist',
		addreplaceplay: 'Added, Playlist replaced',
		addall: 'Added to playlist',
		addallreplaceplay: 'Added, Playlist replaced',
		update: 'Update path: ',
		remove: 'Removed from playlist',
		move: 'Playlist items moved',
		savepl: 'Playlist saved',
		needplname: 'Enter a name',
		deletesavedpl: 'Playlist deleted',
		deleteradiostn: 'Radio station deleted',
		addradiostn: 'Radio station added',
		updateradiostn: 'Radio station updated',
		updateclockradio: 'Clock radio updated',
		updatetcmodsconf: 'Custom config updated',
		themechange: 'Theme color changed, select Menu/Refresh to activate'
	};

	if (map[cmd] === undefined) {
		console.error('[notify] Unknown cmd ' + cmd);
	}

	var icon = (cmd == 'needplname') ? 'icon-info-sign' : 'icon-ok';
	$.pnotify({
		title: map[cmd],
		text: msg,
		icon: icon,
		delay: 2000,
		opacity: 0.9,
		history: false
	});
};

/**
 * Load json via ajax
 */
M.json = function(url, data, args) {
	M.log("[M.json]");

	var jsonArgs = $.extend({
		method: 'GET',
		accepts: 'application/json',
		dataType: 'json',
		data: data
	}, args);

	return $.ajax(url, jsonArgs);
};

/**
 * Long-poll MPD daemon status
 *
 * Does not return its state, instead update GUI variable via renderUI() call
 * TODO check if this is an infinite recursion
 */
M.backendRequest = function() {
	M.log("[M.backendRequest]");
	$.getJSON('_player_engine.php?state=' + GUI.state)
		.done(function(json) {
			// MPD state changed
			M.log('[M.backendRequest] done', json);
			window.clearTimeout(M.backendTimeout);
			M.backendTimeout = null;
			renderUI(json);
		})
		.fail(function() {
			// MPD request timed out
			M.log('[M.backendRequest] timeout/fail');
			// show timeout if we can't reconnect
			if (M.backendTimeout === null) {
				M.backendTimeout = window.setTimeout(function() {
					renderUI(null);
				}, 5000);
			}
		})
		.then(function() {
			M.log('[M.backendRequest] then');
			M.backendRequest();
		});
};

/**
 * Read Moode server configuration
 */
M.readTcmConf = function() {
	M.log("[M.readTcmConf]");
	return $.getJSON('tcmods.php?cmd=readtcmconf').done(function(json) {
		M.log("[M.readTcmConf] done", json);
		TCMCONF.json = json;
	})
	.fail(function() {
		console.error('[M.readTcmConf] failed');
	});
};

M.updateTcmConf = function() {
	M.log("[M.updateTcmConf]");
	return $.post('tcmods.php?cmd=updatetcmconf', TCMCONF.json, null, 'json')
	.fail(function() {
		console.error("[M.updateTcmConf] failed");
	});
};

/**
 * Database interaction
 */
M.getDB = function(cmd, path, browsemode, uplevel) {
	M.log('[M.getDB] ' + cmd);

	var updateWebRadio = function() {
		return M.getDB('update', 'WEBRADIO').done(function() {
			return M.getDB('filepath', 'WEBRADIO');
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
			// break; // unreachable

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
			// break; // unreachable

		case 'deletesavedpl':
			return $.post('db/?cmd=' + cmd, {
				'path': path
			}, null, 'json').done(function() {
				return M.getDB('filepath', '', null, 0);
			});
			// break; // unreachable

		case 'deleteradiostn':
			return $.post('db/?cmd=' + cmd, {
				'path': path
			}, null, 'json').done(function() {
				return updateWebRadio();
			});
			// break; // unreachable

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
			// break; // unreachable

		// TC (Tim Curtis) 2014-12-23: if no search keyword, dont post, clear search tally
		case 'search':
			var keyword = $('#db-search-keyword').val();
			if (keyword !== '') {
				return $.post('db/?querytype=' + browsemode + '&cmd=search', {
					'query': keyword
				}, function(data) {
					populateDB(data, path, uplevel, keyword);
				}, 'json');
			}
			else {
				$('#db-filter-results').html('');
				return $.Deferred().resolve();
			}
			break;

		default:
			console.error('[getDB] Unknown command: ' + cmd);
			return $.Deferred().reject();
	}
};

/**
 * MPD functions
 */
M.sendMpdCmd = function(cmd) {
	M.log("[M.sendMpdCmd]");
	return $.getJSON('db/?cmd=' + cmd).done(function(json) {
		M.log(json);
	});
};

/**
 * Get MPD current song data and format for GUI display as either Webradio or file source
 */
M.mpdCurrentSong = function() {
	M.log("[M.mpdCurrentSong]");
	return M.sendMpdCmd('currentsong').done(function(json) {
		M.log("[M.mpdCurrentSong]", json);
		MPDCS.json = json;

		// default cover
		MPDCS.coverurl = MPDCS.defaultcover;

		// TC (Tim Curtis) 2015-07-31: updated logic
		if (MPDCS.json.file !== undefined) {
			// RADIO STATION
			if (MPDCS.json.Name !== undefined || MPDCS.json.file.substr(0, 4) == "http" && MPDCS.json.Artist === undefined) {
				MPDCS.artist = 'Radio Station';
				MPDCS.title = MPDCS.json.Title || MPDCS.json.file;

				var obj = getRadioInfo(MPDCS.json.file);
				if (obj === null) { // station not in db
					MPDCS.album = MPDCS.json.Name || "Unknown Station";
					MPDCS.coverurl = MPDCS.webradiocover; // default radio cover
				}
				else {
					MPDCS.album = (obj.name.substr(0, 4) == "Soma") ? MPDCS.json.Name : obj.name; // use transmitted name for Soma stations
					MPDCS.coverurl = (obj.logo == "local") ? MPDCS.stnlogoroot + obj.name + ".png" : obj.logo;
				}
			}
			// SONG FILE OR UPNP SONG URL
			else {
				MPDCS.artist = MPDCS.json.Artist || "Unknown Artist";
				MPDCS.album = MPDCS.json.Album || "Unknown Album";
				// UPnP song url
				if (MPDCS.json.file.substr(0, 4) == "http") {
					MPDCS.title = MPDCS.json.Title || MPDCS.json.file;
					MPDCS.coverurl = makeUPNPCoverURL();
				}
				// song file
				else {
					if (MPDCS.json.Title === undefined) { // use file name
						var file = MPDCS.json.file.split('/').pop(); // file portion
						MPDCS.title = file.slice(0, file.lastIndexOf("."));
					}
					else {
						MPDCS.title = MPDCS.json.Title; // use title
					}
					MPDCS.coverurl = makeCoverURL(MPDCS.json.file);
				}
			}
		}

		return MPDCS;
	})
	.fail(function() {
		console.error('Error: mpdCurrentSong() no data returned');
	});
};
