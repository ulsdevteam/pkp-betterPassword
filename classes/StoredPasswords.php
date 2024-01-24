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
        /** @var \DateTime Timestamp of last login */
        private $_lastChangeTime;

        	/**
	 * Constructor
	 * @param string $username Username
	 * @param int $count Number of bad password login count
	 * @param int $lastLoginTime The time of the last bad login attempt
	 */
	public function __construct(int $userId, string $password, \DateTime $lastChangeTime) {
		parent::__construct();
		$this->_data["id"] = $userId;
		$this->_data["password"] = $password;
		$timeString = $lastChangeTime->format("Y-m-d H:i:s");
		$this->_data["lastChangeTime"] = $timeString;
	}

	/**
	 * Get the user Id
	 * @return int The user Id
	 */
	public function getUserId() : int {
		return $this->_data["id"];
	}

	/**
	 * Get the password
	 * @return string The users password
	 */
	public function getPassword() : string {
		return $this->_data["password"];
	}

	/**
	 * Get the time of last password change
	 * @return int The time of the last password change
	 */
	public function getChangeTime() : \DateTime {
		$tempDateTime = new \DateTime($this->_data["lastChangeTime"]);
		return $tempDateTime;
	}

	public function setChangeTime(\DateTime $lastChangeTime) : void{
		$timeString = $lastChangeTime->format("Y-m-d H:i:s");
		$this->_data["lastChangeTime"] = $timeString;
	}

	public function getPasswords() : array{
		$allPasswords = explode(',', $this->_data["password"]);
		return $allPasswords;
	}

	public function setPasswords($passwords) : void{
		$this->_data["password"] = implode(',', $passwords);
	}
}


