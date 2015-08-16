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

// DEV debug parameters
// ini_set('display_errors',1);
// error_reporting(E_ERROR);
error_reporting(E_ALL ^ E_NOTICE);

define('ROOTPATH', '/var/www' );
define('DAEMONIP', 'localhost');

define ('COMMENTCHAR','#');
define ('DELSTRING','');
define ('CONFPATH', ROOTPATH.'inc/mpd.conf');
define ('DEFCONFPATH', ROOTPATH.'inc/mpd.conf.default');
define ('NETCONFPATH', ROOTPATH.'inc/network/interfaces');
define ('NETCONFPATHMANUAL', ROOTPATH.'inc/network/interfaces.manual');
define ('NETCONFPATHAUTO', ROOTPATH.'inc/network/interfaces.dhcp');
define ('NETCONFPATHBOOT', ROOTPATH.'inc/network/interfaces.loadatboot');

$db = 'sqlite:' . ROOTPATH . '/db/player.db';
