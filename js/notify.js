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
 *	file:					notify.js
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 *	TC (Tim Curtis) 2014-08-23, r1.0
 * 	- added delay = 1000 (1 sec) to override default of 8 secs
 *
 *	TC (Tim Curtis) 2014-09-17, r1.1
 *	- delete saved playlist (Browse panel)
 *	- play all songs in list (Library panel)
 *	- add all songs in list (Library panel)
 *	- clear playlist, add all songs in list (Library panel)
 *
 *	TC (Tim Curtis) 2014-12-23, r1.3
 *	- notify delete radio station (Browse panel)
 *	- add and edit radio station (Browse panel)
 *	- increase timeout from 1000 to 2000 ms
 *	- case for delete, move playlist items
 *	- case for update clock radio
 *	- clean up titles
 *	- replace icon-remove w icon-ok
 *
 *	TC (Tim Curtis) 2015-01-27, r1.5
 *	- case for update tcmods config
 *
 *	TC (Tim Curtis) 2015-03-21, r1.7
 *	- add history:false to prevent history tab from appearing when on Config pages
 *	- Change "TCMODS config updated" to "Custom config updated"
 *
 *	TC (Tim Curtis) 2015-04-29, r1.8
 *	- add theme change notification, moved from settings.php
 *
 *	TC (Tim Curtis) 2015-05-30, r1.9
 *	- change text in 'themechange' to reflect new option for refreshing web page
 *
 */
 
function notify(command, msg) {
	switch (command) {
		case 'add':
			$.pnotify({
				title: 'Added to playlist',
				text: msg,
				icon: 'icon-ok',
				delay: 2000,
				opacity: .9,
				history:false
			});
			break;

		case 'addreplaceplay':
			$.pnotify({
				title: 'Added, Playlist replaced',
				text: msg,
				icon: 'icon-ok',
				delay: 2000,
				opacity: .9,
				history:false
			});
			break;
		

        case 'addall': // library btns Add, Add and play
			$.pnotify({
                title: 'Added to playlist',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;
			
		case 'addallreplaceplay': // library btn Add, replace and play
			$.pnotify({
				title: 'Added, Playlist replaced',
				text: msg,
				icon: 'icon-ok',
				delay: 2000,
				opacity: .9,
				history:false
			});
			break;

		case 'update':
			$.pnotify({
				title: 'Update path: ',
				text: msg,
				icon: 'icon-ok',
				delay: 2000,
				opacity: .9,
				history:false
			});
			break;
		
		case 'remove':
			$.pnotify({
				title: 'Removed from playlist',
				text: msg,
				icon: 'icon-ok',
				delay: 2000,
				opacity: .9,
				history:false
			});
			break;

		case 'move':
			$.pnotify({
				title: 'Playlist items moved',
				text: msg,
				icon: 'icon-ok',
				delay: 2000,
				opacity: .9,
				history:false
			});
			break;

        case 'savepl':
        	$.pnotify({
                title: 'Playlist saved',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
			});
			break;

        case 'needplname':
			$.pnotify({
                title: 'Enter a name',
                text: msg,
                icon: 'icon-info-sign',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;

		// TC (Tim Curtis) 2014-09-17
		// - delete saved playlist (Browse panel)
		// - play all songs in list (Library panel)
		// - add all songs in list (Library panel)
		// - clear playlist, add all songs in list (Library panel)
		// TC (Tim Curtis) 2014-12-23
		// - increase timeout from 1000 to 2000 ms for add, update, delete msgs 
        case 'deletesavedpl':
			$.pnotify({
                title: 'Playlist deleted',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;
			
		// TC (Tim Curtis) 2014-11-30
		// - delete radio station (Browse panel)
		case 'deleteradiostn':
			$.pnotify({
                title: 'Radio station deleted',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;

		// TC (Tim Curtis) 2014-12-23
		// - add and edit radio station (Browse panel)
		// - update clock radio
		case 'addradiostn':
			$.pnotify({
                title: 'Radio station added',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;

		case 'updateradiostn':
			$.pnotify({
                title: 'Radio station updated',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;

		case 'updateclockradio':
			$.pnotify({
                title: 'Clock radio updated',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;

		case 'updatetcmodsconf':
			$.pnotify({
                title: 'Custom config updated',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;

		// TC (Tim Curtis) 2015-05-30: change text to reflect new option for refreshing web page
		case 'themechange':
			$.pnotify({
                title: 'Theme color changed, select Menu/Refresh to activate',
                text: msg,
                icon: 'icon-ok',
				delay: 2000,
                opacity: .9,
				history:false
            });
			break;
	}
}
