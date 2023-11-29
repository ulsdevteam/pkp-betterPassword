<?php

/**
 * @file plugins/generic/betterPassword/classes/StoredPasswords.php
 * Description of StoredPasswords
 *
 * @class StoredPasswords
 * 
 */
namespace APP\plugins\generic\betterPassword\classes;
use PKP\core\DataObject;

class StoredPasswords extends DataObject {
		/** @var int UserId */
        private $_userId;
        /** @var string Password */
        private $_password;
        /** @var int Timestamp of last login */
        private $_lastChangeTime;

        	/**
	 * Constructor
	 * @param string $username Username
	 * @param int $count Number of bad password login count
	 * @param int $lastLoginTime The time of the last bad login attempt
	 */
	public function __construct(int $userId, string $password, int $lastChangeTime) {
		parent::__construct();
		$this->_userId = $userId;
		$this->_password = $password;
		$this->_lastChangeTime = $lastChangeTime;
	}

	/**
	 * Get the user Id
	 * @return int The user Id
	 */
	public function getUserId() : int {
		return $this->_userId;
	}

	/**
	 * Get the password
	 * @return string The users password
	 */
	public function getPassword() : string {
		return $this->_password;
	}

	/**
	 * Get the time of last password change
	 * @return int The time of the last password change
	 */
	public function getChangeTime() : int {
		return $this->_lastChangeTime;
	}
}


