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
		$this->_passwordLifetime = (int) $plugin->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationDays');
		$this->_warningDays = (int) $plugin->getSetting(CONTEXT_SITE, 'betterPasswordInvalidationMininumWarningDays');
		if (!$this->_passwordLifetime) {
			return;
		}

		$this->_addPasswordExpirationCheck();
	}

	/**
	 * Register callback to detect if the user password has expired
	 */
	private function _addPasswordExpirationCheck() : void {
		foreach (['LoadHandler', 'LoadComponentHandler', 'TemplateManager::display'] as $hook) {
			HookRegistry::register('LoadHandler', function ($hook, $args) {
				if ($this->_isProcessed) {
					return;
				}
				$this->_isProcessed = true;
				$user = Application::get()->getRequest()->getUser();
				$username = $_POST['username'] ?? null;
				if (!$user && $username) {
					/** @var UserDAO */
					$userDao = DAORegistry::getDAO('UserDAO');
					$user = $userDao->getByUsername($username);
				}
				if (!$user) {
					return;
				}

				if (!$this->_isPasswordExpired($user)) {
					$this->_handleNotification($user);
					return;
				} 

				if (!$user->getMustChangePassword()) {
					$user->setMustChangePassword(true);
					$userDao->updateObject($user);
				}
			});
		}
	}

	/**
	 * Retrieve the password expiration date
	 * @param User $user
	 * @return DateTime
	 */
	private function _getExpirationDate(User $user) : DateTime {
		$expirationDate = new DateTime($user->getData("{$this->_plugin->getName()}::lastPasswordUpdate") ?: $user->getDateRegistered());
		$expirationDate->modify("{$this->_passwordLifetime} day");
		return $expirationDate;
	}

	private function _handleNotification(User $user) : void {
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
			$notificationDao->deleteObject($notification);
		}

		$notification = $notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_WARNING, array('contents' => __('plugins.generic.betterPassword.message.yourPasswordWillExpire', ['days' => $diffInDays])));
		$lastNotification = (object) ['id' => $notification->getId(), 'days' => $diffInDays];
		
		$this->_setLastNotification($user, $lastNotification);
		/** @var UserDAO */
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->updateObject($user);
	}

	/**
	 * Retrieve if the password is expired
	 * @param User $user
	 * @return bool
	 */
	private function _isPasswordExpired(User $user) : bool {
		return $this->_getExpirationDate($user)->getTimestamp() <= time();
	}

	/**
	 * Retrieve the last notification
	 * @param User $user
	 * @return ?string
	 */
	private function _getLastNotification(User $user) : ?object {
		return json_decode($user->getData("{$this->_plugin->getName()}::lastPasswordNotification"), false);
	}
	
	/**
	 * Set last notification
	 * @param User $user
	 * @param string $password
	 */
	private function _setLastNotification(User $user, ?object $notification) : void {
		$user->setData("{$this->_plugin->getName()}::lastPasswordNotification", json_encode($notification));
	}
}
