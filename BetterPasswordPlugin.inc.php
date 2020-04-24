<?php

/**
 * @file plugins/generic/betterPassword/betterPasswordPlugin.inc.php
 *
 * Copyright (c) 2019 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class betterPasswordPlugin
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Better Password plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class betterPasswordPlugin extends GenericPlugin {

	/**
	 * @var $settings array()
	 *  This array associates available settings with setting types
	 *  Name prefixes of betterPassword, betterPasswordCheck, and betterPasswordLock are magical
	 *    "betterPassword" will trigger the setting to be saved within the plugin
	 *    "betterPasswordCheck" will be a group of checkbox options
	 *    "betterPasswordLock" will be a group of textfields
	 */
	public $settingsKeys = array(
		'betterPasswordCheckAlpha' => 'bool',
		'betterPasswordCheckUppercase' => 'bool',
		'betterPasswordCheckLowercase' => 'bool',
		'betterPasswordCheckNumber' => 'bool',
		'betterPasswordCheckSpecial' => 'bool',
		'betterPasswordCheckBlacklist' => 'bool',
		'betterPasswordLockTries' => 'int',
		'betterPasswordLockExpires' => 'int',
		'betterPasswordLockSeconds' => 'int',
		'minPasswordLength' => 'int',
	);
	

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled()) {
			// Attach hooks
			$this->registerDAOs();
			foreach (array('registrationform::validate', 'changepasswordform::validate', 'loginchangepasswordform::validate') as $hook) {
				HookRegistry::register($hook, array(&$this, 'checkPassword'));
			}
			if ($this->getSetting(CONTEXT_SITE, 'betterPasswordLockTries')) {
				// Register callback to add text to registration page
				HookRegistry::register('TemplateManager::display', array($this, 'handleTemplateDisplay'));
				// Add a handler to hijack login requests
				HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
			}
			HookRegistry::register('userdao::getAdditionalFieldNames', array(&$this, 'addUserSettings'));
		}
		return $success;
	}

	/**
	 * Site-wide plugins should override this function to return true.
	 *
	 * @return boolean
	 */
	function isSitePlugin() {
		return true;
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.generic.betterPassword.displayName');
	}

	/**
	 * Get a description of the plugin.
	 * @return String
	 */
	function getDescription() {
		return __('plugins.generic.betterPassword.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		$verb = $request->getUserVar('verb');
		switch ($verb) {
			case 'settings':
				$templateMgr = TemplateManager::getManager();
				$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));
				$context = $request->getContext();

				$this->import('BetterPasswordSettingsForm');
				$form = new BetterPasswordSettingsForm($this, $context ? $context->getId() : CONTEXT_SITE);
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * Hook callback: check a password against requirements
	 * @see Form::validate()
	 */
	function checkPassword($hookName, $args) {
		// Supported hooks must be enumerated here to identify the content to be checked and the form being used
		$form = NULL;
		$data = array();
		switch ($hookName) {
			case 'registrationform::validate':
			case 'changepasswordform::validate':
			case 'loginchangepasswordform::validate':
				$form = $args[0];
				$password = $form->getData('password');
				$errorField = 'password';
				break;
			default:
				return false;
		}
		if (!$password) {
			// Let the form itself handle the core required function
			return false;
		}
		if ($this->getSetting(CONTEXT_SITE, 'betterPasswordCheckBlacklist')) {
			$badPassword = false;
			$lowerPassword = strtolower($password);
			foreach ($this->getBlacklists() as $filename) {
				/* In case of out-of-memory error, break glass
				$passwordfile = fopen($filename, "r");
				while (!feof($passwordfile)) {
					$disallowed = fgets($passwordfile);
					if ($lowerPassword === $disallowed) {
						$badPassword = true;
						break;
					}
				}
				fclose($passwordfile);
				 */
				$passwordfile = file_get_contents($filename);
				if (strpos($passwordfile, $lowerPassword) !== false) {
					$badPassword = true;
					break;
				}
			}
			if ($badPassword) {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordCheckBlacklist'));
			}
		}
		if ($this->getSetting(CONTEXT_SITE, 'betterPasswordCheckAlpha')) {
			if (!PKPString::regexp_match('/[[:alpha:]]/', $password)) {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordCheckAlpha'));
			}
		}
		if ($this->getSetting(CONTEXT_SITE, 'betterPasswordCheckNumber')) {
			if (!PKPString::regexp_match('/\d/', $password)) {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordCheckNumber'));
			}
		}
		if ($this->getSetting(CONTEXT_SITE, 'betterPasswordCheckUppercase')) {
			if (!PKPString::regexp_match('/[[:upper:]]/', $password)) {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordCheckUppercase'));
			}
		}
		if ($this->getSetting(CONTEXT_SITE, 'betterPasswordCheckLowercase')) {
			if (!PKPString::regexp_match('/[[:lower:]]/', $password)) {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordCheckLowercase'));
			}
		}
		if ($this->getSetting(CONTEXT_SITE, 'betterPasswordCheckSpecial')) {
			if (!PKPString::regexp_match('/[^[:alnum:]]/', $password)) {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordCheckSpecial'));
			}
		}
	}

	/**
	 * Get the filename(s) of password blacklists.
	 * @return array filename strings
	 */
	function getBlacklists() {
		return array(
			$this->getPluginPath() . DIRECTORY_SEPARATOR . 'badPasswords' . DIRECTORY_SEPARATOR . 'badPasswords.txt',
		);
	}

	/*
	 * Hook callback: check for bad password attempts
	 * @see TemplateManager::display()
	 */
	function handleTemplateDisplay($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];
		if ($template === 'frontend/pages/userLogin.tpl') {
			if ($templateMgr->getTemplateVars('error') === 'user.login.loginError') {
				$username = $templateMgr->getTemplateVars('username');
				$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
				$user = $badpwFailedLoginsDao->getByUsername($username);
				if (!isset($user)) {
					$count = $user->getCount();
					$time = $user->getFailedTime();
					// expire old bad password attempts
					if (($count || $time) && $time < time() - $this->getSetting(CONTEXT_SITE, 'betterPasswordLockExpires')) {
						$badpwFailedLoginsDao->resetCount($user);
					}
					// update the count to represent this failed attempt
					$badpwFailedLoginsDao->incCount($user);
					// warn the user if count has been exceeded
					if ($count >= $this->getSetting(CONTEXT_SITE, 'betterPasswordLockTries')) {
						$templateMgr->assign('error', 'plugins.generic.betterPassword.validation.betterPasswordLocked');
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * @see PKPComponentRouter::route()
	 */
	public function callbackLoadHandler($hookName, $args) {
		if ($args[0] === "login" && $args[1] === "signIn") {
			// Hijack the user's signin attempt, if frequent bad passwords are being tried
			if (isset($_POST['username'])) {
				$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
				$user = $badpwFailedLoginsDao->getByUsername($_POST['username']);
				if (isset($user)) {
					$count = $user->getCount();
					$time = $user->getFailedTime();
					if ($count >= $this->getSetting(CONTEXT_SITE, 'betterPasswordLockTries') && $time > time() - $this->getSetting(CONTEXT_SITE, 'betterPasswordLockSeconds')) {
						// Hijack the typical login/signIn handler to prevent login
						define('HANDLER_CLASS', 'BetterPasswordHandler');
						$args[0] = "plugins.generic.betterPassword.BetterPasswordHandler";
						import($args[0]);
						return true;
					}
				}
			}
		} elseif ($args[0] === "login" && $args[1] === "resetPassword") {
			// Hijack the password reset request to clear bad password locks
			$username = array_shift(PKPRequest::getRequestedArgs());
			$queryString = PKPRequest::getQueryArray();
			$userDao = DAORegistry::getDAO('UserDAO');
			$confirmHash = $queryString['confirm'];
			$user = $userDao->getByUsername($username);
			if ($user && $confirmHash && Validation::verifyPasswordResetHash($user->getId(), $confirmHash)) {
					$user->setData($this->getName()."::badPasswordCount", 0);
					$user->setData($this->getName()."::badPasswordTime", 0);
					$userDao->updateObject($user);
			}
		}
		return false;
	}

	/**
	 * Add locking information to the User DAO
	 * @see DAO::getAddtionalFieldNames
	 */
	function addUserSettings($hookname, $args) {
		$fields =& $args[1];
		$fields = array_merge($fields, array(
			$this->getName()."::badPasswordCount",
			$this->getName()."::badPasswordTime",
			)
		);
		return false;
	}
	
	/**
	 * @copydoc PKPPlugin::getInstallSchemaFile()
	 */
	public function getInstallSchemaFile() {
		return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'xml' . DIRECTORY_SEPARATOR . 'schema.xml';
	}
	
	/**
	 * Register this plugin's DAO with the application
	 */
	public function registerDAOs() {
		$this->import('classes.BadpwFailedLoginsDAO');
		
		$badpwFailedLoginDAO = new BadpwFailedLoginsDAO();
		DAORegistry::registerDAO('BadpwFailedLoginsDAO', $badpwFailedLoginDAO);
	}
}
