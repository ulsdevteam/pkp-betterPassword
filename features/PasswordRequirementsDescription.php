<?php

/**
 * @file plugins/generic/betterPassword/features/PasswordRequirementsDescription.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class PasswordRequirementsDescription
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Renders the full list of active password requirements next to the
 *        password input on every form that lets a user set a password.
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\core\Application;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\plugins\Hook;

class PasswordRequirementsDescription
{
    /** HTML class on the injected block — also used as an idempotency marker. */
    private const MARKER_CLASS = 'better-password-requirements';

    /** @var BetterPasswordPlugin */
    private $_plugin;

    /** @var bool Whether the Smarty output filter has been registered this request. */
    private $_filterRegistered = false;

    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_plugin = $plugin;

        $hooks = [
            'registrationform::display',
            'changepasswordform::display',
            'loginchangepasswordform::display',
            'resetpasswordform::display',
            'userdetailsform::display',
        ];
        foreach ($hooks as $hook) {
            Hook::add($hook, [$this, 'registerOutputFilter']);
        }
    }

    /**
     * Lazily register the Smarty output filter the first time any of the
     * password-setting forms is being rendered this request.
     */
    public function registerOutputFilter(string $hookName, array $args): bool
    {
        if ($this->_filterRegistered) {
            return false;
        }
        $this->_filterRegistered = true;
        TemplateManager::getManager()->registerFilter('output', [$this, 'inject']);
        return false;
    }

    /**
     * Smarty output filter: prepend the requirements block to the first
     * `name="password"` input it finds. Idempotent within a render via the
     * marker class.
     */
    public function inject(string $output, $smarty): string
    {
        if (str_contains($output, self::MARKER_CLASS)
            || !preg_match('/<input\b[^>]*\bname="password"/', $output)
        ) {
            return $output;
        }

        $items = $this->_buildRequirementItems();
        if (!$items) {
            return $output;
        }

        $listItems = '';
        foreach ($items as $item) {
            $listItems .= '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $heading = htmlspecialchars(
            __('plugins.generic.betterPassword.description.requirementsHeading'),
            ENT_QUOTES,
            'UTF-8'
        );

        // Two simple rules:
        //   1. hide the FBV sub-label's default "must be at least N" text,
        //      since the requirements block above already covers it. Scoped
        //      with the marker class as a sibling so only the password
        //      field's sub-label is touched, never the confirm field's.
        //      `:not(.error)` keeps actual validation errors visible.
        //   2. give the repeat-password input a hard 24px top margin so
        //      there is always a visible gap between it and the password
        //      input above, regardless of whether the sub-label is empty,
        //      collapsed by OJS JS, or holding an error message.
        $marker = self::MARKER_CLASS;
        $css = ".{$marker} ~ span > label.sub_label:not(.error){display:none}"
            . 'input[name="password2"]{margin-top:24px}';
        $block = '<div class="' . $marker . '">'
            . '<style>' . $css . '</style>'
            . '<p>' . $heading . '</p>'
            . '<ul>' . $listItems . '</ul>'
            . '</div>';

        return preg_replace(
            '/(<input\b[^>]*\bname="password"[^>]*>)/',
            $block . '$1',
            $output,
            1
        );
    }

    /**
     * Build the list of human-readable requirement strings reflecting the
     * current site-level plugin settings.
     *
     * @return string[]
     */
    private function _buildRequirementItems(): array
    {
        $contextSite = PKPApplication::CONTEXT_SITE;
        $items = [];

        $minLength = (int) Application::get()->getRequest()->getSite()->getMinPasswordLength();
        if ($minLength > 0) {
            $items[] = __('plugins.generic.betterPassword.requirement.length', ['length' => $minLength]);
        }

        $complexityChecks = [
            'betterPasswordCheckAlpha' => 'plugins.generic.betterPassword.requirement.alpha',
            'betterPasswordCheckNumber' => 'plugins.generic.betterPassword.requirement.number',
            'betterPasswordCheckUppercase' => 'plugins.generic.betterPassword.requirement.uppercase',
            'betterPasswordCheckLowercase' => 'plugins.generic.betterPassword.requirement.lowercase',
            'betterPasswordCheckSpecial' => 'plugins.generic.betterPassword.requirement.special',
        ];
        foreach ($complexityChecks as $setting => $key) {
            if ($this->_plugin->getSetting($contextSite, $setting)) {
                $items[] = __($key);
            }
        }

        if ($this->_plugin->getSetting($contextSite, 'betterPasswordCheckBlacklist')) {
            $items[] = __('plugins.generic.betterPassword.requirement.blocklist');
        }

        $reuseCount = (int) $this->_plugin->getSetting($contextSite, 'betterPasswordInvalidationPasswords');
        if ($reuseCount > 0) {
            $items[] = __('plugins.generic.betterPassword.requirement.noReuse', ['count' => $reuseCount]);
        }

        return $items;
    }
}
