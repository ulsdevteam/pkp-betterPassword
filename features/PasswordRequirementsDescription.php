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
     * Smarty output filter: insert the requirements block above the first
     * `name="password"` input. Idempotent within a render via the marker
     * class.
     *
     * Two render contexts to handle:
     *   - Full-page renders (the public `Register` page): the filter sees
     *     the whole document, so a naive prepend would land the block
     *     before `<!doctype>` / `<head>`. We locate the input's nearest
     *     enclosing `<div>` and insert just before it.
     *   - Form-fragment renders (Add User, Change Password, Reset
     *     Password, Login-Change-Password): the filter sees only the form
     *     HTML, and the FBV layouts use side-by-side `pkp_helpers_half`
     *     columns. Splicing in front of the wrapping `<div>` would land
     *     the block inside one of those columns; prepend to the fragment
     *     instead so it spans the full row above the inputs.
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

        // Scoped via input-name selectors so the rules apply regardless of
        // where the requirements block sits relative to the password input
        // (inside its wrapper, or as a sibling above it):
        //   1. hide the FBV default sub-label text on the new-password
        //      input, since the requirements list above already covers it.
        //      `:not(.error)` keeps validation errors visible.
        //   2. give the repeat-password input a 24px top margin in stacked
        //      layouts (Change Password tab) so it doesn't touch the field
        //      above it. `:not(.inline)` skips Add User's side-by-side
        //      layout where the two inputs sit on the same row.
        // SecurityRules joins the per-requirement labels with `\n` so they can
        // render on separate lines. The PKP form-error injection HTML-escapes
        // the message before putting it in the DOM, so `<br>` would render
        // literally. `white-space:pre-line` on the error containers preserves
        // the `\n` characters as actual line breaks instead.
        $marker = self::MARKER_CLASS;
        $css = 'input[name="password"] + span > label.sub_label:not(.error){display:none}'
            . '.pkp_helpers_half:not(.inline) input[name="password2"]{margin-top:24px}'
            . 'label.sub_label.error,.notifyFormError .description{white-space:pre-line}'
            // Tighten the gap above the password field on the public Register
            // page: the block sits as a sibling inside `<div class="fields">`,
            // which applies `padding-bottom:2.143rem` to each direct child
            // via `.cmp_form .fields > div`, and its `<ul>` carries the
            // browser-default bottom margin. Both selectors are scoped so
            // the surrounding form layout (e.g. the Add User modal, where
            // the block isn't inside .fields) is unaffected. The padding
            // selector mirrors the OJS rule's class count so it wins
            // specificity.
            . '.cmp_form .fields > .' . $marker . '{padding-bottom:0}'
            . '.' . $marker . ' ul{margin-bottom:10px}';
        $block = '<div class="' . $marker . '">'
            . '<style>' . $css . '</style>'
            . '<p>' . $heading . '</p>'
            . '<ul>' . $listItems . '</ul>'
            . '</div>';

        // Strip the hardcoded leading whitespace inside the error label generated by
        // OJS core templates. This prevents `white-space: pre-line` from turning
        // that template formatting into a visual gap at the top of the red border.
        $output = preg_replace('/(<label[^>]*class="[^"]*\bsub_label error\b[^"]*"[^>]*>)\s+/', '$1', $output);

        // Form-fragment render (Add User et al.): fall back to the
        // original prepend so the existing layout is unchanged.
        if (!preg_match('/^\s*<(?:!doctype\b|html\b)/i', $output)) {
            return $block . $output;
        }

        // Full-page render (public Register page): insert in front of
        // the password input's nearest enclosing <div>.
        if (!preg_match('/<input\b[^>]*\bname="password"/', $output, $m, PREG_OFFSET_CAPTURE)) {
            return $output;
        }
        $insertPos = $this->_findWrappingDivStart($output, $m[0][1]) ?? $m[0][1];

        return substr($output, 0, $insertPos) . $block . substr($output, $insertPos);
    }

    /**
     * Walk back from $inputPos to find the start offset of the nearest
     * unclosed `<div>` — i.e. the innermost wrapper containing the password
     * input. Returns null if there is no enclosing `<div>` in $output.
     */
    private function _findWrappingDivStart(string $output, int $inputPos): ?int
    {
        $before = substr($output, 0, $inputPos);
        if (!preg_match_all('/<(\/?)div\b[^>]*>/', $before, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $depth = 0;
        for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
            if ($matches[1][$i][0] === '/') {
                $depth++;
            } elseif ($depth === 0) {
                return $matches[0][$i][1];
            } else {
                $depth--;
            }
        }
        return null;
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
