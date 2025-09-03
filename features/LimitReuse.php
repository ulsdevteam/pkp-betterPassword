<?php

/**
 * @file plugins/generic/betterPassword/features/LimitReuse.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class LimitReuse
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to disable reusing old passwords
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\facades\Repo;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\form\validation\FormValidatorCustom;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\user\User;

class LimitReuse
{
    /** @var BetterPasswordPlugin */
    private $_plugin;

    /** @var int Max reusable passwords */
    private $_maxReusablePasswords;

    /** @var bool Flag to avoid an infinite loop in the handler */
    private $_handledPasswordUpdate;

    /**
     * Constructor
     */
    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_plugin = $plugin;
        $this->_maxReusablePasswords = (int) $plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordInvalidationPasswords');
        if (!$this->_maxReusablePasswords) {
            return;
        }

        $userDao = Repo::user()->dao;
        Hook::add('changepasswordform::execute', [$this, 'rememberPasswords']);
        Hook::add('loginchangepasswordform::execute', [$this, 'rememberPasswords']);
        Hook::add('resetpasswordform::execute', [$this, 'rememberPasswords']);
        Hook::add('loginchangepasswordform::Constructor', [$this, 'passwordChangeValidation']);
        Hook::add('changepasswordform::Constructor', [$this, 'passwordChangeValidation']);
        Hook::add('resetpasswordform::Constructor', [$this, 'passwordChangeValidation']);
    }

    /**
     * Checks the current form and remembers the user's passwords
     *
     * @param string $hook The hook name
     * @param array $args Arguments of the hook
     *
     * @return bool false if process completes
     */
    public function rememberPasswords($hook, $args)
    {
        //user is considered authenticated for all reset forms except when password has been marked expired by this plugin or manually by an admin
        if ($hook == 'loginchangepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\LoginChangePasswordForm') {
            $user = Repo::user()->getByUsername($args[0]->getData('username'), false);
        }
        else {
            $user = $args[0]->getUser();
        }
        $password = $user->getData('password');
        $this->_addPassword($user, $password);
        return false;
    }

    /**
     * Checks the current form and checks if the user's inputted password has been used previously
     *
     * @param string $hook The hook name
     * @param array $args Arguments of the hook
     *
     * @return bool true if process completes
     */
    public function passwordChangeValidation($hook, $args)
    {
        $form = $args[0];
        $newCheck = new FormValidatorCustom($form, 'password', 'required', 'plugins.generic.betterPassword.validation.betterPasswordPasswordReused', function ($password) use ($form) {
            return $this->passwordCompare($password, $form);
        });
        $form->addCheck($newCheck);
        return false;
    }

    /**
     * Compares the user's password to prior passwords
     *
     * @param string $password The user's given password
     * @param \PKP\user\form\ChangePasswordForm $form The form for changing passwords
     *
     * @return bool false if the user's password has been reused and true if it has not
     */
    public function passwordCompare($password, $form)
    {
        $formClassName = get_class($form);
        switch ($formClassName) {
            case $formClassName === 'PKP\user\form\ResetPasswordForm':
                $user = $form->getUser();
                break;
            case $formClassName === 'PKP\user\form\LoginChangePasswordForm':
                $user = Repo::user()->getByUsername($form->getData('username'));
                break;
            case $formClassName === 'PKP\user\form\ChangePasswordForm':
                $user = $form->_user;
                break;
        }

        /** @var \APP\plugins\generic\betterPassword\classes\StoredPasswordsDAO $storedPasswordsDao */
        $storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
        if ($user) {
            $storedPasswords = $storedPasswordsDao->getByUserId($user->getId());
        } else {
            $storedPasswords = null;
        }

        if ($storedPasswords) {
            foreach ($storedPasswords->getPasswords($this->_maxReusablePasswords) as $previousPassword) {
                //if $password matches the hash of a previously-used password we've stored, return false
                if (Validation::verifyPassword($user->getUsername(), $password, $previousPassword, $rehash)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Add a user password to the list of blocked passwords and refresh the last updated timestamp
     *
     * @param User $user The given user
     * @param string $password The given password
     */
    private function _addPassword(User $user, string $password): void
    {
        /** @var \APP\plugins\generic\betterPassword\classes\StoredPasswordsDAO $storedPasswordsDao */
        $storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
        $storedPasswords = $storedPasswordsDao->getByUserId($user->getId());
        if ($storedPasswords) {
            $totalPasswords = $storedPasswords->getPasswords($this->_maxReusablePasswords);
            if (count($totalPasswords) < $this->_maxReusablePasswords) {
                array_push($totalPasswords, $password);
                $storedPasswords->setPasswords($totalPasswords, true);
                $storedPasswordsDao->update($storedPasswords);
            } else {
                array_shift($totalPasswords);
                array_push($totalPasswords, $password);
                $storedPasswords->setPasswords($totalPasswords, true);
                $storedPasswordsDao->update($storedPasswords);
            }
        } else {
            $storedPasswords = $storedPasswordsDao->newDataObject();
            $storedPasswords->setUserId($user->getId());
            $totalPasswords = [$password];
            $storedPasswords->setPasswords($totalPasswords, true);
            $storedPasswordsDao->insert($storedPasswords);
        }
    }
}
