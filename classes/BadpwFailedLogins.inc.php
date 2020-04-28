<?php

/**
 * @filesource plugins/generic/betterPassword/classes/BadpwFailedLogins.inc.php
 * Description of BadpwFailedLogins
 *
 * @class BadpwFailedLogins
 * @brief Container for badPassword failed logins 
 */
class BadpwFailedLogins extends DataObject {
	
	private $username, $count, $lastLoginTime;	
	
	/**
	 * Constructor
	 * @param string $username Username
	 * @param int $count Number of bad password login count
	 * @param int $lastLoginTime The time of the last bad login attempt
	 */
	public function __construct($username, $count, $lastLoginTime) {
		parent::__construct();
		$this->username = filter_var($username, FILTER_SANITIZE_STRING);
		$this->count = filter_var($count, FILTER_SANITIZE_NUMBER_INT);
		$this->lastLoginTime = filter_var($lastLoginTime, FILTER_SANITIZE_NUMBER_INT);
	}
	
	/**
	 * Get the username
	 * @return string The username
	 */
	public function getUsername() {
		return $this->username;
	}
	
	/**
	 * Get the count of bad login attempts
	 * @return int Number of bad password login count
	 */
	public function getCount() {
		return $this->count;
	}
	
	/**
	 * Get the time of last failed login attempt
	 * @return int The time of the last bad login attempt
	 */
	public function getFailedTime() {
		return $this->lastLoginTime;
	}
}
