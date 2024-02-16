<?php

/**
 * @file plugins/generic/betterPassword/classes/StoredPasswordsDAO.php
 *
 * @class StoredPasswordsDAO
 * @brief Database operations with the StoredPasswords
 */
namespace APP\plugins\generic\betterPassword\classes;

use PKP\core\EntityDAO;
use APP\plugins\generic\betterPassword\classes\StoredPasswords as StoredPasswords;
use PKP\plugins\Hook;
use Illuminate\Support\Facades\DB;

class StoredPasswordsDAO extends EntityDAO {
	/** @copydoc EntityDAO::$schema */
	public $schema = 'storedPassword';

	/** @copydoc EntityDAO::$schemaName */
	public $schemaName = 'storedPassword';

	/** @copydoc EntityDAO::$table */
	public $table = 'stored_passwords';
	
	/** @copydoc EntityDAO::$primaryKeyColumn */
	public $primaryKeyColumn = 'id';
	
	/** @copydoc EntityDAO::$primaryTableColumn */
	public $primaryTableColumns = [
		'id' => 'id',
		'user_id' => 'user_id',
		'password' => 'password',
		'lastChangeTime' => 'last_change_time'
	];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(new \PKP\services\PKPSchemaService());
	}

	/**
	 * Overrides EntityDao's newDataObject()
	 * @return StoredPasswords New StoredPasswords object
	 */
	public function newDataObject () {
		return new StoredPasswords();
	}

	/**
	 * Create a new Stored Passwords object
	 * @param int $user_id The user's user id
	 * @param array $password The user's password list
	 * @param \DateTime $last_change_time Time of the last password change
	 * @return StoredPasswords New StoredPasswords object
	 */
	public function newDataObjects (int $user_id, array $password = array(''), \DateTime $last_change_time = new \DateTime ('now')) : ?StoredPasswords {
		$storedPasswords = $this->newDataObject();
		$storedPasswords->setUserId($user_id);
		$storedPasswords->setPasswords($password);
		$storedPasswords->setChangeTime($last_change_time);
		return $storedPasswords;
	}

	/**
	 * Get a StoredPasswords object based on user id
	 * @param int $user_id The user's user id
	 * @return StoredPasswords StoredPasswords object
	 */
	public function getByUserId(int $userId): ?StoredPasswords
	{
		$row = DB::table('stored_passwords')
			->where('user_id', '=', $userId)
			->first();
		return $row ? $this->fromRow($row) : null;
	}

	/** @copydoc EntityDAO::_insert */
	public function insert($object) {
		return $this->_insert($object);
	}

	/** @copydoc EntityDAO::_update */
	public function update($object) {
		$this->_update($object);
	}

}
