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
	 * @param string $password The user's password list
	 * @param \DateTime $last_change_time Time of the last password change
	 * @return StoredPasswords New StoredPasswords object
	 */
	public function newDataObjects (int $user_id, string $password = '', \DateTime $last_change_time = new \DateTime ('now')) : ?StoredPasswords {
		$storedPasswords = $this->newDataObject();
		$storedPasswords->setUserId($user_id);
		$storedPasswords->setPassword($password);
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
	
	/**
	 * Gets the most recent time a user's password was changed from the database
	 * @param StoredPasswords $storedPasswords StoredPasswords object
	 * @return string $mostRecent['MAX(last_change_time)'] Time of last password change
	 */
	public function getMostRecent(StoredPasswords $storedPasswords) {
		$result = $this->retrieve('
			SELECT MAX(last_change_time)
			FROM stored_passwords
			WHERE user_id = ?
		', [$storedPasswords->getUserId()]);
		$mostRecent = (array) $result->current();
		return $mostRecent['MAX(last_change_time)'];
	}

	/** @copydoc DAO::_retrieve */
	public function retrieve($sql, $params = [], $callHooks = true)
	{
		if ($callHooks === true) {
			$trace = debug_backtrace();
			// Call hooks based on the calling entity, assuming
			// this method is only called by a subclass. Results
			// in hook calls named e.g. "sessiondao::_getsession"
			// (always lower case).
			$value = null;
			if (Hook::run(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']), [&$sql, &$params, &$value])) {
				return $value;
			}
		}
		return DB::cursor(DB::raw($sql)->getValue(), $params);
	}

}
