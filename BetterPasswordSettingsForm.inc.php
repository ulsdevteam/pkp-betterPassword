<?php

/**
 * @file plugins/generic/betterPassword/BetterPasswordSettingsForm.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class BetterPasswordSettingsForm
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Form for administrators to modify Better Password plugin settings
 */

import('lib.pkp.classes.form.Form');

class BetterPasswordSettingsForm extends Form {
	/** @var $_contextId int */
	private $_contextId;

	/** @var $_plugin BetterPasswordPlugin */
	private $_plugin;

	/** @var $isDependentFieldSet bool If the dependent field error is already set */
	private $_isDependentFieldSet = false;

	/**
	 * Constructor
	 * @param $plugin BetterPasswordPlugin
	 */
	public function __construct(BetterPasswordPlugin $plugin) {
		$this->_contextId = CONTEXT_SITE;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'), $this->_contextId);

		$lockFields = [];
		$invalidationFields = [];
		foreach (array_keys($this->_plugin->getSettings()) as $setting) {
			if (strpos($setting, 'betterPasswordLock') === 0) {
				$lockFields[] = $setting;
			} elseif (strpos($setting, 'betterPasswordInvalidation') === 0) {
				$invalidationFields[] = $setting;
			}
		}

		foreach ($lockFields as $field) {
			$this->addCheck(new FormValidatorCustom(
				$this, $field, FORM_VALIDATOR_OPTIONAL_VALUE,
				'plugins.generic.betterPassword.manager.settings.betterPasswordLockRequired',
				function ($value) use ($lockFields) {
					// Only check for dependencies if the field has a value
					if ($value && !$this->_isDependentFieldSet) {
						foreach ($lockFields as $field) {
							if (!$this->getData($field)) {
								// Field was set but dependent value was missing
								$this->_isDependentFieldSet = true;
								return false;
							}
						}
					}
					return true;
				}
			));
		}

		foreach (array_merge($invalidationFields, $lockFields) as $field) {
			$this->addCheck(new FormValidatorCustom(
				$this, $field, FORM_VALIDATOR_OPTIONAL_VALUE,
				"plugins.generic.betterPassword.manager.settings.{$field}NumberRequired",
				function ($value) {
					return is_numeric($value) && is_int(+$value) && +$value > 0;
				}
			));
		}

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	public function initData() : void {
		parent::initData();

		$plugin = $this->_plugin;
		foreach (array_keys($plugin->getSettings()) as $setting) {
			if (strpos($setting, 'betterPassword') === 0) {
				$this->setData($setting, $plugin->getSetting($this->_contextId, $setting));
			} elseif ($setting == 'minPasswordLength') {
				$siteDao = DAORegistry::getDAO('SiteDAO');
				$site = $siteDao->getSite();
				$this->setData($setting, $site->getMinPasswordLength());
			}
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() : void {
		$this->readUserVars(array_keys($this->_plugin->getSettings()));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	public function fetch($request, $template = null, $display = false) : string {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN);
		$plugin = $this->_plugin;

		$checkboxes = $locking = $invalidation = [];
		foreach (array_keys($plugin->getSettings()) as $setting) {
			if (strpos($setting, 'betterPasswordCheck') === 0) {
				$checkboxes[$setting] = $this->getData($setting);
			} elseif (strpos($setting, 'betterPasswordLock') === 0) {
				$locking[$setting] = $this->getData($setting) ?: '';
			} elseif (strpos($setting, 'betterPasswordInvalidation') === 0) {
				$invalidation[$setting] = $this->getData($setting) ?: '';
			}
		}

		$blocklistFiles = [];
		foreach ($plugin->getSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles') as $hash => $name) {
			$blocklistFiles[$name] = new LinkAction(
				'deleteBlocklist',
				new RemoteActionConfirmationModal(
					$request->getSession(),
					__('plugins.generic.betterPassword.actions.deleteBlocklistCheck'),
					__('plugins.generic.betterPassword.actions.deleteBlocklist'),
					$request->getRouter()->url($request, null, null, 'deleteBlocklist', null, ['file' => $hash])
				),
				__('common.delete'),
				null,
				__('plugins.generic.betterPassword.actions.deleteBlocklist')
			);
		}
		TemplateManager::getManager($request)->assign([
			'pluginName' => $plugin->getName(),
			'betterPasswordCheckboxes' => $checkboxes,
			'betterPasswordLocking' => $locking,
			'betterPasswordInvalidation' => $invalidation,
			'betterPasswordBlocklistFiles' => $blocklistFiles
		]);
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	public function execute(...$functionArgs) : void {
		$plugin = $this->_plugin;
		foreach ($plugin->getSettings() as $setting => $type) {
			$value = $this->getData($setting);
			settype($value, $type);
			if (strpos($setting, 'betterPassword') === 0) {
				$plugin->updateSetting($this->_contextId, $setting, $value, $type);
			} elseif ($setting == 'minPasswordLength') {
				$siteDao = DAORegistry::getDAO('SiteDAO');
				$site = $siteDao->getSite();
				if ($site->getMinPasswordLength() !== $value) {
					$site->setMinPasswordLength($value);
					$siteDao->updateObject($site);
					Blocklist::clearCache();
				}
			}
		}
	}
}
