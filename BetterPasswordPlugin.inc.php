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
	 * @var $settings array
	 *  This array associates available settings with setting types
	 *  Name prefixes of betterPassword, betterPasswordCheck, and betterPasswordLock are magical
	 *    "betterPassword" will trigger the setting to be saved within the plugin
	 *    "betterPasswordCheck" will be a group of checkbox options
	 *    "betterPasswordLock" will be a group of textfields
	 */
	public $settingsKeys = [
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
		'betterPasswordInvalidationSeconds' => 'int',
		'betterPasswordInvalidationPasswords' => 'int',
	];

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled()) {
			// Attach hooks
			$this->registerDAOs();
			foreach (['registrationform::validate', 'changepasswordform::validate', 'loginchangepasswordform::validate'] as $hook) {
				HookRegistry::register($hook, [&$this, 'checkPassword']);
			}
			$lockTries = $this->getSetting(CONTEXT_SITE, 'betterPasswordLockTries');
			$passwordLifetime = $this->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationSeconds');
			$maxReusablePasswords = $this->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationPasswords');
			if ($maxReusablePasswords) {
				foreach(['loginchangepasswordform::Constructor', 'changepasswordform::Constructor'] as $hook) {
					HookRegistry::register($hook, function ($hook, $args) use ($maxReusablePasswords) {
						[$form] = $args;
						$this->_addReuseValidator($form, $maxReusablePasswords);
					});
				}
			}
			if ($lockTries) {
				// Register callback to add text to registration page
				HookRegistry::register('TemplateManager::display', [$this, 'handleTemplateDisplay']);
			}
			if ($lockTries || $maxReusablePasswords || $passwordLifetime) {
				// Add a handler to hijack login requests
				HookRegistry::register('LoadHandler', [$this, 'callbackLoadHandler']);
			}
			HookRegistry::register('userdao::getAdditionalFieldNames', [&$this, 'addUserSettings']);
			HookRegistry::register('LoadComponentHandler', [$this, 'callbackLoadHandler']);
		}
		return $success;
	}

	private function _addReuseValidator($form, $maxReusablePasswords) {
		$form->addCheck(new FormValidatorCustom(
			$form, 'password', 'required', 'plugins.generic.betterPassword.validation.betterPasswordPasswordReused',
			function ($password) use ($form, $maxReusablePasswords) {
				$username = $form->getData('username') ?? Application::get()->getRequest()->getUser()->getUsername();
				$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
				$badPwUser = $badpwFailedLoginsDao->getByUsername($username);
				foreach ($badPwUser->getPasswords($maxReusablePasswords) as $previousPassword) {
					if (Validation::verifyPassword($username, $password, $previousPassword, $rehash)) {
						return false;
					}
				}
				return true;
			}
		));
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
			$this->getEnabled()?[
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			]:[],
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
				$templateMgr->register_function('plugin_url', [$this, 'smartyPluginUrl']);
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
		$form = null;
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
			$shaPass = sha1($lowerPassword);
			$passwordHash = substr($shaPass, 0, 2);
			$blacklist = $this->generateBlacklist();
			if ($blacklist) {
				$cache = CacheManager::getManager()->getCache('badPasswords', $passwordHash, [$this, '_passwordCacheMiss']);
				$badPassword = $cache->get($shaPass);
			} else {
				$form->addError($errorField, __('plugins.generic.betterPassword.validation.betterPasswordUnexpectedError'));
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
	 * Check for newly added blacklists. If ran the first time creates the list of blacklists
	 * @return boolean false if updating blacklist failed, true if updated the blacklist
	 */	
	function generateBlacklist() {
		$prevBlacklist = $this->getSetting(CONTEXT_SITE, 'betterPasswordBlacklistFiles');
		$updateBlacklist = false;
		$newBlacklist = [];
		foreach ($this->getBlacklists() as $filename) {
			$newBlacklist[$filename] = sha1_file($filename);
		}
		if (is_null($prevBlacklist) || $prevBlacklist != $newBlacklist) {
			$updateTempFile = $this->handleTempFile();
			if ($updateTempFile) {
				$this->updateSetting(CONTEXT_SITE, 'betterPasswordBlacklistFiles', $newBlacklist);
				$updateBlacklist = true;
			}
		} else {
			$updateBlacklist = true;
		}
		return (boolean) $updateBlacklist;
	}

	/**
	 * Creates a temporary file to aggregate all the passwords from the blacklist files if the temporary file doesn't exist
	 * @return string|null The file path for the temporary file
	 */
	function getTempFile() {
		import('lib.pkp.classes.file.PrivateFileManager');
		$fileMgr = new PrivateFileManager();
		$tempFileDir = realpath($fileMgr->getBasePath()) . DIRECTORY_SEPARATOR . 'betterPassword';
		if (!$fileMgr->fileExists($tempFileDir, 'dir')) {
			$success = $fileMgr->mkdirtree($tempFileDir);
			if (!$success) {
				error_log('ERROR: Unable to create directory' . $tempFileDir);// Files directory wrong configuration?
				return null;
			}
		}
		if (!file_exists($tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile')) {
			touch($tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile');
		}
		return $tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile';
	}

	/**
	 * Handles the temporary file with the data of password blacklists.
	 * @return boolean True if operations done to the temp file succeed  
	 */
	function handleTempFile() {
		$siteDao = DAORegistry::getDAO('SiteDAO');
		$site = $siteDao->getSite();
		$minLengthPass = $site->getMinPasswordLength();
		$tempFilePath = $this->getTempFile();
		$fpTemp = fopen($tempFilePath, 'w');
		if (!$fpTemp) {
			error_log('ERROR: Could not create file temporary file');
			return false;
		}
		foreach ($this->getBlacklists() as $filename) {
			$fpPass = fopen($filename, "r");
			if (!$fpPass) {
				error_log('ERROR: Could not open blacklist file ' . $filename);
				return false;
			}
			if (flock($fpTemp, LOCK_EX)) {
				while (!feof($fpPass)) {
					$passwordLine = rtrim(fgets($fpPass), PHP_EOL);
					if (strlen($passwordLine) >= $minLengthPass) {
						fwrite($fpTemp, $passwordLine . PHP_EOL);
					}
				}
				flock($fpTemp, LOCK_UN);
			} else {
				error_log('ERROR: Could not lock file ' . $tempFilePath . ' to write contents');
				return false;
			}
			fclose($fpPass);
		}
		fclose($fpTemp);
		CacheManager::getManager()->flush('badPasswords');
		return true;
	}

	/**
	 * Get the filename(s) of password blacklists.
	 * @return array filename strings
	 */
	function getBlacklists() {
		import('lib.pkp.classes.file.PrivateFileManager');
		$privateFileManager = new PrivateFileManager();
		$userBlacklists = $this->getSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles');
		foreach ($userBlacklists as $f) {
			$userBlacklistsFilepath[] = $privateFileManager->getBasePath() . DIRECTORY_SEPARATOR . 'betterPassword' . DIRECTORY_SEPARATOR . 'blacklists' . DIRECTORY_SEPARATOR . $f;
		}
		$pluginBlacklistsFilenames = [$this->getPluginPath() . DIRECTORY_SEPARATOR . 'badPasswords' . DIRECTORY_SEPARATOR . 'badPasswords.txt'];
		$blacklistFilenames = array_merge($userBlacklistsFilepath, $pluginBlacklistsFilenames);
		return $blacklistFilenames;
	}

	/**
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
				if (isset($user)) {
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
		$lockTries = $this->getSetting(CONTEXT_SITE, 'betterPasswordLockTries');
		$maxReusablePasswords = $this->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationPasswords');
		$passwordLifetime = $this->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationSeconds');

		if ($args[0] === "tab.user.ProfileTabHandler" && $args[1] === 'savePassword' && $maxReusablePasswords) {
			HookRegistry::register('userdao::_updateobject', function ($hook, $args) use ($maxReusablePasswords) {
				$params = $args[1];
				[$username, $password] = $params;
				$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
				$user = $badpwFailedLoginsDao->getByUsername($username);
				$badpwFailedLoginsDao->updatePassword($user, $password, $maxReusablePasswords);
				return false;
			});
		} elseif ($args[0] === "login" && $args[1] === "signIn" && ($username = $_POST['username'] ?? '')) {
			// Hijack the user's signin attempt, if frequent bad passwords are being tried
			$badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
			$user = $badpwFailedLoginsDao->getByUsername($username);
			if (!$user) {
				return false;
			}
			if ($lockTries) {
				$count = $user->getCount();
				$time = $user->getFailedTime();
				if ($count >= $lockTries && $time > time() - $this->getSetting(CONTEXT_SITE, 'betterPasswordLockSeconds')) {
					// Hijack the typical login/signIn handler to prevent login
					define('HANDLER_CLASS', 'BetterPasswordHandler');
					$args[0] = "plugins.generic.betterPassword.BetterPasswordHandler";
					import($args[0]);
					return true;
				}
			}
			if ($passwordLifetime && $user->getLastPasswordUpdate() + $passwordLifetime < time()) {
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getByUsername($username);
				if ($user && !$user->getMustChangePassword()) {
					$user->setMustChangePassword(true);
					$userDao->updateObject($user);
				}
			}
		} elseif ($args[0] === "login" && $args[1] === "resetPassword" && $lockTries) {
			// Hijack the password reset request to clear bad password locks
			if (method_exists('Application', 'get')) {
				$request = Application::get()->getRequest();
			} else {
				// Legacy 3.1.x: remove when support is dropped
				$request = Application::getApplication()->getRequest();
			}
			$username = array_shift($request->getRequestedArgs());
			$queryString = $request->getQueryArray();
			$userDao = DAORegistry::getDAO('UserDAO');
			$confirmHash = $queryString['confirm'];
			$user = $userDao->getByUsername($username);
			if ($user && $confirmHash && Validation::verifyPasswordResetHash($user->getId(), $confirmHash)) {
					$user->setData($this->getName()."::badPasswordCount", 0);
					$user->setData($this->getName()."::badPasswordTime", 0);
					$userDao->updateObject($user);
			}
		} elseif ($hookName === "LoadComponentHandler" && in_array($args[1], ['deleteBlacklists', 'uploadBlacklists'])) {
			define('HANDLER_CLASS', 'BetterPasswordComponentHandler');
			$args[0] = "plugins.generic.betterPassword.BetterPasswordHandler";
			import($args[0]);
			return true;
		}
		return false;
	}

	/**
	 * Add locking information to the User DAO
	 * @see DAO::getAddtionalFieldNames
	 */
	function addUserSettings($hookname, $args) {
		$fields =& $args[1];
		$fields = array_merge($fields, [
			$this->getName()."::badPasswordCount",
			$this->getName()."::badPasswordTime",
		]);
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

	/**
	 * Callback to fill cache with data, if empty.
	 * @param $cache GenericCache
	 * @param $passwordHash string The hash of the user password
	 * @return boolean if hash of the password exists in the cache
	 */
	function _passwordCacheMiss($cache, $passwordHash) {
		$check = get_class($cache->cacheMiss);
		if ($check === 'generic_cache_miss') {
			$cache_password = [];
			$passwords = fopen($this->getTempFile(), "r");
			while (!feof($passwords)) {
				$curr_password = rtrim(fgets($passwords), PHP_EOL);
				$sha_curr_password = sha1($curr_password);
				if (strcmp(substr($sha_curr_password, 0, 2), substr($passwordHash, 0, 2)) == 0) {
					$cache_password[$sha_curr_password] = $sha_curr_password;
				}
			}
			fclose($passwords);
			$cache->setEntireCache($cache_password);
			return in_array($passwordHash, $cache_password);
		}
		return false;
	}
}
