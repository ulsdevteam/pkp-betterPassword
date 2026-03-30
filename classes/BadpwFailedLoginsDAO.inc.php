<?php

/**
 * @file plugins/generic/betterPassword/classes/BadpwFailedLoginsDAO.inc.php
 *
 * @class BadpwFailedLoginsDAO
 * @brief Database operations with the BadpwFailedLogins
 */
import('lib.pkp.classes.db.DAO');
import('plugins.generic.betterPassword.classes.BadpwFailedLogins');

class BadpwFailedLoginsDAO extends DAO {
	/**
	 * Insert a BadpwFailedLogins Object into DB
	 * @param BadpwFailedLogins BadpwFailedLogins $badpwObj Object of BadpwFailedLogins
	 * @return boolean true if successfully inserted into DB
	 */
	private function _insertObject(BadpwFailedLogins $badpwObj) : bool {
		$type = 'date';
		return $this->update('
			INSERT INTO badpw_failedlogins (username, count, failed_login_time)
			VALUES (?, ?, ?)
		', [$badpwObj->getUsername(), $badpwObj->getCount(), $this->convertToDB($badpwObj->getFailedTime(), $type)]);
	}

	/**
	 * Increment count and update the failed login time
	 * @param BadpwFailedLogins BadpwFailedLogins $badpwObj Object of BadpwFailedLogins of which the count and last failed login time has to be updated
	 * @return boolean True if successfully updated in the DB
	 */
	public function incCount(BadpwFailedLogins $badpwObj) : bool {
		return $this->update('
			UPDATE badpw_failedlogins
			SET
				count = count + 1,
				failed_login_time = CURRENT_TIMESTAMP
			WHERE username = ?
		', [$badpwObj->getUsername()]);
	}

	/**
	 * Delete an Object
	 * @param BadpwFailedLogins BadpwFailedLogins $badpwObj Object of BadpwFailedLogins
	 * @return boolean True if successfully deleted
	 */
	public function deleteObject(BadpwFailedLogins $badpwObj) : bool {
		return $this->update('
			DELETE FROM badpw_failedlogins
			WHERE username = ?
		', [$badpwObj->getUsername()]);
	}

	/**
	 * Get BadpwFailedLogins by username
	 * @param string $username The username to search the DB with
	 * @return BadpwFailedLogins object Object matching the username
	 */
	public function getByUsername(string $username) : ?BadpwFailedLogins {
		$result = $this->retrieve('
			SELECT *
			FROM badpw_failedlogins
			WHERE username = ?
		', [(string)$username]);

		$row = (array)$result->current();

		//User already in database
		if (count($row)>0) {
			return new BadpwFailedLogins((string)$row['username'], (int)$row['count'], (int)strtotime($row['failed_login_time']));
		}

		//Unknown user, add to db before returning object
		$badpwObj = new BadpwFailedLogins($username, 0, time());
		$this->_insertObject($badpwObj);
		return $badpwObj;
	}

	/**
	 * Reset the count of the bad logins to 0
	 * @param BadpwFailedLogins Object $badpwObj BadpwFailedLogins Object to reset count of
	 * @return boolean True if reset is successful
	 */
	public function resetCount(BadpwFailedLogins $badpwObj) : bool {
		return $this->update('DELETE FROM badpw_failedlogins WHERE username = ?', [$badpwObj->getUsername()]);
	}

	/**
	 * Cleanup stale failed login attempts.
	 *
	 * Rules:
	 * - If the account is locked (count > $maxRetries), then `failed_login_time` must be old enough
	 *   to satisfy both `$lockExpiresSeconds` and `$lockSeconds`.
	 * - Otherwise, only `$lockExpiresSeconds` must be respected.
	 *
	 * @param int $maxRetries Maximum amount of retries before an account is considered locked
	 * @param int $lockSeconds Lock duration (seconds)
	 * @param int $lockExpiresSeconds How long we keep bad-password attempts (seconds)
	 */
	public function cleanup(int $maxRetries, int $lockSeconds, int $attemptExpiresSeconds): void {
		$now = time();
		$type = 'date';
		$attemptsExpiresAt = $this->convertToDB($now + $attemptExpiresSeconds, $type);
		$lockExpiresAt = $this->convertToDB($now + max($attemptExpiresSeconds, $lockSeconds), $type);
		$this->update(
			'DELETE FROM badpw_failedlogins WHERE failed_login_time >= CASE WHEN count >= ? THEN ? ELSE ? END',
			[
				$maxRetries,
				$lockExpiresAt,
				$attemptsExpiresAt
			]
		);
	}
}
