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
namespace APP\plugins\generic\betterPassword;

use APP\facades\Repo;
use APP\template\TemplateManager;
use APP\plugins\generic\betterPassword\features\Blocklist as Blocklist;
use APP\plugins\generic\betterPassword\features\ForceExpiration as ForceExpiration;
use APP\plugins\generic\betterPassword\features\LimitRetry as LimitRetry;
use APP\plugins\generic\betterPassword\features\LimitReuse as LimitReuse;
use APP\plugins\generic\betterPassword\features\SecurityRules as SecurityRules;
use APP\plugins\generic\betterPassword\BetterPasswordSettingsForm as BetterPasswordSettingsForm;
use APP\plugins\generic\betterPassword\BetterPasswordSchemaMigration as BetterPasswordSchemaMigration;
use APP\plugins\generic\betterPassword\classes\BadpwFailedLoginsDAO;
use APP\plugins\generic\betterPassword\classes\StoredPasswordsDAO;
use PKP\plugins\GenericPlugin;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\linkAction\request\AjaxModal;
use PKP\config\Config;
use PKP\linkAction\LinkAction;
use PKP\core\PKPApplication;
use PKP\core\JSONMessage;

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

			new LimitRetry($this);

			new LimitReuse($this);

			new Blocklist($this);

			new SecurityRules($this);

			new ForceExpiration($this);
		}
		return $success;
	}

	/**
	 * Register this plugin's DAO with the application
	 */
	private function _registerDAOs() : void {
		$badpwFailedLoginDAO = new BadpwFailedLoginsDAO();
		DAORegistry::registerDAO('BadpwFailedLoginsDAO', $badpwFailedLoginDAO);
		$storedPasswords = new StoredPasswordsDAO();
		DAORegistry::registerDAO('StoredPasswordsDAO', $storedPasswords);
		Hook::add('Schema::get::storedPassword', [$this, 'setStoredPasswordSchema']);
	}

	/**
	 * @copydoc Plugin::getInstallMigration()
	 */
	public function getInstallMigration() {
		$schemaMigration = new BetterPasswordSchemaMigration();
		return $schemaMigration;
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $args) : array {
		$actions = parent::getActions($request, $args);
		if (!$this->getEnabled()) {
			return $actions;
		}
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
			$templateManager = TemplateManager::getManager($request);
			$templateManager->registerPlugin('function', 'plugin_url', [$this, 'smartyPluginUrl']);
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
	 * Sets the schema for our StoredPasswords 
	 * @param string $hook The hook name
	 * @param array $args Arguments of the hook
	 * @return bool false if process completes
	 */
	public function setStoredPasswordSchema ($hook, $args) {
		$schema =& $args[0];
		$schemaFile = sprintf('%s/plugins/generic/betterPassword/schemas/storedPassword.json', BASE_SYS_DIR);
		if (file_exists($schemaFile)) {
			$temp = json_decode(file_get_contents($schemaFile));
			foreach ($temp as $key => $value) {
				$schema->$key = $value;
			}

			if (!$schema) {
				throw new Exception('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $schemaFile . '. Last JSON error: ' . json_last_error());
			}
		} else {
			throw new Exception('Schema file does not exist at location: ' . $schemaFile);
		}

		return false;
	}
}

if (!PKP_STRICT_MODE) {
    class_alias('APP\plugins\generic\betterPassword\BetterPasswordPlugin', '\BetterPasswordPlugin');
}
