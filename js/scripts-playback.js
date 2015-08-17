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

// TC (Tim Curtis) 2014-11-30: global for tcmods.conf file
var TCMCONF = {
	json: 0
};

// TC (Tim Curtis) 2014-12-23: global for triggering Library load
var libLoaded = false;

// TC (Tim Curtis) 2015-01-27: global for indicating time knob slider paint is complete
var timeKnobPaintComplete = false;

// TC (Tim Curtis) 2015-07-31: global for indicating page position on Playback panel when UI is vertical
var pageCycle = 1;

// INITIALIZATION SECTION
// Executed once at player start and whenever web page is reloaded
jQuery(document).ready(function($) { 'use strict';
	// First connection with MPD
	backendRequest(GUI.state);

	// TC (Tim Curtis) 2014-11-30: read tcmods.conf file, update state/color of clockradio icons
	TCMCONF.json = readTcmConf();
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

	// Populate browse panel root
	getDB('filepath', GUI.currentpath, 'file');

	// Hide "Connecting" screen
	if (GUI.state != 'disconnected') {
		$('#loader').hide();
	}

	// TC (Tim Curtis) 2015-07-31: set volume knob to readonly if MPD volume control = disabled
	if (TCMCONF.json['volume_mixer_type'] == "disabled") {
		$('#volume, #volume-2').attr('data-readOnly', "true");
		$('#volume, #volume-2').attr('data-fgColor', "#4c5454"); // shade of Asbestos
		TCMCONF.json['volume_knob_setting'] = 0;
		var rtnString = updateTcmConf();
	}
	else {
		$('#volume, #volume-2').attr('data-readOnly', "false")
	}

	// TC (Tim Curtis) 2015-06-26: for new setVolume() volume control
	GUI.volume = TCMCONF.json['volume_knob_setting'];
	$('#volume, #volume-2').val(GUI.volume);

	// BUTTON CLICK HANDLERS
	// TC (Tim Curtis) 2015-01-01: remove highlighting and implement play/pause toggle, stop btn code removed
	$('.btn-cmd').click(function() {
		var cmd;
		// TC (Tim Curtis) 2015-06-26: added
		var vol;
		var volevent;

		// Play/pause
		if ($(this).attr('id') == 'play') {
			if (GUI.state == 'play') {
				$("#play i").removeClass("icon-play").addClass("icon-pause"); // TC 2015-01-01
				// TC (Tim Curtis) 2014-11-30: stop for radio station, pause for song file
				// TC (Tim Curtis) 2015-04-29: add logic to handle UPnP song files (file= http://...)
				if (MPDCS.json.file.substr(0, 5).toLowerCase() == 'http:') {
					if (typeof MPDCS.json.Artist != 'undefined') { // UPnP song file
						cmd = 'pause';
					} else { // radio station
						cmd = 'stop';
					}
				} else {
					cmd = 'pause';
				}
				$('#countdown-display').countdown('pause');
			} else if (GUI.state == 'pause') {
				$("#play i").removeClass("icon-pause").addClass("icon-play"); // TC 2015-01-01
				cmd = 'play';
				$('#countdown-display').countdown('resume');

				var current = parseInt(GUI.json['song']); // TC (Tim Curtis) 2015-04-29: add scrollto
				customScroll('pl', current, 200);
			} else if (GUI.state == 'stop') {
				cmd = 'play';
				// TC (Tim Curtis) 2014-11-30: count up or down depending on conf setting, radio always counts up
				// TC (Tim Curtis) 2015-01-27: add onTick and chg format 'MS' to 'hMS'
				// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
				if (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) {
					$('#countdown-display').countdown({since: 0, onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				} else {
					$('#countdown-display').countdown({until: parseInt(GUI.json['time']), onTick: watchCountdown, compact: true, format: 'hMS', layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'});
				}

				var current = parseInt(GUI.json['song']); // TC (Tim Curtis) 2015-04-29: add scrollto
				customScroll('pl', current, 200);
			}
			window.clearInterval(GUI.currentKnob);
			sendMpdCmd(cmd);
			return;
		}

		// Previous/next
		// TC (Tim Curtis) 2015-04-29: chg 'previous' to 'prev'
		else if ($(this).attr('id') == 'prev' || $(this).attr('id') == 'next') {
			GUI.halt = 1;
			$('#countdown-display').countdown('pause');
			window.clearInterval(GUI.currentKnob);
		}

		// Volume up/down/mute btns
		// TC (Tim Curtis) 2014-11-30: add volumemute-2 code for 2nd volume control
		else if ($(this).hasClass('btn-volume')) {
			volevent = ''; // TC (Tim Curtis) 2015-06-26: initialize to null

			if (GUI.volume == null) {
				GUI.volume = $('#volume').val();
			}

			var guiVolume = parseInt(GUI.volume);
			if ($(this).attr('id') == 'volumedn') {
				vol = guiVolume > 0 ? guiVolume - 1 : guiVolume;
				GUI.volume = vol;
			} else if ($(this).attr('id') == 'volumeup') {
				// TC (Tim Curtis) 2015-01-27: volume warning popup
				vol = guiVolume < 100 ? guiVolume + 1 : guiVolume;

				if (vol > parseInt(TCMCONF.json['volume_warning_limit'])) {
					$('#volume-warning-text').text('Volume setting ' + vol + ' exceeds the warning limit of ' + TCMCONF.json['volume_warning_limit']);
					$('#volumewarning-modal').modal();
				} else {
					GUI.volume = vol;
				}
			} else if ($(this).attr('id') == 'volumemute' || $(this).attr('id') == 'volumemute-2') {
				// TC (Tim Curtis) 2015-06-26: TESTING MPD Logarithmic volume control
				TCMCONF.json = readTcmConf();
				if (TCMCONF.json['volume_muted'] == 0) {
					TCMCONF.json['volume_muted'] = 1 // toggle unmute to mute
					$("#volumemute").addClass('btn-primary');
					$("#volumemute-2").addClass('btn-primary');
					vol = 0;
					volevent = "mute";
				} else {
					TCMCONF.json['volume_muted'] = 0 // toggle mute to unmute
					$("#volumemute").removeClass('btn-primary');
					$("#volumemute-2").removeClass('btn-primary');
					vol = TCMCONF.json['volume_knob_setting'];
					volevent = "unmute";
				}
				var rtnString = updateTcmConf();
			}

			// TC (Tim Curtis) 2015-01-27: check volume warning limit
			if (vol <= parseInt(TCMCONF.json['volume_warning_limit'])) {
				// TC (Tim Curtis) 2015-06-26: new volume control
				setVolume(vol, volevent);
			}
		}

		// Toggle buttons
		if ($(this).hasClass('btn-toggle')) {
			if ($(this).hasClass('btn-primary')) {
				cmd = $(this).attr('id') + ' 0';
			} else {
				cmd = $(this).attr('id') + ' 1';
			}
			$(this).toggleClass('btn-primary');
		}
		// Send command, note handles next/previous
		else {
			// TC (Tim Curtis) 2014-11-30: for 3-button play controls, previous
			// TC (Tim Curtis) 2015-04-29: chg 'previous' to 'prev'
			if ($(this).attr('id') == 'prev' && parseInt(GUI.json['time']) > 0 && parseInt(getMpdStatus()['elapsed']) > 0) {
				refreshTimer(0, 0, 'stop'); // reset to beginning of song and pause
				sendMpdCmd('seek ' + GUI.json['song'] + ' ' + 0);
				if (GUI.state != 'pause') {
					cmd = 'pause';
				} else {
					cmd = '';
				}
			} else {
				cmd = $(this).attr('id') == 'prev' ? 'previous' : $(this).attr('id');
			}
		}
		sendMpdCmd(cmd);
	});

	// KNOB EVENT PROCESSING
	// Countdown timer knob
	$('.playbackknob').knob({
		inline: false,
		change : function (value) {
			if (GUI.state != 'stop') {
				window.clearInterval(GUI.currentKnob)
				// TC (Tim Curtis) 2015-01-27: update time display when changing slider
				// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
				var seekto = Math.floor((value * parseInt(GUI.json['time'])) / 1000);
				var time = (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) === 0) ? seekto : parseInt(GUI.json['time']) - seekto;
				$('#countdown-display').html(timeConvert(time));
			} else {
				$('#time').val(0);
			}
			// TC (Tim Curtis) 2015-01-27: set global indicating re-paint is needed
			timeKnobPaintComplete = false;
		},
		release : function (value) {
			if (GUI.state != 'stop') {
				GUI.halt = 1;
				window.clearInterval(GUI.currentKnob);
				var seekto = Math.floor((value * parseInt(GUI.json['time'])) / 1000);
				sendMpdCmd('seek ' + GUI.json['song'] + ' ' + seekto);
				// TC (Tim Curtis) 2015-01-27: not needed >$('#time').val(value);<
				// TC (Tim Curtis) 2015-01-27: comment out fixes brief revert to count up >$('#countdown-display').countdown('destroy');<
				// TC (Tim Curtis) 2014-11-30: count up or down depending on conf setting, radio always counts up
				// TC (Tim Curtis) 2015-01-27: add onTick and chg format 'MS' to 'hMS'
				// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
				$('#countdown-display').countdown({
					until: (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) === 0) ? -seekto : seekto,
					onTick: watchCountdown,
					compact: true,
					format: 'hMS',
					layout: '{h<}{hn}{sep}{h>}{mnn}{sep}{snn}'
				});
			}
			// TC (Tim Curtis) 2015-01-27: set global indicating re-paint is needed
			timeKnobPaintComplete = false;
		}
	});

	// Volume control knob
	$('.volumeknob').knob({
		change : function (value) {
			// TC (Tim Curtis) 2015-01-27: volume warning popup
			if (value > parseInt(TCMCONF.json['volume_warning_limit'])) {
				$('#volume-warning-text').text('Volume setting ' + value + ' exceeds the warning limit of ' + TCMCONF.json['volume_warning_limit']);
				$('#volumewarning-modal').modal();
				// TC (Tim Curtis) 2015-06-26: new volume control
				setVolume(GUI.volume, "change"); // restore original value
			}
			else {
				// TC (Tim Curtis) 2015-06-26: new volume control
				if (GUI.volumeTimeout !== null) {
					clearTimeout(GUI.volumeTimeout);
					GUI.volumeTimeout = null;
				}
				GUI.volumeTimeout = setTimeout(function() {
					setVolume(value, "change");
				}, 100);
			}
		},
		draw : function () {
			// Skin "tron"
			if (this.$.data('skin') == 'tron') {

				var a = this.angle(this.cv)	// Angle
					, sa = this.startAngle	// Previous start angle
					, sat = this.startAngle	// Start angle
					, ea					// Previous end angle
					, eat = sat + a			// End angle
					, r = true;

				this.g.lineWidth = this.lineWidth;

				this.o.cursor
					&& (sat = eat - 0.05)
					&& (eat = eat + 0.05);

				if (this.o.displayPrevious) {
					ea = this.startAngle + this.angle(this.value);
					this.o.cursor
						&& (sa = ea - 0.1)
						&& (ea = ea + 0.1);
					this.g.beginPath();
					this.g.strokeStyle = this.previousColor;
					this.g.arc(this.xy, this.xy, this.radius - this.lineWidth, sa, ea, false);
					this.g.stroke();
				}

				this.g.beginPath();
				this.g.strokeStyle = r ? this.o.fgColor : this.fgColor ;
				this.g.arc(this.xy, this.xy, this.radius - this.lineWidth, sat, eat, false);
				this.g.stroke();

				this.g.lineWidth = 2;
				this.g.beginPath();
				this.g.strokeStyle = this.o.fgColor;
				this.g.arc(this.xy, this.xy, this.radius - this.lineWidth + 10 + this.lineWidth * 2 / 3, 0, 20 * Math.PI, false);
				this.g.stroke();

				return false;
			}
		}
	});

	// TOOLBAR HIDE/SHOW
	// TC (Tim Curtis) 2014-12-23: toolbar btn click handler
	// TC (Tim Curtis) 2015-04-29: add support for individual toolbars
	$('#toolbar-btn').click(function() {
		// Browse panel
		if ($('#open-panel-sx').hasClass('active')) {
			if ($('.btnlist-top-db').hasClass('hidden')) {
				$('.btnlist-top-db').removeClass('hidden');
				$('.btnlist-bottom-db').removeClass('hidden');
				$('#database').css({"padding":"80px 0"});
			} else {
				$('.btnlist-top-db').addClass('hidden');
				$('.btnlist-bottom-db').addClass('hidden');
				$('#database').css({"padding":"40px 0"});
			}
			// search auto-focus
			if ($('#db-currentpath span').text() == '' || $('#db-currentpath span').text().substr(0, 3) == 'NAS') {
				if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#db-search-keyword').focus();}
			} else {
				if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#rs-filter').focus();}
			}
		// Library panel
		} else if ($('#open-panel-lib').hasClass('active')) {
			if ($('.btnlist-top-lib').hasClass('hidden')) {
				$('.btnlist-top-lib').removeClass('hidden');
				$('#lib-content').css({"top":"80px"});
			} else {
				$('.btnlist-top-lib').addClass('hidden');
				$('#lib-content').css({"top":"40px"});
			}
			// search auto-focus
			if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#lib-album-filter').focus();}
		// Playback panel
		} else if ($('#open-playback').hasClass('active')) {
			if ($('.btnlist-top-pl').hasClass('hidden')) {
				$('.btnlist-top-pl').removeClass('hidden');
				$('.btnlist-bottom-pl').removeClass('hidden');
			} else {
				$('.btnlist-top-pl').addClass('hidden');
				$('.btnlist-bottom-pl').addClass('hidden');
			}
			// search auto-focus
			if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#pl-filter').focus();}
		// Playlist panel
		} else if ($('#open-panel-dx').hasClass('active')) {
			// moved to Playback panel
		}
	});

	// COUNTDOWN DIRECTION INDICATOR
	// TC (Tim Curtis) 2014-12-23: toggle count up/down and direction indicator, radio always counts up
	$('#countdown-display').click(function() {
		// Toggle the setting
		TCMCONF.json['time_knob_countup'] == "1" ? TCMCONF.json['time_knob_countup'] = "0" : TCMCONF.json['time_knob_countup'] = "1"

		// Update tcmods.conf file
		var rtnString;
		rtnString = updateTcmConf()

		// Update timer count and direction indicator, use getMpdStatus() to obtain exact elapsed time
		// TC (Tim Curtis) 2015-01-27: remove timer_knob_radiocount
		if (TCMCONF.json['time_knob_countup'] == "1" || parseInt(GUI.json['time']) == 0) {
			refreshTimer(parseInt(getMpdStatus()['elapsed']), parseInt(GUI.json['time']), GUI.json['state']); // count up
			$('#total').html(timeConvert(GUI.json['time']) + '<i class="icon-caret-up countdown-caret"></i>');
		} else {
			refreshTimer(parseInt(GUI.json['time'] - parseInt(getMpdStatus()['elapsed'])), 0, GUI.json['state']); // count down
			$('#total').html(timeConvert(GUI.json['time']) + '<i class="icon-caret-down countdown-caret"></i>');
		}
	});

	// PLAYLIST ITEM, ACTION MENU AND SAVE CLICK HANDLERS
	// click on playlist entry
	$('.playlist').on('click', '.pl-entry', function() {
		var pos = $('.playlist .pl-entry').index(this);
		var cmd = 'play ' + pos;
		sendMpdCmd(cmd);
		GUI.halt = 1;
		$('.playlist li').removeClass('active');
		$(this).parent().addClass('active');
	});

	// TC (Tim Curtis) 2014-12-23: click on playlist action menu button
	$('.playlist').on('click', '.pl-action', function() {
		//$('.playlist li').removeClass('active');
		//$(this).parent().addClass('active');

		//console.log('window height=', $(window).height());
		//console.log('btn offset=', $(this).offset().top);
		//console.log('window scrolltop=', $(window).scrollTop());
		//console.log('btn pos=', $(this).offset().top - $(window).scrollTop());
		//console.log('diff=', $(window).height() - ($(this).offset().top - $(window).scrollTop()));

		// TC (Tim Curtis) 2015-01-27: adjust menu position so its always visible
		//console.log('diff=', $(window).height() - ($(this).offset().top - $(window).scrollTop()));
		var posTop = "-92px"; // new btn pos
		var relOfs = 212;  // btn offset relative to window
		if ($(window).height() - ($(this).offset().top - $(window).scrollTop()) <= relOfs) {
			$('#context-menus .dropdown-menu').css({"top":posTop}); // 3 menu items
		} else {
			$('#context-menus .dropdown-menu').css({"top":"0px"});
		}
		GUI.DBentry[0] = $('.playlist .pl-action').index(this); // store posn for later use by action menu selection

		// For clock radio, reuse GUI.DBentry[3] which is also used on the Browse panel
		// TC (Tim Curtis) 2015-01-27: fix wrong if () test to differentiate radio station from song
		// TC (Tim Curtis) 2015-01-27: use textContent for radio station to avoid html esc codes
		// TC (Tim Curtis) 2015-01-27: replace colon (:) with semicolon (;) in radio station name to avoid parsing error in readTcmConf()
		// TC (Tim Curtis) 2015-01-27: replace certain html escape codes in song title
		if ($('.playlist .pl-entry span').get(GUI.DBentry[0]).innerHTML.substr(0, 2) == '<i') { // has icon-microphone
			// Radio ststion
			GUI.DBentry[3] = $('.playlist .pl-entry span').get(GUI.DBentry[0]).textContent.replace(/:/g, ";");
		} else {
			// Song title, artist
			GUI.DBentry[3] = $('.playlist .pl-entry').get(GUI.DBentry[0]).innerHTML;
			GUI.DBentry[3] = GUI.DBentry[3].substr(0, GUI.DBentry[3].indexOf("<em") - 1) + ", " + $('.playlist .pl-entry span').get(GUI.DBentry[0]).textContent;
			GUI.DBentry[3] = GUI.DBentry[3].replace(/&amp;/g, "&").replace(/&gt;/g, ">").replace(/&lt;/g, "<");
		}
	});

	// Playlist save click handler
	// TC (Tim Curtis) 2014-12-23: send '' instead of plname in notify()
	$('#pl-controls').on('click', '#pl-btnSave', function(event) {
		var plname = $("#pl-saveName").val();
		if (plname) {
			sendPLCmd('savepl&plname=' + plname);
			M.notify('savepl');
		} else {
			M.notify('needplname');
		}
	});

	// TAB BUTTON CLICK HANDLERS
	// TC (Tim Curtis) 2014-12-23: add click handlers for Browse and Library tabs
	// TC (Tim Curtis) 2014-12-23: hide/show certain header btns depending on tab
	// TC (Tim Curtis) 2014-12-23: load library only if not already loaded
	// TC (Tim Curtis) 2015-04-29: support for playback panel w integrated playlist
	// TC (Tim Curtis) 2015-07-31: hide/show playback-page-cycle button on Browse and Library panels

	// Click on Browse tab
	$('#open-panel-sx a').click(function() {
		$('.playback-controls').removeClass('hidden');
		$('#volume-ctl').removeClass('hidden');
		$('#toolbar-btn').removeClass('hidden');
		$('#playback-page-cycle').css({"display":"none"});
	});

	// Click on Library tab
	// TC (Tim Curtis) 2015-01-27: chg addClass to removeClass for lib, support toolbar
	$('#open-panel-lib a').click(function() {
		$('.playback-controls').removeClass('hidden');
		$('#volume-ctl').removeClass('hidden');
		$('#toolbar-btn').removeClass('hidden');
		$('#playback-page-cycle').css({"display":"none"});

		if (!libLoaded) {
			$("#lib-loader").show();
			$.post('db/?cmd=loadlib', {}, function(data) {
				$("#lib-loader").hide();
				$("#lib-content").show();
				loadLibrary(data);
				libLoaded = true;
			}, 'json');
		}
	});

	// Click on Playback tab
	$('#open-playback a').click(function() {
		$('.playback-controls').addClass('hidden');
		$('#volume-ctl').removeClass('hidden');
		$('#toolbar-btn').removeClass('hidden'); // TC (Tim Curtis) 2015-04-29: playback tab has a toolbar for integrated playlist
		$('#playback-page-cycle').css({"display":"inline"});
		pageCycle = 1;
		//$('#toolbar-btn').addClass('hidden');
		var current = parseInt(GUI.json['song']);  // TC (Tim Curtis) 2015-04-29: scrollto when click
		customScroll('pl', current, 200);
	});

	// TC (Tim Curtis) 2015-07-31: click to cycle through knobs and album art when UI is vertical
	$('#playback-page-cycle').click(function() {
		if (pageCycle == 1) {
			var selector = "#timeknob";
			pageCycle = 2;
		} else if (pageCycle == 2) {
			var selector = ".covers";
			pageCycle = 1;
		}

		$('html, body').animate({
			scrollTop: $(selector).offset().top - 30
		}, 200);

		// #container-playlist (or #playlist), #timeknob, .covers
	});

	// DATABASE PANEL CLICK HANDLERS
	// Click on back btn"
	$('#db-back').click(function() {
		--GUI.currentDBpos[10];
		var path = GUI.currentpath;
		var cutpos=path.lastIndexOf("/");
		if (cutpos !=-1) {
			var path = path.slice(0,cutpos);
		}  else {
			path = '';
		}
		getDB('filepath', path, GUI.browsemode, 1);
	});

	// Click on database entry
	// TC (Tim Curtis) 2014-09-17: added else if to handle onclick for saved playlist db-entry
	// TC (Tim Curtis) 2014-10-31: remove onclick row highlight
	// TC (Tim Curtis) 2015-01-01: toggle db, typedown search
	$('.database').on('click', '.db-browse', function() {
		//$('.database li').removeClass('active');
		//$(this).parent().addClass('active');
		// TC (Tim Curtis) 2015-01-01: show toolbars
		// TC (Tim Curtis) 2015-01-27: add Library margin setting
		if ($('.btnlist-top-db').hasClass('hidden')) {
			$('.btnlist-top-db').removeClass('hidden');
			$('.btnlist-bottom-db').removeClass('hidden');
			//$('#playlist').css({"padding":"80px 0"}); // TC (Tim Curtis) 2015-04-29: not needed since individual toolbars
			$('#database').css({"padding":"80px 0"});
			$('#lib-content').css({"top":"80px"});
		}
		if (!$(this).hasClass('sx')) {
			if ($(this).hasClass('db-folder')) {
				var path = $(this).parent().data('path');
				var entryID = $(this).parent().attr('id');
				entryID = entryID.replace('db-','');
				GUI.currentDBpos[GUI.currentDBpos[10]] = entryID;
				++GUI.currentDBpos[10];
				getDB('filepath', path, 'file', 0);
				// TC (Tim Curtis) 2015-01-01: toggle db, typedown search
				// TC (Tim Curtis) 2015-01-27: set focus to search field
				if (path == 'WEBRADIO') {
					$('#db-search').addClass('db-form-hidden');
					$('#db-search-input').addClass('hidden');
					$('#rs-search-input').removeClass('hidden');
					if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#rs-filter').focus();}
				} else {
					$('#rs-search-input').addClass('hidden');
					$('#db-search').removeClass('db-form-hidden');
					$('#db-search-input').removeClass('hidden');
					if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#db-search-keyword').focus();}
				}
			} else if ($(this).hasClass('db-savedplaylist')) {
				var path = $(this).parent().data('path');
				var entryID = $(this).parent().attr('id');
				entryID = entryID.replace('db-','');
				GUI.currentDBpos[GUI.currentDBpos[10]] = entryID;
				++GUI.currentDBpos[10];
				getDB('listsavedpl', path, 'file', 0);
				// TC (Tim Curtis) 2015-01-01: typedown search
				// TC (Tim Curtis) 2015-01-27: set focus to search field
				$('#db-search').addClass('db-form-hidden');
				$('#db-search-input').addClass('hidden');
				$('#rs-search-input').removeClass('hidden');
				if (TCMCONF.json['search_autofocus_enabled'] == 'Yes') {$('#rs-filter').focus();}
			}
		}
	});

	// Click on browse action menu button
	$('.database').on('click', '.db-action', function() {
		GUI.DBentry[0] = $(this).parent().attr('data-path');
		// TC (Tim Curtis) 2014-10-31: store row posn in newly added GUI.DBentry[3]
		// TC (Tim Curtis) 2014-10-31: highlight row
		GUI.DBentry[3] = $(this).parent().attr('id'); // Used in .context-menu a click handler to remove highlight
		$('.database li').removeClass('active');
		$(this).parent().addClass('active');

		// TC (Tim Curtis) 2015-01-27: adjust menu position so its always visible
		var posTop = ''; // New btn pos
		var relOfs = 0;  // Btn offset relative to window
		var menuId = $('.db-action a').attr('data-target');

		if (menuId == '#context-menu-savedpl-item' || menuId == '#context-menu-folder-item') { // 3 menu items
			posTop = '-92px';
			relOfs = 212;
		} else if (menuId == '#context-menu' || menuId == '#context-menu-root') { // 4 menu items
			posTop = '-132px';
			relOfs = 252;
		} else if (menuId == '#context-menu-webradio-item') { // 6 menu items
			posTop = '-212px';
			relOfs = 332;
		}

		if ($(window).height() - ($(this).offset().top - $(window).scrollTop()) <= relOfs) {
			$('#context-menus .dropdown-menu').css({"top":posTop});
		} else {
			$('#context-menus .dropdown-menu').css({"top":"0px"});
		}
	});

	// chiudi i risultati di ricerca nel DB
	$('.database').on('click', '.search-results', function() {
		getDB('filepath', GUI.currentpath);
	});

	// ACTION MENU AND MAIN MENU CLICK HANDLERS
	// TC (Tim Curtis) 2014-12-23: send '' instead of path in notify()
	$('.context-menu a').click(function() {
		var path = GUI.DBentry[0]; // File path or item num

		if ($(this).data('cmd') == 'add') {
			getDB('add', path);
			M.notify('add');
		}
		if ($(this).data('cmd') == 'addplay') {
			getDB('addplay', path);
			M.notify('add');
		}
		if ($(this).data('cmd') == 'addreplaceplay') {
			getDB('addreplaceplay', path);
			M.notify('addreplaceplay');
			// TC (Tim Curtis) 2014-09-17: bug fix typeError in path.contains("/"), change to path.indexof
			if (path.indexOf("/") == -1) {  // Its a playlist, preload the saved playlist name
				$("#pl-saveName").val(path);
			} else {
				$("#pl-saveName").val("");
			}
		}
		if ($(this).data('cmd') == 'update') {
			getDB('update', path);
			M.notify('update', path);
		}
		// TC (Tim Curtis) 2014-09-17: action to delete saved playlist
		// TC (Tim Curtis) 2014-11-30: modal to confirm delete actions
		// TC (Tim Curtis) 2014-12-23: add, edit station
		// TC (Tim Curtis) 2014-12-23: delete, move pl items
		if ($(this).data('cmd') == 'deletesavedpl') {
			$('#savedpl-path').html(path);
			$('#deletesavedpl-modal').modal();
		}
		if ($(this).data('cmd') == 'deleteradiostn') {
			// Trim "WEBRADIO/" and ".pls" from path
			$('#station-path').html(path.slice(0,path.lastIndexOf(".")).substr(9));
			$('#deletestation-modal').modal();
		}
		if ($(this).data('cmd') == 'addradiostn') {
			// Set input fields to default values for modal form
			$('#add-station-name').val("New Station");
			$('#add-station-url').val("http://");
			$('#addstation-modal').modal();
		}
		if ($(this).data('cmd') == 'editradiostn') {
			path = path.slice(0,path.lastIndexOf(".")).substr(9); // trim "WEBRADIO/" and ".pls" from path
			// Set input field to file values for modal form
			$('#edit-station-name').val(path);
			$('#edit-station-url').val(readStationFile(GUI.DBentry[0])['File1']);
			$('#editstation-modal').modal();
		}
		if ($(this).data('cmd') == 'deleteplitem') {
			// Set input fields for modal form
			// Max value (num pl items in list)
			$('#delete-plitem-begpos').attr('max', GUI.DBentry[4]);
			$('#delete-plitem-endpos').attr('max', GUI.DBentry[4]);
			$('#delete-plitem-newpos').attr('max', GUI.DBentry[4]);
			// Num of selected item
			$('#delete-plitem-begpos').val(path + 1);
			$('#delete-plitem-endpos').val(path + 1);
			$('#deleteplitems-modal').modal();
		}
		if ($(this).data('cmd') == 'moveplitem') {
			// Set input fields for modal form
			// Max value (num pl items in list)
			$('#move-plitem-begpos').attr('max', GUI.DBentry[4]);
			$('#move-plitem-endpos').attr('max', GUI.DBentry[4]);
			$('#move-plitem-newpos').attr('max', GUI.DBentry[4]);
			// Num of selected item
			$('#move-plitem-begpos').val(path + 1);
			$('#move-plitem-endpos').val(path + 1);
			$('#move-plitem-newpos').val(path + 1);
			$('#moveplitems-modal').modal();
		}

		// TC (Tim Curtis) 2014-10-31: remove row highlight after selecting action menu item (Browse)
		// TC (Tim Curtis) 2014-12-23: test for correct context
		//console.log('here', GUI.DBentry[3]);
		if (GUI.DBentry[3].substr(0, 3) == 'db-') {
			$('#' + GUI.DBentry[3]).removeClass('active');
		}
	});

	// BUTTON CLICK HANDLERS
	// TC (Tim Curtis) 2014-11-30: btns for delete playlist, radio station confirmation modals
	// TC (Tim Curtis) 2014-12-23: btns for add, update radio station modals
	// TC (Tim Curtis) 2014-12-23: btns for delete and move pl items
	// TC (Tim Curtis) 2014-12-23: send '' instead of GUI.DBentry[0] (path) in notify()
	$('.btn-del-savedpl').click(function() {
		getDB('deletesavedpl', GUI.DBentry[0]);
		M.notify('deletesavedpl');
	});
	$('.btn-del-radiostn').click(function() {
		getDB('deleteradiostn', GUI.DBentry[0]);
		M.notify('deleteradiostn');
	});
	$('.btn-add-radiostn').click(function() {
		getDB('addradiostn', $('#add-station-name').val() + "\n" + $('#add-station-url').val() + "\n");
		M.notify('addradiostn');
	});
	$('.btn-update-radiostn').click(function() {
		getDB('updateradiostn', $('#edit-station-name').val() + "\n" + $('#edit-station-url').val() + "\n");
		M.notify('updateradiostn');
	});
	$('.btn-delete-plitem').click(function() {
		var cmd = '';
		var begpos = $('#delete-plitem-begpos').val() - 1;
		var endpos = $('#delete-plitem-endpos').val() - 1;
		// Format for single or multiple, endpos not inclusive so must be bumped for multiple
		begpos == endpos ? cmd = 'trackremove&songid=' + begpos : cmd = 'trackremove&songid=' + begpos + ':' + (endpos + 1);
		M.notify('remove');
		sendPLCmd(cmd);
	});

	// TC (Tim Curtis) 2015-01-27: speed btns on delete modal
	$('#btn-delete-setpos-top').click(function() {
		$('#delete-plitem-begpos').val(1);
		return false;
	});
	$('#btn-delete-setpos-bot').click(function() {
		$('#delete-plitem-endpos').val(GUI.DBentry[4]);
		return false;
	});

	$('.btn-move-plitem').click(function() {
		var cmd = '';
		var begpos = $('#move-plitem-begpos').val() - 1;
		var endpos = $('#move-plitem-endpos').val() - 1;
		var newpos = $('#move-plitem-newpos').val() - 1;
		// Format for single or multiple, endpos not inclusive so must be bumped for multiple
		// Move begpos newpos or move begpos:endpos newpos
		begpos == endpos ? cmd = 'trackmove&songid=' + begpos + '&newpos=' + newpos : cmd = 'trackmove&songid=' + begpos + ':' + (endpos + 1) + '&newpos=' + newpos;
		M.notify('move');
		sendPLCmd(cmd);
	});
	// TC (Tim Curtis) 2015-01-27: speed btns on move modal
	$('#btn-move-setpos-top').click(function() {
		$('#move-plitem-begpos').val(1);
		return false;
	});
	$('#btn-move-setpos-bot').click(function() {
		$('#move-plitem-endpos').val(GUI.DBentry[4]);
		return false;
	});
	$('#btn-move-setnewpos-top').click(function() {
		$('#move-plitem-newpos').val(1);
		return false;
	});
	$('#btn-move-setnewpos-bot').click(function() {
		$('#move-plitem-newpos').val(GUI.DBentry[4]);
		return false;
	});

	// TC (tim Curtis) 2014-10-31: remove highlight when clicking off-row
	$('.database').on('click', '.db-song', function() {
		$('.database li').removeClass('active');
	});

	// VOLUME CONTROL POPUP CLICK HANDLER
	// TC (Tim Curtis) 2012-12-23: volume control popup
	$('.btn-volume-control').click(function() {
		$('#volume-modal').modal();
	});

	// SCROLL BUTTON CLICK HANDLER
	$('.db-firstPage').click(function() {
		$.scrollTo(0 , 500);
	});
	$('.db-prevPage').click(function() {
		var scrolloffset = '-=' + $(window).height() + 'px';
		$.scrollTo(scrolloffset , 500);
	});
	$('.db-nextPage').click(function() {
		var scrolloffset = '+=' + $(window).height() + 'px';
		$.scrollTo(scrolloffset , 500);
	});
	$('.db-lastPage').click(function() {
		$.scrollTo('100%', 500);
	});

	$('.pl-firstPage').click(function() {
		$('#container-playlist').scrollTo(0 , 500);  // TC (Tim Curtis) 2015-04-29: for playback panel w integrated playlist
	});
	$('.pl-prevPage').click(function() {
		var scrollTop = $(window).scrollTop();
		var scrolloffset = scrollTop - $(window).height();
		$.scrollTo(scrolloffset , 500);
	});
	$('.pl-nextPage').click(function() {
		var scrollTop = $(window).scrollTop();
		var scrolloffset = scrollTop + $(window).height();
		$.scrollTo(scrolloffset , 500);
	});
	$('.pl-lastPage').click(function() {
		$('#container-playlist').scrollTo('100%', 500); // TC (Tim Curtis) 2015-04-29: for playback panel w integrated playlist
	});

	// TC (Tim Curtis) 2015-05-30; plaback history first/last page click handlers
	$('.ph-firstPage').click(function() {
		$('#container-playhistory').scrollTo(0 , 500);
	});
	$('.ph-lastPage').click(function() {
		$('#container-playhistory').scrollTo('100%', 500);
	});

	// TC (Tim Curtis) 2015-06-26; customization settings first/last page click handlers
	$('.cs-firstPage').click(function() {
		$('#container-customize').scrollTo(0 , 500);
	});
	$('.cs-lastPage').click(function() {
		$('#container-customize').scrollTo('100%', 500);
	});

	// DEBUG BUTTON CLICK HANDLERS
	$('#db-debug-btn').click(function() {
		var scrollTop = $(window).scrollTop();
	});
	$('#pl-debug-btn').click(function() {
		randomScrollPL();
	});

	// OPEN TAB FROM EXTERNAL LINK
	var url = document.location.toString();
	if (url.match('#')) {
		$('#menu-bottom a[href=#'+url.split('#')[1]+']').tab('show') ;
	}

	// DO NOT SCROLL WITH HTML5 HISTORY API
	$('#menu-bottom a').on('shown', function (e) {
		if(history.pushState) {
			history.pushState(null, null, e.target.hash);
		} else {
			window.location.hash = e.target.hash; //Polyfill for old browsers
		}
	});

	// SEARCH INPUT HANDLERS
	// Playlist typedown search
	$("#pl-filter").keyup(function() {
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$(".playlist li").each(function() {
			if ($(this).text().search(new RegExp(filter, "i")) < 0) {
				$(this).hide();
			} else {
				$(this).show();
				count++;
			}
		});
		// TC (Tim Curtis) 2014-12-23: change format of search results line
		var s = (count == 1) ? '' : 's';
		if (filter != '') {
			$('#pl-filter-results').html((+count) + '&nbsp;item' + s);
		} else {
			$('#pl-filter-results').html('');
		}
	});

	// TC (Tim Curtis) 2015-01-01: radio station typedown search
	$("#rs-filter").keyup(function() {
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$(".database li").each(function() {
			if ($(this).text().search(new RegExp(filter, "i")) < 0) {
				$(this).hide();
			} else {
				$(this).show();
				count++;
			}
		});
		var s = (count == 1) ? '' : 's';
		if (filter != '') {
			$('#db-filter-results').html((+count) + '&nbsp;station' + s);
		} else {
			$('#db-filter-results').html('');
		}
	});

	// TC (Tim Curtis) 2015-01-27: typedown search for library albumslist
	$("#lib-album-filter").keyup(function() {
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$(".albumslist li").each(function() {
			if ($(this).text().search(new RegExp(filter, "i")) < 0) {
				$(this).hide();
			} else {
				$(this).show();
				count++;
			}
		});
		var s = (count == 1) ? '' : 's';
		if (filter != '') {
			$('#lib-album-filter-results').html((+count) + '&nbsp;album' + s);
		} else {
			$('#lib-album-filter-results').html('');
		}
	});

	// Playlist history typedown search
	$("#ph-filter").keyup(function() {
		$.scrollTo(0 , 500);
		var filter = $(this).val(), count = 0;
		$(".playhistory li").each(function() {
			if ($(this).text().search(new RegExp(filter, "i")) < 0) {
				$(this).hide();
			} else {
				$(this).show();
				count++;
			}
		});
		// TC (Tim Curtis) 2014-12-23: change format of search results line
		var s = (count == 1) ? '' : 's';
		if (filter != '') {
			$('#ph-filter-results').html((+count) + '&nbsp;item' + s);
		} else {
			$('#ph-filter-results').html('');
		}
	});

	// TOOLTIPS HANDLER
	if( $('.ttip').length ){
		$('.ttip').tooltip();
	}

	// HEADER BUTTON SHOW/HIDE STATE HANDLER
	// TC (Tim Curtis) 2014-12-23: set show/hide state of toolbar and vol header btns
	// TC (Tim Curtis) 2015-01-27: chg addClass to removeClass for lib, support toolbar
	// TC (Tim Curtis) 2015-04-29: playback panel has own toolbar
	// TC (Tim Curtis) 2015-07-31: hide/show playback-page-cycle button on Browse and Library panels
	// Browse panel
	if ($('#open-panel-sx').hasClass('active')) {
		$('.playback-controls').removeClass('hidden');
		$('#volume-ctl').removeClass('hidden');
		$('#toolbar-btn').removeClass('hidden');
		$('#playback-page-cycle').css({"display":"none"});
		//$('#playback-page-cycle').addClass('hidden');
	// Library panel
	} else if ($('#open-panel-lib').hasClass('active')) {
		$('.playback-controls').removeClass('hidden');
		$('#volume-ctl').removeClass('hidden');
		$('#toolbar-btn').removeClass('hidden');
		$('#playback-page-cycle').css({"display":"none"});
		//$('#playback-page-cycle').addClass('hidden');
	// Playback panel
	} else if ($('#open-playback').hasClass('active')) {
		$('.playback-controls').addClass('hidden');
		$('#volume-ctl').removeClass('hidden');
		$('#toolbar-btn').removeClass('hidden');  // TC (Tim Curtis) 2015-04-29: playback panel has own toolbar
		$('#playback-page-cycle').css({"display":"inline"});
		//$('#playback-page-cycle').removeClass('hidden');
	}

	// CONTROL WHEN LIBRARY LOADS
	// TC (Tim Curtis) 2014-12-23: load library only if on the Library tab and page reload requested
	if ($('#open-panel-lib').hasClass('active')) {
		//console.log('scripts-playback.js: library load started');
		$("#lib-loader").show();
		$.post('db/?cmd=loadlib', {}, function(data) {
			$("#lib-loader").hide();
			$("#lib-content").show();
			loadLibrary(data);
			libLoaded = true;
		}, 'json');
	}

}); // End

// TC (Tim Curtis) 2015-05-30: info show/hide toggle
$('.info-toggle').click(function() {
	var spanId = '#' + $(this).data('cmd');
	if ($(spanId).hasClass('hide')) {
		$(spanId).removeClass('hide');
	} else {
		$(spanId).addClass('hide');
	}
});
