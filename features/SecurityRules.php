<?php

/**
 * @file plugins/generic/betterPassword/features/Blocklist.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class Blocklist
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to force the password to follow some security rules
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\plugins\Hook;

class SecurityRules
{
    /** @var BetterPasswordPlugin */
    private $_plugin;

    /** @var array List of validations, the first item represents the setting name */
    private const VALIDATIONS = [
        ['betterPasswordCheckAlpha', '/[[:alpha:]]/'],
        ['betterPasswordCheckNumber', '/\d/'],
        ['betterPasswordCheckUppercase', '/[[:upper:]]/'],
        ['betterPasswordCheckLowercase', '/[[:lower:]]/'],
        ['betterPasswordCheckSpecial', '/[^[:alnum:]]/']
    ];

    /**
     * Constructor
     */
    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_plugin = $plugin;
        $hasAny = false;
        foreach (self::VALIDATIONS as [$setting]) {
            if ($plugin->getSetting(PKPApplication::CONTEXT_SITE, $setting)) {
                $hasAny = true;
                break;
            }
        }

        if (!$hasAny) {
            return;
        }

        $this->_addPasswordValidation();
    }

    /**
     * Register a hook to validate passwords against a blocked list
     */
    private function _addPasswordValidation(): void
    {
        // Register callback to validate new passwords
        foreach (['registrationform::validate', 'changepasswordform::validate', 'loginchangepasswordform::validate'] as $hook) {
            Hook::add($hook, function ($hook, $args) {
                /** @var \PKP\form\Form $form */
                [$form] = $args;
                $passwordField = 'password';
                $password = $form->getData($passwordField);
                // Let the form itself handle the core required function
                if (!$password) {
                    return;
                }

                foreach (self::VALIDATIONS as [$setting, $rule]) {
                    if ($this->_plugin->getSetting(PKPApplication::CONTEXT_SITE, $setting) && !PKPString::regexp_match($rule, $password)) {
                        $form->addError($passwordField, __("plugins.generic.betterPassword.validation.{$setting}"));
                    }
                }
            });
        }
    }
}
