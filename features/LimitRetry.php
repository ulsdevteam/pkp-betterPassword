<?php

/**
 * @file plugins/generic/betterPassword/features/LimitRetry.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class LimitRetry
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to limit retries and lock the account
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use APP\plugins\generic\betterPassword\classes\BadpwFailedLoginsDAO;
use APP\plugins\generic\betterPassword\handlers\DisabledLoginHandler;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\Validation;

class LimitRetry
{
    /** @var int Max amount of retries */
    private $_maxRetries;

    /** @var int Amount of seconds to lock account */
    private $_lockSeconds;

    /** @var int Max amount of retries */
    private $_lockExpiresSeconds;

    /**
     * Constructor
     */
    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_maxRetries = $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordLockTries');
        if (!$this->_maxRetries) {
            return;
        }

        $this->_lockSeconds = $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordLockSeconds');
        $this->_lockExpiresSeconds = $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordLockExpires');
        $this->_handleTemplateDisplay();
        $this->registerLoadHandler();
        $this->_addResetPasswordReset();
    }

    /**
     * Register callback to add text to registration page
     */
    private function _handleTemplateDisplay(): void
    {
        Hook::add('TemplateManager::display', function ($hook, $args) {
            /** @var TemplateManager $templateManager */
            [$templateManager, $template] = $args;
            if ($template !== 'frontend/pages/userLogin.tpl' || $templateManager->getTemplateVars('error') !== 'user.login.loginError') {
                return;
            }
            $username = $templateManager->getTemplateVars('username');
            if (!$username) {
                return;
            }
            /** @var BadpwFailedLoginsDAO */
            $badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
            $user = $badpwFailedLoginsDao->getByUsername($username);
            if (!$user) {
                return;
            }
            $count = $user->getCount();
            $time = $user->getFailedTime();

            // Discard old bad password attempts
            // When the memory has expired
            if ($count && $time < time() - $this->_lockExpiresSeconds) {
                // And the user is not currently locked
                if ($user->getCount() < $this->_maxRetries || $user->getFailedTime() <= time() - $this->_lockSeconds) {
                    $badpwFailedLoginsDao->resetCount($user);
                }
            }

            // Update the count to represent this failed attempt
            $badpwFailedLoginsDao->incCount($user);

            // Warn the user if the attempts have been exhausted
            if ($count >= $this->_maxRetries) {
                $templateManager->assign('error', 'plugins.generic.betterPassword.validation.betterPasswordLocked');
            }
        });
    }

    /**
     * Register a hook for LoadHandler
     */
    public function registerLoadHandler()
    {
        Hook::add('LoadHandler', [$this, 'setLoadHandler']);
    }

    /**
     * Create a new DisabledLoginHandler
     *
     * @param string $hookname The hook name
     * @param array $args Arguments of the hook
     *
     * @return bool true if DiasabledLogin handler successfully created
     */
    public function setLoadHandler(string $hookname, array $args): bool
    {
        $page = &$args[0];
        $operation = $args[1];
        $handler = & $args[3];
        $username = $_POST['username'] ?? null;
        if ([$page, $operation] !== ['login', 'signIn'] || !$username) {
            return false;
        }

        /** @var BadpwFailedLoginsDAO */
        $badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
        $user = $badpwFailedLoginsDao->getByUsername($username);
        if (!$user || $user->getCount() < $this->_maxRetries || $user->getFailedTime() <= time() - $this->_lockSeconds) {
            return false;
        }

        $handler = new DisabledLoginHandler();
        return true;
    }


    /**
     * Register a hook to reset the retry count after resetting the password
     */
    private function _addResetPasswordReset(): void
    {
        Hook::add('LoadHandler', function ($hook, $args) {
            $page = &$args[0];
            $operation = $args[1];
            if ([$page, $operation] !== ['login', 'resetPassword']) {
                return;
            }

            $request = Application::get()->getRequest();
            $username = array_shift($request->getRequestedArgs());
            $confirmHash = $request->getUserVar('confirm');
            $user = Repo::user()->getByUsername($username);
            if ($user && $confirmHash && Validation::verifyPasswordResetHash($user->getId(), $confirmHash)) {
                /** @var BadpwFailedLoginsDAO */
                $badpwFailedLoginsDao = DAORegistry::getDAO('BadpwFailedLoginsDAO');
                $user = $badpwFailedLoginsDao->getByUsername($username);
                $badpwFailedLoginsDao->resetCount($user);
            }
        });
    }
}
