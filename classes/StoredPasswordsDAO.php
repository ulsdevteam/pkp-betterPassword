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

class StoredPasswordsDAO extends SchemaDAO {
	public function getMostRecent(StoredPasswords $storedPasswordObj) {
		$result = $this->retrieve('
			SELECT *
			FROM stored_passwords
			WHERE last_change_time = ?
		', [(int)$lastChangeTime]);
		return $result;
	}
}
