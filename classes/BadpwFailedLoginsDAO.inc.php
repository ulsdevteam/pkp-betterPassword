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
		return $this->update('
			INSERT INTO badpw_failedlogins (username, count, failed_login_time)
			VALUES (?, ?, ?)
		', [$badpwObj->getUsername(), $badpwObj->getCount(), $this->convertToDB($badpwObj->getFailedTime(), 'date')]);
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
		', $badpwObj->getUsername());
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
		', $badpwObj->getUsername());
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
		', $username);
		$row = $result->GetRowAssoc(false);
		if ($result->RowCount()) {
			return new BadpwFailedLogins($row['username'], $row['count'], strtotime($row['failed_login_time']));
		}
		/** @var UserDao */
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getByUsername($username);
		if ($user) {
			$badpwObj = new BadpwFailedLogins($username, 0, time());
			$this->_insertObject($badpwObj);
			return $badpwObj;
		}
	}

	/**
	 * Reset the count of the bad logins to 0
	 * @param BadpwFailedLogins Object $badpwObj BadpwFailedLogins Object to reset count of
	 * @return boolean True if reset is successful
	 */
	public function resetCount(BadpwFailedLogins $badpwObj) : bool {
		return $this->update('
			UPDATE badpw_failedlogins
			SET count = 0
			WHERE username = ?
		', $badpwObj->getUsername());
	}
}
