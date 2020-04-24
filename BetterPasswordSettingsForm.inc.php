<?php

/**
 * @file plugins/generic/betterPassword/betterPasswordSettingsForm.inc.php
 *
 * Copyright (c) 2019 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class betterPasswordSettingsForm
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Form for administrators to modify Better Password plugin settings
 */


import('lib.pkp.classes.form.Form');

class BetterPasswordSettingsForm extends Form {

	/** @var $_contextId int */
	var $_contextId;

	/** @var $_plugin betterPasswordPlugin */
	var $_plugin;

	/** @var $_dependentFieldSemaphore bool flag if the dependent field error is already set */
	var $_dependentFieldSemaphore = false;

	/**
	 * Constructor
	 * @param $plugin BetterPasswordPlugin
	 * @param $contextId int (not used)
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = CONTEXT_SITE;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'), $this->_contextId);

		$lockFields = array();
		foreach (array_keys($this->_plugin->settingsKeys) as $key) {
			if (strpos($key, 'betterPasswordLock') === 0) {
				$lockFields[] = $key;
			}
		}

		foreach ($lockFields as $field) {
			$this->addCheck(new FormValidatorCustom($this, $field, FORM_VALIDATOR_OPTIONAL_VALUE, 'plugins.generic.betterPassword.manager.settings.betterPasswordLockRequired', array(&$this, '_dependentFormFieldIsSet'), array(&$this, $lockFields)));
			$this->addCheck(new FormValidatorCustom($this, $field, FORM_VALIDATOR_OPTIONAL_VALUE, 'plugins.generic.betterPassword.manager.settings.'.$field.'NumberRequired', create_function('$s', 'return ($s === "0" || $s > 0);')));
		}

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$contextId = $this->_contextId;
		$plugin =& $this->_plugin;

		parent::initData();
		foreach (array_keys($plugin->settingsKeys) as $k) {
			if (strpos($k, 'betterPassword') === 0) {
				$this->setData($k, $plugin->getSetting($contextId, $k));
			} else {
				$siteDao = DAORegistry::getDAO('SiteDAO');
				$site = $siteDao->getSite();
				$this->setData($k, $site->getMinPasswordLength());
			}
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array_keys($this->_plugin->settingsKeys));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = NULL, $display = false) {
		$router = $request->getRouter();
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN);
		$plugin = $this->_plugin;
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $plugin->getName());
		foreach (array_keys($plugin->settingsKeys) as $key) {
			if (strpos($key, 'betterPasswordCheck') === 0) {
				$checkboxes[$key] = $this->getData($key);
			}
			if (strpos($key, 'betterPasswordLock') === 0) {
				$locking[$key] = $this->getData($key) ? $this->getData($key) : '';
			}
		}
		$blacklistFiles = $plugin->getSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles');
		$templateMgr->assign('betterPasswordCheckboxes', $checkboxes);
		$templateMgr->assign('betterPasswordLocking', $locking);
		foreach (array_keys($blacklistFiles) as $k) {
			$blacklistFiles[$k] = new LinkAction(
						'deleteBlacklist',
						new RemoteActionConfirmationModal(
							$request->getSession(),
							__('plugins.generic.betterPassword.actions.deleteBlacklistCheck'),
							__('plugins.generic.betterPassword.actions.deleteBlacklist'),
							$router->url($request, null, null, 'deleteBlacklists', null, array('fileId' => $blacklistFiles[$k]))
							),
						__('common.delete'),
						null,
						__('plugins.generic.betterPassword.grid.action.deleteBlacklist')
					);
		}
		$templateMgr->assign('betterPasswordBlacklistFiles', $blacklistFiles);
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$plugin =& $this->_plugin;
		$contextId = $this->_contextId;

		foreach ($plugin->settingsKeys as $k => $v) {
			$saveData = $this->getData($k);
			$saveType = $v;
			switch ($v) {
				case 'bool':
					$saveData = boolval($saveData);
					break;
				case 'int':
					$saveData = intval($saveData);
					break;
			}
			if (strpos($k, 'betterPassword') === 0) {
				$plugin->updateSetting($contextId, $k, $saveData, $saveType);
			} else {
				$siteDao = DAORegistry::getDAO('SiteDAO');
				$site = $siteDao->getSite();
				$site->setMinPasswordLength(intval($saveData));
				$siteDao->updateObject($site);
			}
		}
	}

	/**
	 * Check for the presence of dependent fields if a field value is set
	 * @param $fieldValue mixed the value of the field being checked
	 * @param $form object a reference to this form
	 * @param $dependentFields array a list of dependent field names
	 * @return boolean
	 */
	function _dependentFormFieldIsSet($fieldValue, $form, $dependentFields) {
		if ($fieldValue && !$this->_dependentFieldSemaphore) {
			$dependentValues = true;
			foreach ($dependentFields as $field) {
				if (!$form->getData($field)) {
					$dependentValues = false;
				}
			}
			if ($dependentValues) {
				// Field was set and dependent values are present
				return true;
			} else {
				// Field was set but dependent value was missing
				$this->_dependentFieldSemaphore = true;
				return false;
			}
		} else {
			// No value set, so no dependency
			return true;
		}
		return true;
	}
}
