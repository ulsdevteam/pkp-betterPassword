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
	 * Get the user Id
	 * @return int The user's Id
	 */
	public function getUserId() : ?int {
		return $this->_data["user_id"];
	}

	/**
	 * Set the user Id
	 * @param int $user_id The user's id
	 */
	public function setUserId(int $user_id) : void{
		$this->_data["user_id"] = $user_id;
	}

	/**
	 * Get the user's password list
	 * @return string The users password list
	 */
	public function getPassword() : string {
		return $this->_data["password"];
	}

	/**
	 * Set the user's password list
	 * @param string $password The user's password list
	 * @param bool $updateTime Whether the user should update their lastChangeTime
	 */
	public function setPassword(string $password, $updateTime = false) : void{
		$this->_data["password"] = $password;
		if ($updateTime) {
			$this->setChangeTime(new \DateTime ('now'));
		}
	}

	/**
	 * Get the time of user's last password change
	 * @return \DateTime The time of the last password change
	 */
	public function getChangeTime() : \DateTime {
		$tempDateTime = new \DateTime($this->_data["lastChangeTime"]);
		return $tempDateTime;
	}

	/**
	 * Set the time of user's last password change
	 * @param \DateTime $lastChangeTime Time of last password change
	 */
	public function setChangeTime(\DateTime $lastChangeTime) : void{
		$timeString = $lastChangeTime->format("Y-m-d H:i:s");
		$this->_data["lastChangeTime"] = $timeString;
	}

	/**
	 * Get an array of the user's passwords seperated by a comma
	 * @return array Array of user's passwords
	 */
	public function getPasswords() : array{
		return explode(',', $this->getPassword());
	}

	/**
	 * Set the user's list of passwords as a string
	 * @param array $passwords The user's password list in an array
	 * @param bool $updateTime Whether the user should update their lastChangeTime
	 */
	public function setPasswords($passwords, $updateTime = false) : void{
		$this->setPassword(implode(',', $passwords), $updateTime);
	}
}


