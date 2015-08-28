<?php
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
 * Copyright (c) 2015 Andreas Goetz <cpuidle@gmx.de>
 */

/**
 * Configuration database handling
 */
class ConfigDB
{
	static $dbh;

	/**
	 * Open database
	 */
	public static function connect() {
		// already connected
		if (is_resource(static::$dbh)) {
			return true;
		}

		if (false === (static::$dbh = new PDO(MOODE_CONFIG_DB))) {
			static::disconnect();
			die("Cannot open database " . MOODE_CONFIG_DB);
		}
	}

	/**
	 * Close database
	 */
	public static function disconnect() {
		static::$dbh = null;
	}

	public static function read($table, $param = null, $id = null) {
		$para = array();

		if (null === $param) {
			$querystr = 'SELECT * from ' . $table;
		}
		elseif (null !== $id) {
			$querystr = "SELECT * from " . $table . " WHERE id=?";
			$para[] = $id;
		}
		elseif ($param == 'mpdconf'){
			$querystr = "SELECT param, value_player FROM cfg_mpd WHERE value_player != ''";
		}
		elseif ($param == 'mpdconfdefault') {
			$querystr = "SELECT param, value_default FROM cfg_mpd WHERE value_default != ''";
		}
		elseif ($table == 'cfg_audiodev') {
			$querystr = 'SELECT name, dacchip, arch, iface, other FROM ' . $table . ' WHERE name=?';
			$para[] = $param;
		}
		elseif ($table == 'cfg_radio') {
			$querystr = 'SELECT station, name, logo FROM ' . $table . ' WHERE station=?';
			$para[] = $param;
		}
		else {
			$querystr = 'SELECT value FROM ' . $table . ' WHERE param=?';
			$para[] = $param;
		}

		return static::execute($querystr, $para);
	}

	public static function update($table, $key, $value) {
		switch ($table) {
			case 'cfg_engine':
				$querystr = "UPDATE " . $table . " SET value=? WHERE param=?";
				$para = array($value, $key);
				break;

			case 'cfg_mpd':
				$querystr = "UPDATE " . $table . " SET value_player=? WHERE param=?";
				$para = array($value, $key);
				break;

			case 'cfg_lan':
				$querystr = "UPDATE " . $table . " SET dhcp=?, ip=?, netmask=?, gw=?, dns1=?, dns2=? WHERE name=?";
				$para = array($value['dhcp'], $value['ip'], $value['netmask'], $value['gw'], $value['dns1'], $value['dns2'], $value['name']);
				break;

			case 'cfg_wifisec':
				$querystr = "UPDATE " . $table . " SET ssid=?, security=?, password=? WHERE id=1";
				$para = array($value['ssid'], $value['encryption'], $value['password']);
				break;

			case 'cfg_source':
				$querystr = "UPDATE " . $table . " SET name=?, type=?, address=?, remotedir=?, username=?, password=?, charset=?, rsize=?, wsize=?, options=?, error=? WHERE id=?";
				$para = array($value['name'], $value['type'], $value['address'], $value['remotedir'], $value['username'], $value['password'], $value['charset'], $value['rsize'], $value['wsize'], $value['options'], $value['error'], $value['id']);
				break;
		}

		return static::execute($querystr, $para);
	}

	public static function write($table, $para) {
		$querystr = "INSERT INTO " . $table . " VALUES (" . implode(', ', array_fill(0, count($para), '?')) .")";

		static::execute($querystr, $para);

		return static::$dbh->lastInsertId();
	}

	public static function delete($table, $id = null) {
		$para = array();
		$querystr = "DELETE FROM " . $table;

		if (isset($id)) {
			$querystr .= " WHERE id=?";
			$para[] = $id;
		}

		return static::execute($querystr, $para);
	}

	private static function execute($querystr, $para = null) {
		static::connect();

		$stmt = static::$dbh->prepare($querystr);

		if (!$stmt->execute($para)) {
			return false;
		}

		$result = array();
		foreach ($stmt as $value) {
			$result[] = $value;
		}

		if (empty($result)) {
			return true;
		}

		return $result;
	}
}
