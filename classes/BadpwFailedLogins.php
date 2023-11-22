<?php

/**
 * @file plugins/generic/betterPassword/classes/BadpwFailedLogins.inc.php
 * Description of BadpwFailedLogins
 *
 * @class BadpwFailedLogins
 * @brief Container for badPassword failed logins
 */
namespace APP\plugins\generic\betterPassword\classes;
use PKP\core\DataObject;

class BadpwFailedLogins extends DataObject {
	/** @var string Username */
	private $_username;
	/** @var int Amount of failed logins */
	private $_count;
	/** @var int Timestamp of last login */
	private $_lastLoginTime;

	/**
	 * Constructor
	 * @param string $username Username
	 * @param int $count Number of bad password login count
	 * @param int $lastLoginTime The time of the last bad login attempt
	 */
	public function __construct(string $username, int $count, int $lastLoginTime) {
		parent::__construct();
		$this->_username = $username;
		$this->_count = $count;
		$this->_lastLoginTime = $lastLoginTime;
	}

	/**
	 * Get the username
	 * @return string The username
	 */
	public function getUsername() : string {
		return $this->_username;
	}

	/**
	 * Get the count of bad login attempts
	 * @return int Number of bad password login count
	 */
	public function getCount() : int {
		return $this->_count;
	}

	/**
	 * Get the time of last failed login attempt
	 * @return int The time of the last bad login attempt
	 */
	public function getFailedTime() : int {
		return $this->_lastLoginTime;
	}
}
