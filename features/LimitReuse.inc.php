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
		$this->_maxReusablePasswords = (int) $plugin->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationPasswords');
		if (!$this->_maxReusablePasswords) {
			return;
		}

		$this->_saveUserPasswords();
		$this->_addPasswordChangeValidation();
	}

	/**
	 * Register a hook to validate reused passwords
	 */
	private function _addPasswordChangeValidation() : void {
		foreach(['loginchangepasswordform::Constructor', 'changepasswordform::Constructor'] as $hook) {
			HookRegistry::register($hook, function ($hook, $args) {
				/** @var Form $form */
				[$form] = $args;
				$form->addCheck(new FormValidatorCustom(
					$form, 'password', 'required', 'plugins.generic.betterPassword.validation.betterPasswordPasswordReused',
					function ($password) use ($form) {
						$user = Application::get()->getRequest()->getUser();
						if (!$user) {
							/** @var UserDAO */
							$userDao = DAORegistry::getDAO('UserDAO');
							$user = $userDao->getByUsername($form->getData('username'));
						}
						foreach ($this->_getPasswords($user) as $previousPassword) {
							// Check if an old password matches with the new
							if (Validation::verifyPassword($user->getUsername(), $password, $previousPassword, $rehash)) {
								return false;
							}
						}
						return true;
					}
				));
			});
		}
	}

	/**
	 * Register a hook to save new passwords
	 */
	private function _saveUserPasswords() : void {
		$handler = function ($hook, $args) {
			[$page, $operation] = $args;
			if (!in_array($page, ['tab.user.ProfileTabHandler', 'login']) || $operation !== 'savePassword') {
				return;
			}
			HookRegistry::register('userdao::_updateobject', function ($hook, $args) {
				// Avoid an infinite loop after updating itself
				if ($this->_handledPasswordUpdate) {
					return;
				}
				[, [$username, $password]] = $args;
				/** @var UserDao */
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getByUsername($username);
				$this->_addPassword($user, $password);
				$this->_handledPasswordUpdate = true;
				$userDao->updateObject($user);
			});
		};
		HookRegistry::register('LoadComponentHandler', $handler);
		HookRegistry::register('LoadHandler', $handler);
	}

	/**
	 * Retrieve the hashed user passwords
	 * @param User $user
	 * @return ?string
	 */
	private function _getPasswords(User $user) : array {
		$passwords = json_decode($user->getData("{$this->_plugin->getName()}::lastPasswords")) ?? [];
		return array_slice($passwords, 0, $this->_maxReusablePasswords);
	}

	/**
	 * Add a user password and refresh the last updated timestamp
	 * @param User $user
	 * @param string $password
	 */
	private function _addPassword(User $user, string $password) : void {
		$passwords = $this->_getPasswords($user);
		array_unshift($passwords, $password);
		$passwords = array_slice($passwords, 0, $this->_maxReusablePasswords);
		$user->setData("{$this->_plugin->getName()}::lastPasswords", json_encode($passwords));
		$user->setData("{$this->_plugin->getName()}::lastPasswordUpdate", (new DateTime())->format('c'));
	}
}
