<?php

/**
 * @file plugins/generic/betterPassword/features/ForceExpiration.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class ForceExpiration
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the feature to force the expiration of passwords
 */
namespace APP\plugins\generic\betterPassword\features;

use PKP\plugins\Hook;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use APP\notification\NotificationManager;
use APP\core\Application;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use APP\facades\Repo;

class ForceExpiration {
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
	 * @param BetterPasswordPlugin $plugin
	 */
	public function __construct(BetterPasswordPlugin $plugin) {
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
	private function _addPasswordExpirationCheck() : void {
		foreach (['LoadHandler', 'LoadComponentHandler', 'TemplateManager::display'] as $hook) {
			Hook::add('LoadHandler', function ($hook, $args) {
				if ($this->_isProcessed) {
					return;
				}
				$this->_isProcessed = true;
				$user = Application::get()->getRequest()->getUser();
				$username = $_POST['username'] ?? null;
				if (!$user) {
					return;
				}

				if (!$this->_isPasswordExpired($user)) {
					$this->_handleNotification($user);
					return;
				}

				if (!$user->getMustChangePassword()) {
					$user->setMustChangePassword(true);
					Repo::user()->edit($user);
				}
			});
		}
	}

	/**
	 * Retrieve the password expiration date
	 * @param User $user
	 * @return DateTime Most recent time of password change
	 */
	private function _getExpirationDate(\PKP\user\User $user) : \DateTime {
		/* @var $storedPasswordsDao StoredPasswordsDAO */
		$storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
		$storedPasswords = $storedPasswordsDao->newDataObjects($user->getId());
		$mostRecent = new \DateTime ($storedPasswordsDao->getMostRecent($storedPasswords));
		if (!$mostRecent) {
			$mostRecent = new \DateTime ($user->getDateRegistered());
		}

		$mostRecent->modify("{$this->_passwordLifetime} day");
		return $mostRecent;
	}

	/**
	 * Updates the time of the users last password change
	 * @param string $hook The hook name
	 * @param array $args Arguments of the hook
	 * @return bool false if process completes
	 */
	public function rememberPasswordDate($hook, $args) {
		if ($hook == 'changepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\ChangePasswordForm') {
			$user = $args[0]->getUser();
		}
		elseif ($hook == 'loginchangepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\LoginChangePasswordForm') {
			$user = Repo::user()->getByUsername($args[0]->getData('username'), false);
		}
		else {
		}

		$storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
		if ($user) {
			$storedPasswords = $storedPasswordsDao->getByUserId($user->getId());
			if ($storedPasswords) {
				$storedPasswords->setChangeTime(new \DateTime ('now'));
				$storedPasswordsDao->update($storedPasswords);
			}
			else {
				$storedPasswords = $storedPasswordsDao->newDataObjects($user->getId());
				$storedPasswordsDao->insert($storedPasswords);
			}
		}
		return false;
	}

	/**
	 * Checks the expiration date for a users password and creates a notification for them to change it if expired
	 * @param User $user Given user
	 */
	private function _handleNotification(\PKP\user\User $user) : void {
		$expirationDate = $this->_getExpirationDate($user);
		if (!$this->_warningDays || $expirationDate->getTimestamp() <= time()) {
			return;
		}

		$diffInDays = ceil(($expirationDate->getTimestamp() - time()) / 60 / 60 / 24);
		if ($diffInDays > $this->_warningDays) {
			return;
		}

		$notificationManager = new NotificationManager();
		$lastNotification = $this->_getLastNotification($user);
		if ($lastNotification) {
			if ($lastNotification->days == $diffInDays) {
				return;
			}
			
			/* @var $notificationDao NotificationDAO */
			$notificationDao = DAORegistry::getDAO('NotificationDAO');
			$notification = $notificationDao->getById($lastNotification->id, $user->getId());
			if ($notification) {
				$notificationDao->delete($notification);
			}
		}
		$notification = $notificationManager->createTrivialNotification($user->getId(), \PKP\notification\PKPNotification::NOTIFICATION_TYPE_WARNING, array('contents' => __('plugins.generic.betterPassword.message.yourPasswordWillExpire', ['days' => $diffInDays])));
		$lastNotification = (object) ['id' => $notification->getId(), 'days' => $diffInDays];
		$this->_setLastNotification($user, $lastNotification);
		$user = Repo::user()->edit($user);
	}

	/**
	 * Retrieve if the password is expired
	 * @param User $user
	 * @return bool
	 */
	private function _isPasswordExpired(\PKP\user\User $user) : bool {
		return $this->_getExpirationDate($user)->getTimestamp() <= time();
	}

	/**
	 * Retrieve the last notification
	 * @param User $user
	 * @return ?object Last Notification
	 */
	private function _getLastNotification(\PKP\user\User $user) : ?object {
		return json_decode($user->getData("{$this->_plugin->getSettingsName()}::lastPasswordNotification"), false);
	}
	
	/**
	 * Set last notification
	 * @param User $user
	 * @param ?object Last Notification
	 */
	private function _setLastNotification(\PKP\user\User $user, ?object $notification) : void {
		$user->setData("{$this->_plugin->getSettingsName()}::lastPasswordNotification", json_encode($notification));
	}
}
