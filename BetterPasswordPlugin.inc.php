<?php

/**
 * @file plugins/generic/betterPassword/BetterPasswordPlugin.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class BetterPasswordPlugin
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Better Password plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class BetterPasswordPlugin extends GenericPlugin {
	/**
	 * @var $settings array
	 *  This array associates available settings with setting types
	 *  Name prefixes of betterPassword, betterPasswordCheck, betterPasswordLock, and betterPasswordInvalidation are magical
	 *    "betterPassword" will trigger the setting to be saved within the plugin
	 *    "betterPasswordCheck" will be a group of checkbox options
	 *    "betterPasswordLock" will be a group of text fields
	 *    "betterPasswordInvalidation" will be a group of text fields
	 */
	private $_settings = [
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

		'betterPasswordInvalidationDays' => 'int',
		'betterPasswordInvalidationMininumWarningDays' => 'int',
		'betterPasswordInvalidationPasswords' => 'int'
	];

	/**
	 * Retrieve the plugin settings
	 * @return array
	 */
	public function getSettings() : array {
		return $this->_settings;
	}

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) : bool {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
			return true;
		}
		if ($success && $this->getEnabled()) {
			$this->_registerDAOs();
			$this->_addUserSettings();

			$this->import('features.LimitRetry');
			new LimitRetry($this);

			$this->import('features.LimitReuse');
			new LimitReuse($this);

			$this->import('features.Blocklist');
			new Blocklist($this);

			$this->import('features.SecurityRules');
			new SecurityRules($this);

			$this->import('features.ForceExpiration');
			new ForceExpiration($this);
		}

		return $success;
	}

	/**
	 * Register this plugin's DAO with the application
	 */
	private function _registerDAOs() : void {
		$this->import('classes.BadpwFailedLoginsDAO');

		$badpwFailedLoginDAO = new BadpwFailedLoginsDAO();
		DAORegistry::registerDAO('BadpwFailedLoginsDAO', $badpwFailedLoginDAO);
	}

	/**
	 * Add the required user settings
	 * @see DAO::getAddtionalFieldNames
	 */
	private function _addUserSettings() : void {
		HookRegistry::register('userdao::getAdditionalFieldNames', function ($hook, $args) {
			$fields = &$args[1];
			$prefix = "{$this->getName()}::";
			foreach (['lastPasswords', 'lastPasswordUpdate', 'lastPasswordNotification'] as $field) {
				$fields[] = $prefix . $field;
			}
		});
	}

	/**
	* @copydoc Plugin::getInstallMigration()
	*/
	function getInstallMigration() {
		$this->import('BetterPasswordSchemaMigration');
		return new BetterPasswordSchemaMigration();
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $args) : array {
		$actions = parent::getActions($request, $args);
		if (!$this->getEnabled()) {
			return $actions;
		}
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			[
				new LinkAction(
					'settings',
					new AjaxModal(
						$request->getRouter()->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
						$this->getDisplayName()
					),
					__('manager.plugins.settings')
				)
			],
			$actions
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request) : ?JSONMessage {
		$verb = $request->getUserVar('verb');
		if ($verb == 'settings') {
			$templateManager = TemplateManager::getManager();
			$templateManager->register_function('plugin_url', [$this, 'smartyPluginUrl']);
			$this->import('BetterPasswordSettingsForm');
			$form = new BetterPasswordSettingsForm($this);
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
	 * @copydoc Plugin::isSitePlugin()
	 */
	public function isSitePlugin() : bool {
		return true;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() : string {
		return __('plugins.generic.betterPassword.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() : string {
		return __('plugins.generic.betterPassword.description');
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	public function getName() : string {
		return __CLASS__;
	}
}
