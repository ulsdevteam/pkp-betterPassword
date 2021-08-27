<?php

/**
 * @file plugins/generic/betterPassword/features/LimitRetry.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class LimitRetry
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to limit retries and lock the account
 */

class LimitRetry {
	/** @var int Max amount of retries */
	private $_maxRetries;

	/** @var int Amount of seconds to lock account */
	private $_lockSeconds;

	/** @var int Max amount of retries */
	private $_lockExpiresSeconds;

	/**
	 * Constructor
	 * @param BetterPasswordPlugin $plugin
	 */
	public function __construct(BetterPasswordPlugin $plugin) {
		$this->_maxRetries = $plugin->getSetting(CONTEXT_SITE, 'betterPasswordLockTries');
		if (!$this->_maxRetries) {
			return;
		}
		$this->_lockSeconds = $plugin->getSetting(CONTEXT_SITE, 'betterPasswordLockSeconds');
		$this->_lockExpiresSeconds = $plugin->getSetting(CONTEXT_SITE, 'betterPasswordLockExpires');

		$this->_handleTemplateDisplay();
		$this->_addFailedLoginLogger();
		$this->_addResetPasswordReset();
	}

	/**
	 * Register callback to add text to registration page
	 */
	private function _handleTemplateDisplay() : void {
		HookRegistry::register('TemplateManager::display', function ($hook, $args) {
			/** @var TemplateManager $templateManager */
			[$templateManager, $template] = $args;
			if ($template !== 'frontend/pages/userLogin.tpl' || $templateManager->getTemplateVars('error') !== 'user.login.loginError') {
				return;
			}
			$username = $templateManager->getTemplateVars('username');
			/** @var BadpwFailedLoginsDAO */
			$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
			$user = $badpwFailedLoginsDao->getByUsername($username);
			if (!$user) {
				return;
			}
			$count = $user->getCount();
			$time = $user->getFailedTime();

			// Discard old bad password attempts
			// When the memory has expired
			if ($count && $time < time() - $this->_lockExpiresSeconds) {
				// And the user is not currently locked
				if ($user->getCount() < $this->_maxRetries || $user->getFailedTime() <= time() - $this->_lockSeconds) {
					$badpwFailedLoginsDao->resetCount($user);
				}
			}

			// Update the count to represent this failed attempt
			$badpwFailedLoginsDao->incCount($user);

			// Warn the user if the attempts have been exhausted
			if ($count >= $this->_maxRetries) {
				$templateManager->assign('error', 'plugins.generic.betterPassword.validation.betterPasswordLocked');
			}
		});
	}

	/**
	 * Register a hook to handle failed login attempts
	 */
	private function _addFailedLoginLogger() : void {
		HookRegistry::register('LoadHandler', function ($hook, $args) {
			$page = &$args[0];
			$operation = $args[1];
			$username = $_POST['username'] ?? null;
			if ([$page, $operation] !== ['login', 'signIn'] || !$username) {
				return;
			}

			/** @var BadpwFailedLoginsDAO */
			$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
			$user = $badpwFailedLoginsDao->getByUsername($username);
			if (!$user || $user->getCount() < $this->_maxRetries || $user->getFailedTime() <= time() - $this->_lockSeconds) {
				return;
			}

			// Replace the login/signIn handler to prevent login
			define('HANDLER_CLASS', 'DisabledLoginHandler');
			$page = 'plugins.generic.betterPassword.handlers.DisabledLoginHandler';
			import($page);
			return true;
		});
	}

	/**
	 * Register a hook to reset the retry count after resetting the password
	 */
	private function _addResetPasswordReset() : void {
		HookRegistry::register('LoadHandler', function ($hook, $args) {
			$page = &$args[0];
			$operation = $args[1];
			if ([$page, $operation] !== ['login', 'resetPassword']) {
				return;
			}

			$request = Application::get()->getRequest();
			$username = array_shift($request->getRequestedArgs());
			$confirmHash = $request->getQueryArray()['confirm'] ?? null;
			/** @var UserDAO */
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getByUsername($username);
			if ($user && $confirmHash && Validation::verifyPasswordResetHash($user->getId(), $confirmHash)) {
				/** @var BadpwFailedLoginsDAO */
				$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
				$user = $badpwFailedLoginsDao->getByUsername($username);
				$badpwFailedLoginsDao->resetCount($user);
			}
		});
	}
}
