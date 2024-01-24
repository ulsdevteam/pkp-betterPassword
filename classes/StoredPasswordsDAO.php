<?php

/**
 * @file plugins/generic/betterPassword/classes/StoredPasswordsDAO.php
 *
 * @class StoredPasswordsDAO
 * @brief Database operations with the StoredPasswords
 */
namespace APP\plugins\generic\betterPassword\classes;

use PKP\db\SchemaDAO;
use APP\plugins\generic\betterPassword\classes\StoredPasswords as StoredPasswords;

class StoredPasswordsDAO extends SchemaDAO { //got an error about this not being abstract
	    /** @var string This constant is being modeled from PKPSchemaService following 
		 * their naming conventions
		*/
		public $schemaName = 'storedPassword';

		/** @copydoc SchemaDAO::$tableName */
		public $tableName = 'stored_passwords';
	
		/** @copydoc SchemaDAO::$primaryKeyColumn */
		public $primaryKeyColumn = 'user_id';
	
		/** @var array Maps schema properties for the primary table to their column names */
		public $primaryTableColumns = [
			'id' => 'user_id',
			'password' => 'password',
			'lastChangeTime' => 'last_change_time'
		];
	
	public function newDataObject() {
		return new StoredPasswords();
	}
	
	public function placeholder(int $user_id, string $password = '', \DateTime $last_change_time = new \DateTime ('now')) : ?StoredPasswords { //confirm these statements
		/*$result = $this->retrieve('
			SELECT *
			FROM stored_passwords
			WHERE user_id = ?
		', [(int)$user_id]);*/
		return new StoredPasswords($user_id, $password, $last_change_time); //possibly alter $last_change_time to be fetched from database
		//$time = new \DateTime ($field['last_change_time']);
/* Below here may be unneccesary
		if (count($field)>0) { //this count isn't correct find a replacement
			$time = new \DateTime ($field['last_change_time']);
			return new StoredPasswords($field['user_id'], $field['password'], $time);
		}
		else {
			$time = new \DateTime ('now');
			$storedPasswords = new StoredPasswords($user_id, '', $time); //ask about leaving password as ''
			$this->updateDate($storedPasswords); //created 2 field, confirm only one of these are neccesary
			//$this->updatePasswords($storedPasswords);
			return $storedPasswords;
		}
		*/
	}

	public function getMostRecent(StoredPasswords $storedPasswords) { //may want to change name to specify date
		$result = $this->retrieve('
			SELECT MAX(last_change_time)
			FROM stored_passwords
			WHERE user_id = ?
		', [$storedPasswords->getUserId()]);
		$mostRecent = (array) $result->current(); //added this into Mysql	INSERT INTO stored_passwords Values (1,'turtlesaregreat',now());
		return $mostRecent['MAX(last_change_time)'];
	}
	
	public function updateDate(StoredPasswords $storedPasswords) : bool {
		if($storedPasswords->getUserId() == null) { //confirm this
			return $this->update('
				INSERT INTO stored_passwords
					(user_id, password, last_change_time)
				VALUES
					(?, "", NOW())
				ON DUPLICATE KEY UPDATE
					last_change_time = NOW()
			', [$storedPasswords->getUserId()]); //may need to be time
		}
		else {
			return $this->update('
				UPDATE stored_passwords
				SET
					last_change_time = NOW()
				WHERE user_id = ?
			', [$storedPasswords->getUserId()]); //may need to be time
		}
	}

	public function getMostRecentPasswords(StoredPasswords $storedPasswords) { //may need to adjust name to passwords, instead of just $user_id use all of StoredPasswords as a variable to access all needed values
		$result = $this->retrieve('
			SELECT password
			FROM stored_passwords
			WHERE user_id = ?
		', [$storedPasswords->getUserId()]); //this fills in for question marks
		$mostRecentPasswords = (array) $result->current(); //change to passwords, and seperate the passwords from this array
		//seperate the passwords from the array here into their own variable
		//use explode to seperate on a ,
		$mostRecentPasswords = explode(',', $mostRecentPasswords['password']);
		//use implode in limitreuse to rejoin them together when calling for an update
		//use update or similar to put new data back into the table
		//array shift and array push in limit reuse 
		//NOTE TODO figure out why passwords/dates aren't all stored in one cell when happening
		return $mostRecentPasswords; //check this return value
	}

	public function updatePasswords(StoredPasswords $storedPasswords) : bool {
		if($storedPasswords->getPassword() == null || $storedPasswords->getPassword() == '') { //see if this method of checking the password is right
			return $this->update('
				INSERT INTO stored_passwords
					(user_id, password, last_change_time)
				VALUES
					(?, ?, NOW())
				ON DUPLICATE KEY UPDATE
					last_change_time = NOW()
			', [$storedPasswords->getUserId(), $storedPasswords->getPassword()]);
		}
		else {
			return $this->update('
				UPDATE stored_passwords
				SET
					password = CONCAT(password, ?)
					password = password + ", " + ?
				WHERE user_id = ?
			', [$storedPasswords->getPassword(), $storedPasswords->getUserId()]);
		} //check above concat, or find new replacement
	}
	
	//repeat above method for password to set password, have the empty string be 
	//make a method to update the password, you can check if it exists in limit reuse by getting the password from here like above with most recent time, in limit reuse since you have the password add 
	
	public function addPasswords(StoredPasswords $storedPasswords) : bool { //made to delete passwords after a threshold given in limitreuse
		
	}

	public function deletePasswords(StoredPasswords $storedPasswords) : bool { //made to delete passwords after a threshold given in limitreuse
		
	}
}

//TODO look at updateObject from SchemaDAO, it should allow us to replace most of this. It can be called directly from SchemaDAO without any of this above
