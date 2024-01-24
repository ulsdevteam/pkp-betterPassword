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
				/** @var UserDAO */
				//$userDao = DAORegistry::getDAO('UserDAO');
				if (!$user) {
					return;
				}

				if (!$this->_isPasswordExpired($user)) {
					$this->_handleNotification($user);
					return;
				} 

				if (!$user->getMustChangePassword()) {
					$user->setMustChangePassword(true);
					//$userDao->updateObject($user);
					Repo::user()->edit($user);
				}
			});
		}
	}

	/**
	 * Retrieve the password expiration date
	 * @param User $user
	 * @return DateTime
	 */
	private function _getExpirationDate(\User $user) : \DateTime {
		/* @var $storedPasswordsDao StoredPasswordsDAO */
		$storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
		$storedPasswords = $storedPasswordsDao->placeholder($user->getId());
		$mostRecent = new \DateTime ($storedPasswordsDao->getMostRecent($storedPasswords));
		if (!$mostRecent) {
			$mostRecent = new \DateTime ($user->getDateRegistered());
		}

		//TODO convert $mostRecent from a string to a dateTime
		//$expirationDate = new DateTime($user->getData("{$this->_plugin->getSettingsName()}::lastPasswordUpdate") ?: $user->getDateRegistered());
		$mostRecent->modify("{$this->_passwordLifetime} day");
		return $mostRecent;
	}

	public function rememberPasswordDate($hook, $args) {
		if ($hook == 'changepasswordform::execute' && get_class($args[0]) == 'PKP\user\form\ChangePasswordForm') {
			$user = $args[0]->getUser();
			/* @var $storedPasswordsDao StoredPasswordsDAO */
			$storedPasswordsDao = DAORegistry::getDAO('StoredPasswordsDAO');
			$storedPasswords = $storedPasswordsDao->placeholder($user->getId());
			$storedPasswordsDao->updateDate($storedPasswords); //may need to store this in a variable
		}
		return false;
	}

	private function _handleNotification(\User $user) : void {
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

		$notification = $notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_WARNING, array('contents' => __('plugins.generic.betterPassword.message.yourPasswordWillExpire', ['days' => $diffInDays])));
		$lastNotification = (object) ['id' => $notification->getId(), 'days' => $diffInDays];
		
		$this->_setLastNotification($user, $lastNotification);
		/** @var UserDAO */
		//$userDao = DAORegistry::getDAO('UserDAO');
		//$userDao->updateObject($user);
		$user = Repo::user()->edit($user);
		
	}

	/**
	 * Retrieve if the password is expired
	 * @param User $user
	 * @return bool
	 */
	private function _isPasswordExpired(\User $user) : bool {
		return $this->_getExpirationDate($user)->getTimestamp() <= time();
		//return true;
	}

	/**
	 * Retrieve the last notification
	 * @param User $user
	 * @return ?string
	 */
	private function _getLastNotification(\User $user) : ?object {
		return json_decode($user->getData("{$this->_plugin->getSettingsName()}::lastPasswordNotification"), false);
	}
	
	/**
	 * Set last notification
	 * @param User $user
	 * @param string $password
	 */
	private function _setLastNotification(\User $user, ?object $notification) : void {
		$user->setData("{$this->_plugin->getSettingsName()}::lastPasswordNotification", json_encode($notification));
	}
}
