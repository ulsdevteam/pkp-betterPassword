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
	private function insertObject($badpwObj) {
		return $this->update('INSERT INTO badpw_failedlogins'
				. '(username,count,failed_login_time)'
				. 'VALUES (?,?,?)',array($badpwObj->getUsername(), $badpwObj->getCount(), $badpwObj->getFailedTime()));
	}
	
	/**
	 * Increment count and update the failed login time
	 * @param BadpwFailedLogins object $badpwObj Object of BadpwFailedLogins of which the count and last failed login time has to be updated
	 * @return boolean True if successfully updated in the DB
	 */
	public function incCount($badpwObj) {
		return $this->update('UPDATE badpw_failedlogins'
				. ' SET count=count+1,failed_login_time=NOW()'
				. ' WHERE username=?', $badpwObj->getUsername());
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
		if($result->RowCount() !=0) {
			$badpwObj = new BadpwFailedLogins($row['username'], $row['count'], strtotime($row['failed_login_time']));
		} else {
			$this->insertUserRecord($username, 0, time());
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

	/**
	 * Gets the userIds of all the users that have incorrect login attempts
	 * @return array UserIds of all the users that have bad password counts in the user table
	 */
	function getUserIdsBySetting() {
		$result = $this->retrieve(
			'SELECT u.user_id FROM users u JOIN user_settings us ON (u.user_id = us.user_id) WHERE us.setting_name = ?',
			array('betterpasswordplugin::badPasswordCount')
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			
			$returner =& $result->GetAll();
		}
		$result->Close();
		return $returner;
	}
	
	/*
	 * Insert a bad login record into the DB
	 * @param string $username Username
	 * @param int $count Number of bad login count
	 * @param int $time Time of last bad login attempt
	 * @return boolean True if obejct is inserted into the DB
	 */
	function insertUserRecord($username, $count, $time) {
		$badpwObj = new BadpwFailedLogins($username, $count, $time);
		return $this->insertObject($badpwObj);
	}
	
	/**
	 * 
	 * @param string $username Username for the user with bad login attempts
	 * @return boolean True if a user exists in the table with that username
	 */
	function userExistsByUsername($username) {
		$result = $this->retrieve(
				'SELECT COUNT(*) FROM badpw_failedlogins WHERE username = ?',
				$username
				);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;
		$result->Close();
		return $returner;
	}
}
