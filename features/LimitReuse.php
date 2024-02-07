<?php

/**
 * @file plugins/generic/betterPassword/features/LimitReuse.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class LimitReuse
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to disable reusing old passwords
 */
namespace APP\plugins\generic\betterPassword\features;

use PKP\plugins\Hook;
use PKP\core\PKPApplication;
use PKP\form\validation\FormValidatorCustom;
use PKP\security\Validation;
use PKP\user\User;
use PKP\user\Repository;
use PKP\db\DAORegistry;
use APP\core\Application;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use APP\facades\Repo;

class LimitReuse {
	/** @var BetterPasswordPlugin */
	private $_plugin;

	/** @var int Max reusable passwords */
	private $_maxReusablePasswords;

	/** @var bool Flag to avoid an infinite loop in the handler */
	private $_handledPasswordUpdate;

	/**
	 * Constructor
	 * @param BetterPasswordPlugin $plugin
	 */
	public function __construct(BetterPasswordPlugin $plugin) {
		$this->_plugin = $plugin;
		$this->_maxReusablePasswords = (int) $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordInvalidationPasswords');
		if (!$this->_maxReusablePasswords) {
			return;
		}

		$userDao = Repo::user()->dao;
		Hook::add('Schema::get::' . $userDao->schema, [$this, 'addToSchema'], Hook::SEQUENCE_CORE);
		Hook::add('changepasswordform::execute', [$this, 'rememberPasswords']);
		Hook::add('loginchangepasswordform::execute', [$this, 'rememberPasswords']);
		Hook::add('loginchangepasswordform::Constructor', [$this, 'passwordChangeValidation']);
		Hook::add('changepasswordform::Constructor', [$this, 'passwordChangeValidation']);
	}

	/**
	 * Adds new properties to our Schema
	 * @param string $hookname The hook name
	 * @param array $args Arguments of the hook
	 * @return bool false if process completes
	 */
	public function addToSchema($hook, $args) {
		$user = $args[0];

		$user->properties->{$this->_plugin->getSettingsName()."::lastPasswords"} = (object) [
			'type' => 'string',
			'apiSummary' => false,
			'validation' => ['nullable']
		];

		$user->properties->{$this->_plugin->getSettingsName()."::lastPasswordsUpdate"} = (object) [
			'type' => 'string',
			'apiSummary' => false,
			'validation' => ['nullable', 'date:Y-m-d H:i:s']
		];

		return false;
	}

	/**
	 * Checks the current form and remembers the user's passwords
	 * @param string $hook The hook name
	 * @param array $args Arguments of the hook
	 * @return bool false if process completes
	 */
	public function rememberPasswords($hook, $args) {
		if ($hook == 'changepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\ChangePasswordForm') {
			$user = $args[0]->getUser();
		}
		else if ($hook == 'loginchangepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\LoginChangePasswordForm'){
			$user = Repo::user()->getByUsername($args[0]->getData('username'), false);
		}

		$password = $user->getData('password');
		$this->_addPassword($user, $password);
		return false;
	}

	/**
	 * Checks the current form and checks if the user's inputted password has been used previously
	 * @param string $hook The hook name
	 * @param array $args Arguments of the hook
	 * @return bool true if process completes
	 */
	public function passwordChangeValidation($hook, $args) {
		$form = $args[0];
		$newCheck = new FormValidatorCustom($form, 'password', 'required', 'plugins.generic.betterPassword.validation.betterPasswordPasswordReused', function ($password) use ($form) {
			 return $this->passwordCompare($password, $form);
		}); //$this->passwordCompare());
		$form->addCheck($newCheck);
		return false;
	}

	/**
	 * Updates the time of the users last password change
	 * @param string $password The user's given password
	 * @param ChangePasswordForm $form The form for changing passwords
	 * @return bool false if the user's password has been reused and true if it has not
	 */
	public function passwordCompare($password, $form) {
		$user = $form->_user;
		$storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
		if ($user) {
			$storedPasswords = $storedPasswordsDao->getByUserId($user->getId());
		}
		else {
			$storedPasswords = null;
		}

		if ($storedPasswords) {
			if (!$user) {
				$user = Repo::user()->getByUsername($form->getData('username'));
			}
			foreach ($storedPasswords->getPasswords() as $previousPassword) {
				if (Validation::verifyPassword($user->getUsername(), $password, $previousPassword, $rehash)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Retrieve the hashed user passwords
	 * @param User $user The given user
	 * @return ?string The list of passwords
	 */
	private function _getPasswords(User $user) : array {
		$hold = $this->_plugin->getSettingsName();
		$hold2 = $user->getData("{$hold}::lastPasswords");
		$hold3 = json_decode($hold2);
		$hold4 = $user->getAllData();
		$passwords = $hold3 ??  [];
		return array_slice($passwords, 0, $this->_maxReusablePasswords);
	}

	/**
	 * Add a user password to the list of blocked passwords and refresh the last updated timestamp
	 * @param User $user The given user
	 * @param string $password The given password
	 * @return array $totalPasswords An array containing all of a user's blocked passwords
	 */
	private function _addPassword(User $user, string $password) : array {
		$storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
		$storedPasswords = $storedPasswordsDao->getByUserId($user->getId());
		if ($storedPasswords) {
			$totalPasswords = $storedPasswords->getPasswords();
			if (count($totalPasswords) < $this->_maxReusablePasswords) {
				array_push($totalPasswords, $password);
				$storedPasswords->setPasswords($totalPasswords, true);
				$storedPasswordsDao->update($storedPasswords);
			}
			else {
				array_shift($totalPasswords);
				array_push($totalPasswords, $password);
				$storedPasswords->setPasswords($totalPasswords, true);
				$storedPasswordsDao->update($storedPasswords);
			}
		}
		else {
			$storedPasswords = $storedPasswordsDao->newDataObjects($user->getId(), $user->getPassword());
			$totalPasswords = $storedPasswords->getPasswords();
			$storedPasswords->setPasswords($totalPasswords, true);
			$storedPasswordsDao->insert($storedPasswords);
		}

		return $totalPasswords;
	}
}
