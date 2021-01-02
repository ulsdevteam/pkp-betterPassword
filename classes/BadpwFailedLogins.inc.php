<?php

/**
 * @filesource plugins/generic/betterPassword/classes/BadpwFailedLogins.inc.php
 * Description of BadpwFailedLogins
 *
 * @class BadpwFailedLogins
 * @brief Container for badPassword failed logins 
 */
class BadpwFailedLogins extends DataObject {

	private $username, $count, $lastLoginTime, $passwords;

	/**
	 * Constructor
	 * @param string $username Username
	 * @param int $count Number of bad password login count
	 * @param int $lastLoginTime The time of the last bad login attempt
	 * @param array $passwords The list of last used passwords
	 * @param int $lastPasswordUpdate The time of the last password update
	 */
	public function __construct($username, $count, $lastLoginTime, $passwords, $lastPasswordUpdate) {
		parent::__construct();
		$this->username = filter_var($username, FILTER_SANITIZE_STRING);
		$this->count = filter_var($count, FILTER_SANITIZE_NUMBER_INT);
		$this->lastLoginTime = filter_var($lastLoginTime, FILTER_SANITIZE_NUMBER_INT);
		$this->passwords = is_array($passwords) ? $passwords : [];
		$this->lastPasswordUpdate = filter_var($lastPasswordUpdate, FILTER_SANITIZE_NUMBER_INT);
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

	/**
	 * Get the list of passwords
	 * @param int $limit Optionally limit the amount of passwords (newest first)
	 * @return int The time of the last bad login attempt
	 */
	public function getPasswords($limit = null) {
		return $limit ? array_slice($this->passwords, 0, $limit) : $this->passwords;
	}

	/**
	 * Get the time of the last password update
	 * @return int The time of the last password update
	 */
	public function getLastPasswordUpdate() {
		return $this->lastPasswordUpdate;
	}
}
