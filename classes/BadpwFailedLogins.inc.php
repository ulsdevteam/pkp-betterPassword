<?php

/**
 * @filesource plugins/generic/betterPassword/classes/BadpwFailedLogins.inc.php
 * Description of BadpwFailedLogins
 *
 * @class BadpwFailedLogins
 * @brief Container for badPassword failed logins 
 */
class BadpwFailedLogins extends DataObject{
	
	public $username, $count, $lastLoginTime;	
	/**
	 * Constructor
	 * @return BadpwFailedLogins
	 */
	
	public function __construct($username, $count, $lastLoginTime) {
		parent::__construct();
		$this->username = $username;
		$this->count = $count;
		$this->lastLoginTime = $lastLoginTime;
	}
	
	/**
	 * Get the username
	 */
	public function getUsername() {
		return $this->username;
	}
	
	/**
	 * Get the count of bad login attempts
	 */
	public function getCount() {
		return $this->count;
	}
	
	/**
	 * Get the time of last failed login attempt
	 */
	public function getFailedTime() {
		return $this->lastLoginTime;
	}
}
