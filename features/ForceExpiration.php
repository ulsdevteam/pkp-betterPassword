<?php

/**
 * @file plugins/generic/betterPassword/features/ForceExpiration.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class ForceExpiration
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to force the expiration of passwords
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\notification\Notification;

class ForceExpiration
{
    /** @var BetterPasswordPlugin */
    private $_plugin;

    /** @var int Password lifetime in days */
    private $_passwordLifetime;

    /** @var int Amount of days to keep notifying the user */
    private $_warningDays;

    /** @var bool Controls whether this handler has been already processed */
    private $_isProcessed;

    /**
     * Constructor
     */
    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_plugin = $plugin;
        $this->_passwordLifetime = (int) $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordInvalidationDays');
        $this->_warningDays = (int) $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordInvalidationMininumWarningDays');
        if (!$this->_passwordLifetime) {
            return;
        }
        Hook::add('changepasswordform::execute', [$this, 'rememberPasswordDate']);
        Hook::add('loginchangepasswordform::execute', [$this, 'rememberPasswordDate']);
        $this->_addPasswordExpirationCheck();
    }

    /**
     * Register callback to detect if the user password has expired
     */
    private function _addPasswordExpirationCheck(): void
    {
        $user = Application::get()->getRequest()->getUser();
        $session = Application::get()->getRequest()->getSession();
        if ($user) {
            if (!$this->_isPasswordExpired($user)) {
                if ($this->_isPasswordExpiring($user)) {
                    if (!$session->get('betterPassword::showedLastNotification')) {
                        $notificationManager = new NotificationManager();
                        $expirationDate = $this->_getExpirationDate($user);
                        $diffInDays = ceil(($expirationDate->getTimestamp() - time()) / 60 / 60 / 24);
                        $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_WARNING, ['contents' => __('plugins.generic.betterPassword.message.yourPasswordWillExpire', ['days' => $diffInDays])]);
                        $session->put('betterPassword::showedLastNotification', true);
                    }
                }
            } else {
                $user->setMustChangePassword(true);
                Repo::user()->edit($user);
            }
        } else {
            //happens on logout
            $session->remove('betterPassword::showedLastNotification');
        }
    }

    public function _isPasswordExpiring(\PKP\user\User $user): bool
    {
        $expirationDate = $this->_getExpirationDate($user);
        if (!$this->_warningDays || $expirationDate->getTimestamp() <= time()) {
            return false;
        }

        $diffInDays = ceil(($expirationDate->getTimestamp() - time()) / 60 / 60 / 24);
        if ($diffInDays > $this->_warningDays) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve the password expiration date
     *
     * @return \DateTime Expiration date
     */
    private function _getExpirationDate(\PKP\user\User $user): \DateTime
    {
        /** @var \APP\plugins\generic\betterPassword\classes\StoredPasswordsDAO $storedPasswordsDao  */
        $storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
        $storedPasswords = $storedPasswordsDao->getByUserId($user->getId());

        $mostRecent = $storedPasswords->getChangeTime();
        if (!$mostRecent) {
            $mostRecent = new \DateTime($user->getDateRegistered());
        }

        $mostRecent->modify("{$this->_passwordLifetime} day");
        return $mostRecent;
    }

    /**
     * Updates the time of the users last password change
     *
     * @param string $hook The hook name
     * @param array $args Arguments of the hook
     *
     * @return bool false if process completes
     */
    public function rememberPasswordDate($hook, $args)
    {
        if ($hook == 'changepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\ChangePasswordForm') {
            $user = $args[0]->getUser();
        } elseif ($hook == 'loginchangepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\LoginChangePasswordForm') {
            $user = Repo::user()->getByUsername($args[0]->getData('username'), false);
        } else {
        }

        /** @var \APP\plugins\generic\betterPassword\classes\StoredPasswordsDAO $storedPasswordsDao  */
        $storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
        if ($user) {
            $storedPasswords = $storedPasswordsDao->getByUserId($user->getId());
            if ($storedPasswords) {
                $storedPasswords->setChangeTime(new \DateTime('now'));
                $storedPasswordsDao->update($storedPasswords);
            } else {
                $storedPasswords = $storedPasswordsDao->newDataObject();
                $storedPasswords->setUserId($user->getId());
                $storedPasswords->setChangeTime(now());
                $storedPasswordsDao->insert($storedPasswords);
            }
        }
        return false;
    }

    /**
     * Retrieve if the password is expired
     */
    private function _isPasswordExpired(\PKP\user\User $user): bool
    {
        return $this->_getExpirationDate($user)->getTimestamp() <= time();
    }
}
