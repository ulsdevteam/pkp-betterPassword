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
			$lowerPassword = strtolower($password);
                        $shaPass = sha1($lowerPassword);
                        $passwordHash = substr($shaPass,0,2);
                        $blacklist = $this->blacklistSetting();
                        if ($blacklist) {
                            $cache = CacheManager::getManager()->getCache('badPasswords', $passwordHash, array($this, '_PasswordCacheMiss'));
                            $badPassword = $cache->get($shaPass);
                        }
                        else {
                            $badPassword = true;
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
	 * @return 0|1|2 0 if update failed, 1 if setting is updated, 2 if new setting has been created
	 */
        
        function blacklistSetting() {
            $blacklistFileSetting = $this->getSetting(CONTEXT_SITE, 'betterPasswordBlacklistFiles');
            $updateSettings = false;
            if (is_null($blacklistFileSetting)) {
                $blacklistFileSettingHash = array();
                foreach ($this->getBlacklists() as $filename) {
                        $blacklistFileSettingHash[$filename] = sha1_file($filename);
                }
                $this->updateSetting(CONTEXT_SITE, 'betterPasswordBlacklistFiles', $blacklistFileSettingHash);
                $updateSettings = true;
            }
            else {
                $currBlacklist = $this->handleTempFile(false);
                $this->updateSetting(CONTEXT_SITE, 'betterPasswordBlacklistFiles', $currBlacklist);
                $updateSettings = true;
            }
            return $updateSettings;
        }
	/**
	 * Create a temporary file with the data of password blacklists.
         * @param boolean $check Default true, only false when updating temp file
	 * @return string|boolean|null temporary filename string, or boolean or null when there is an error, when $check is false return associative array
	 */
         function handleTempFile($check = true) {
            import('lib.pkp.classes.file.PrivateFileManager');
            $fileMgr = new PrivateFileManager();
            $siteDao = DAORegistry::getDAO('SiteDAO');
            $site = $siteDao->getSite();
            $minLengthPass = $site->getMinPasswordLength();
            $tempFileDir = realpath($fileMgr->getBasePath()) . DIRECTORY_SEPARATOR . 'betterPassword';
            if (!$fileMgr->fileExists($tempFileDir, 'dir')) {
			$success = $fileMgr->mkdirtree($tempFileDir);
			if (!$success) {
                            // Files directory wrong configuration?
                            return null;
			}
		}
            if(!file_exists($tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile')) {
                $fpTemp = fopen($tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile', 'a');    
                foreach ($this->getBlacklists() as $filename) {
                    $fpPass = fopen($filename, "r");
                    if (flock($fpTemp, LOCK_EX)) {
                        while (!feof($fpPass)) {
                            $passwordLine = fgets($fpPass);
                            if (strlen($passwordLine) >= $minLengthPass) {
                                fwrite($fpTemp, $passwordLine);
                            }
                        }
			flock($fpTemp, LOCK_UN);
                    } else {
                            return null;
                    }
                    fclose($fpPass);
                }
                fclose($fpTemp);
            }
            else {
                if (!$check) {
                    $currBlacklist = array();
                    foreach ($this->getBlacklists() as $filename) {
                            $currBlacklist[$filename] = sha1_file($filename);
                    }
                    $prevBlacklist = $this->getSetting(CONTEXT_SITE, 'betterPasswordBlacklistFiles');
                    if ($prevBlacklist != $currBlacklist) {
                        $tempFilename = $tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile';
                        $fpTemp = fopen($tempFilename, 'w');
                        foreach ($this->getBlacklists() as $filename) {
                                $fpPass = fopen($filename, "r");
                                if (flock($fpTemp, LOCK_EX)) {
                                    while (!feof($fpPass)) {
                                        $passwordLine = fgets($fpPass);
                                        if (strlen($passwordLine) >= $minLengthPass) {
                                            fwrite($fpTemp, $passwordLine);
                                        }
                                    }
                                    flock($fpTemp, LOCK_UN);
                                }
                                fclose($fpPass);
                        }
                        fclose($fpTemp);
                        $flushCache = CacheManager::getManager()->flush('badPasswords');
                        return $currBlacklist;
                }
                }
            }
            return ($tempFileDir . DIRECTORY_SEPARATOR . 'tempPassFile');
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
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getByUsername($username);
				if (isset($user)) {
					$count = $user->getData($this->getName()."::badPasswordCount");
					$time = $user->getData($this->getName()."::badPasswordTime");
					// expire old bad password attempts
					if (($count || $time) && $time < time() - $this->getSetting(CONTEXT_SITE, 'betterPasswordLockExpires')) {
						$count = 0;
					}
					// update the count and time to represent this failed attempt
					$count++;
					$time = time();
					$user->setData($this->getName()."::badPasswordCount", $count);
					$user->setData($this->getName()."::badPasswordTime", $time);
					$userDao->updateObject($user);
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
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getByUsername($_POST['username']);
				if (isset($user)) {
					$count = $user->getData($this->getName()."::badPasswordCount");
					$time = $user->getData($this->getName()."::badPasswordTime");
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
	 * Callback to fill cache with data, if empty.
	 * @param $cache Cache
	 * @param $password_hash string The hash of the user password
	 * @return boolean if hash of the password exists in the cache
	 */
	function _PasswordCacheMiss($cache, $passwordHash) {
            $check = get_class($cache->cacheMiss);
            if ($check === 'generic_cache_miss') {
                $cache_password = array();
                $Passwords = fopen($this->handleTempFile(), "r");
                while (!feof($Passwords)) {
                    $curr_password = rtrim(fgets($Passwords), PHP_EOL);
                    $sha_curr_password = sha1($curr_password);
                    if (strcmp(substr($sha_curr_password,0,2), substr($passwordHash,0,2)) == 0) {
                        $cache_password[$sha_curr_password] = $sha_curr_password;
                    }
                }
                fclose($Passwords);
                $cache->setEntireCache($cache_password);
                return in_array($passwordHash, $cache_password);
            }
	}
}
