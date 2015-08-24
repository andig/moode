<?php
/*
 *      PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 *		 Tsunamp Team
 *      http://www.tsunamp.com
 *
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
 * Rewrite by Tim Curtis and Andreas Goetz
 */

// DEV parameters
ini_set('display_errors', 1);
error_reporting(E_NOTICE);
error_reporting(E_ALL);
// error_reporting(E_ALL ^ E_NOTICE);

define('TCMODS_RELEASE', 'r21'); 			// Current release

define('ROOTPATH', '/var/www' );
define('MPD_HOST', '127.0.0.1');

define('MPD_LIB', '/var/lib/mpd/music/');	// include trailing /

define('CONFIG_DB', 'sqlite:' . ROOTPATH . '/db/player.db');
