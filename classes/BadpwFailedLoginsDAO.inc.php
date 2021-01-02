<?php

/**
 * @filesource plugins/generic/betterPassword/classes/BadpwFailedLoginsDAO.inc.php
 *
 * @class BadpwFailedLoginsDAO
 * @brief Database operations with the BadpwFailedLogins 
 */

import('lib.pkp.classes.db.DAO');
import('plugins.generic.betterPassword.classes.BadpwFailedLogins');

class BadpwFailedLoginsDAO extends DAO {

	/**
	 * Insert a BadpwFailedLogins Object into DB
	 * @param BadpwFailedLogins object $badpwObj Object of BadpwFailedLogins
	 * @return boolean true if successfully inserted into DB
	 */
	private function _insertObject($badpwObj) {
		$type = 'date';
		return $this->update('INSERT INTO badpw_failedlogins'
				. '(username, count, failed_login_time, last_password_update, passwords)'
				. 'VALUES (?, ?, ?, ?, ?)', [$badpwObj->getUsername(), $badpwObj->getCount(), $this->convertToDB($badpwObj->getFailedTime(), $type), $this->convertToDB($badpwObj->getLastPasswordUpdate(), $type), json_encode($badpwObj->getPasswords())]);
	}

	/**
	 * Increment count and update the failed login time
	 * @param BadpwFailedLogins object $badpwObj Object of BadpwFailedLogins of which the count and last failed login time has to be updated
	 * @return boolean True if successfully updated in the DB
	 */
	public function incCount($badpwObj) {
		return $this->update('UPDATE badpw_failedlogins'
				. ' SET count = count + 1,failed_login_time = NOW()'
				. ' WHERE username=?', $badpwObj->getUsername());
	}

	/**
	 * Logs a new user password
	 * @param BadpwFailedLogins object $badpwObj Object of BadpwFailedLogins of which the last used password has to be updated
	 * @return boolean True if successfully updated in the DB
	 */
	public function updatePassword($badpwObj, $password, $maxPasswords = null) {
		$passwords = $badpwObj->getPasswords($maxPasswords ? $maxPasswords - 1 : null);
		array_unshift($passwords, $password);
		return $this->update('
			UPDATE badpw_failedlogins
			SET
				last_password_update = CURRENT_TIMESTAMP,
				passwords = ?
			WHERE username = ?
		', [json_encode($passwords), $badpwObj->getUsername()]);
	}

	/**
	 * Delete an Object
	 * @param BadpwFailedLogins object $badpwObj Object of BadpwFailedLogins
	 * @return boolean True if successfully deleted
	 */
	public function deleteObject($badpwObj) {
		return $this->update('DELETE FROM badpw_failedlogins'
				. ' WHERE username=?', $badpwObj->getUsername());
	}

	/**
	 * Get BadpwFailedLogins by username
	 * @param string $username The username to search the DB with
	 * @return BadpwFailedLogins object Object matching the username
	 */
	public function getByUsername($username) {
		$result = $this->retrieve('SELECT * FROM badpw_failedlogins'
				. ' WHERE username=?', $username);
		$badpwObj = null;
		$row = $result->GetRowAssoc(false);
		if ($result->RowCount() != 0) {
			$badpwObj = new BadpwFailedLogins($row['username'], $row['count'], strtotime($row['failed_login_time']), json_decode($row['passwords']) ?? [], strtotime($row['last_password_update']));
		} else {
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getByUsername($username);
			if ($user) {
				$badpwObj = new BadpwFailedLogins($username, 0, time(), $user ? [$user->getPassword()] : [], ($user ? strtotime($user->getDateRegistered()) : time()));
				$this->_insertObject($badpwObj);
			}
		}
		return $badpwObj;
	}

	/**
	 * Reset the count of the bad logins to 0
	 * @param BadpwFailedLogins Object $badpwObj BadpwFailedLogins Object to reset count of
	 * @return boolean True if reset is successful
	 */
	public function resetCount($badpwObj) {
		return $this->update('UPDATE badpw_failedlogins'
				. ' SET count=0'
				. ' WHERE username=?', $badpwObj->getUsername());
	}
}
