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
error_reporting(E_ALL);
// PRD parameters
// error_reporting(E_ALL ^ E_NOTICE);
// ini_set('display_errors', 0);

define('MOODE_RELEASE', 'r21'); 			// Current release

define('MOODE_ROOT_PATH', '/var/www' );
define('MOODE_CONFIG_DB', 'sqlite:' . MOODE_ROOT_PATH . '/db/player.db');

define('MPD_HOST', '127.0.0.1');			// localhost
define('MPD_TIMEOUT', 5);					// connection timeout
define('MPD_LIB', '/var/lib/mpd/music/');	// include trailing /
