<?php

/**
 * @filesource plugins/generic/betterPassword/classes/BadpwFailedLoginsDAO.inc.php
 *
 * @class BadpwFailedLoginsDAO
 * @brief Database operations with the BadpwFailedLogins 
 */

import('lib.pkp.classes.db.DAO');
import('plugins.generic.betterPassword.classes.BadpwFailedLogins');

class BadpwFailedLoginsDAO extends DAO{
	
	/**
	 * Insert a BadpwFailedLogins Object into DB
	 * @param object $badpwObj Object of BadpwFailedLogins
	 * @return boolean true if successfully inserted into DB
	 */
	public function insertObject($badpwObj) {
		$this->update('INSERT INTO badpw_failedlogins'
				. '(username,count,failed_login_time)'
				. 'VALUES (?,?,NOW())',array($badpwObj->getUsername(), $badpwObj->getCount()));
		return true;
	}
	
	/**
	 * Increment count and update the failed login time
	 * @param object $badpwObj Object of BadpwFailedLogins of which the count and last failed login time has to be updated
	 * @return boolean True if successfully updated in the DB
	 */
	public function incCount($badpwObj) {
		return $this->update('UPDATE badpw_failedlogins'
				. ' SET count=count+1,failed_login_time=NOW()'
				. ' WHERE username=?', $badpwObj->getUsername());
	}
	
	/**
	 * Delete an Object
	 * @param object $badpwObj Object of BadpwFailedLogins
	 * @return boolean True if successfully deleted
	 */
	public function deleteObject($badpwObj) {
		$this->update('DELETE FROM badpw_failedlogins'
				. ' WHERE username=?', $badpwObj->getUsername());
		return true;
	}
	
	/**
	 * Get BadpwFailedLogins by username
	 * @param string $username The username to search the DB with
	 * @return object BadpwFailedLogins object matching the username
	 */
	public function getByUsername($username) {
		$result = $this->retrieve('SELECT * FROM badpw_failedlogins'
				. ' WHERE username=?', $username);
		$badpwObj = null;
		$row = $result->GetRowAssoc(false);
		if($result->RowCount() !=0) {
			$badpwObj = new BadpwFailedLogins($row['username'], $row['count'], $row['failed_login_time']);
		} else {
			$badpwObj = new BadpwFailedLogins($username, 0, date('Y-m-d H:i:s'));
			$this->insertObject($badpwObj);
		}
		return $badpwObj;
	}
}
