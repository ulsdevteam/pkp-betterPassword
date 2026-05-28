<?php

/**
 * @file plugins/generic/betterPassword/features/SecurityRules.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class SecurityRules
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to force the password to follow some security rules
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\core\Application;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\plugins\Hook;

class SecurityRules
{
    /** @var BetterPasswordPlugin */
    private $_plugin;

    /** @var array List of validations: [settingName, regex, requirementLocaleKey] */
    private const VALIDATIONS = [
        ['betterPasswordCheckAlpha', '/[[:alpha:]]/', 'plugins.generic.betterPassword.requirement.alpha'],
        ['betterPasswordCheckNumber', '/\d/', 'plugins.generic.betterPassword.requirement.number'],
        ['betterPasswordCheckUppercase', '/[[:upper:]]/', 'plugins.generic.betterPassword.requirement.uppercase'],
        ['betterPasswordCheckLowercase', '/[[:lower:]]/', 'plugins.generic.betterPassword.requirement.lowercase'],
        ['betterPasswordCheckSpecial', '/[^[:alnum:]]/', 'plugins.generic.betterPassword.requirement.special'],
    ];

    /**
     * Constructor
     */
    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_plugin = $plugin;
        if (!$this->_hasAnyRule()) {
            return;
        }

        $this->_addPasswordValidation();
    }

    /**
     * Whether any complexity rule is enabled.
     */
    private function _hasAnyRule(): bool
    {
        foreach (self::VALIDATIONS as [$setting]) {
            if ($this->_plugin->getSetting(PKPApplication::CONTEXT_SITE, $setting)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the short labels (e.g. "an uppercase character") for every
     * enabled complexity requirement, in the order they appear in VALIDATIONS.
     *
     * @return string[]
     */
    public function getEnabledRequirementLabels(): array
    {
        $labels = [];
        foreach (self::VALIDATIONS as [$setting, , $requirementKey]) {
            if ($this->_plugin->getSetting(PKPApplication::CONTEXT_SITE, $setting)) {
                $labels[] = __($requirementKey);
            }
        }
        return $labels;
    }

    /**
     * Register a hook to validate passwords against the complexity rules.
     */
    private function _addPasswordValidation(): void
    {
        $hooks = [
            'registrationform::validate',
            'changepasswordform::validate',
            'loginchangepasswordform::validate',
            'resetpasswordform::validate',
            'userdetailsform::validate',
        ];
        foreach ($hooks as $hook) {
            Hook::add($hook, function ($hook, $args) {
                /** @var \PKP\form\Form $form */
                [$form] = $args;
                $password = $form->getData('password');
                // Skip when the form leaves the password empty (editing a user
                // without changing their password, or admin-generated passwords).
                if (!$password || $form->getData('generatePassword')) {
                    return;
                }

                $failedLabels = [];

                // Include the site's minimum-length rule so it appears in the
                // combined message instead of as a separate (and only) error.
                $minLength = (int) Application::get()->getRequest()->getSite()->getMinPasswordLength();
                if ($minLength > 0 && strlen($password) < $minLength) {
                    $failedLabels[] = __('plugins.generic.betterPassword.requirement.length', ['length' => $minLength]);
                }

                foreach (self::VALIDATIONS as [$setting, $rule, $requirementKey]) {
                    if ($this->_plugin->getSetting(PKPApplication::CONTEXT_SITE, $setting)
                        && !PKPString::regexp_match($rule, $password)
                    ) {
                        $failedLabels[] = __($requirementKey);
                    }
                }

                if (!$failedLabels) {
                    return;
                }

                // Form::validate() runs validators in order and skips later
                // checks on a field that already has an error, so the form's
                // own length error would otherwise be the *only* thing shown
                // for the password field. Drop any pre-existing 'password'
                // errors so our combined message becomes the visible one.
                $form->_errors = array_values(array_filter(
                    $form->_errors ?? [],
                    fn ($err) => $err->getField() !== 'password'
                ));
                $form->addError(
                    'password',
                    __('plugins.generic.betterPassword.validation.requirementsNotMet', [
                        'requirements' => implode("\n", $failedLabels),
                    ])
                );
                // addError() only populates _errors; the inline red message
                // beneath the field is gated by errorFields[$field], which the
                // core validator loop sets but addError() does not. Without
                // this, our combined message would only show in the top-of-
                // form notification when length passed (because nothing else
                // had flagged the field).
                $form->addErrorField('password');
            });
        }
    }
}
