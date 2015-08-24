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
 * Session handling
 *
 * Support session sharing between frontend and backend processes as workaround for https://bugs.php.net/bug.php?id=69582
 */
class Session
{
	const SESSION_ID = 's9vk3f64p13hg9iour09a3jqs3';
	const SESSION_ADMIN_USER = 'www-data';

	static $pre_session_uid = null;

	/**
	 * Open session, adjust UID if required
	 */
	public static function open($admin = false) {
		if (PHP_SESSION_ACTIVE == session_status()) {
			throw new \LogicException('Session already open');
		}

		// set effective uid of session owner
		if ($admin) {
			static::$pre_session_uid = posix_getuid();
			posix_seteuid(posix_getpwnam(static::SESSION_ADMIN_USER)['uid']);
		}

		// tie all users to single session
		session_id(static::SESSION_ID);

		if (false === session_start()) {
			throw new \RuntimeException('Could not start session');
		}

		// update sesson with current configuration
		// TODO check if necessary
		ConfigDB::connect();
		foreach (ConfigDB::read('cfg_engine') as $row) {
			$_SESSION[$row['param']] = $row['value'];
		}
	}

	/**
	 * Update session and config table
	 */
	public static function update($key, $val) {
		$_SESSION[$var] = $value;
		ConfigDB::connect();
		ConfigDB::update('cfg_engine', $key, $val);
	}

	/**
	 * Destroy session data
	 */
	public static function close() {
		session_unset();
		session_destroy();
	}

	/**
	 * Close session and return to previous uid
	 */
	public static function close() {
		session_write_close();

		// change uid back
		if (null !== static::$pre_session_uid) {
			posix_seteuid(static::$pre_session_uid);
			static::$pre_session_uid = null;
		}
	}

	/**
	 * Warp callable in session access
	 */
	public static function wrap($callable, $admin = false) {
		if (!is_callable($callable)) {
			throw new \RuntimeException('Not a callable ' . (string)$callable);
		}

		// run callable inside session
		static::open($admin);
		$callable();
		static::close();
	}
}
