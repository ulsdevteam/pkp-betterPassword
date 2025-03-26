<?php

/**
 * @file plugins/generic/betterPassword/features/Blocklist.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class Blocklist
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the blocklist feature
 */

class Blocklist {
	/** @var BetterPasswordPlugin */
	private $_plugin;

	/** @var string Context which will keep the cached hashes */
	private const CACHE_CONTEXT = 'badPasswords';

	/**
	 * Constructor
	 * @param BetterPasswordPlugin $plugin
	 */
	public function __construct(BetterPasswordPlugin $plugin) {
		$this->_plugin = $plugin;
		// Enable the file handler to be used by the settings form
		$this->_registerBlocklistFileHandler();
		if (!(bool) $plugin->getSetting(CONTEXT_SITE, 'betterPasswordCheckBlacklist')) {
			return;
		}

		$this->_addPasswordValidation();
	}

	/**
	 * Clear the password cache
	 */
	public static function clearCache() : void {
		CacheManager::getManager()->flush(self::CACHE_CONTEXT);
	}

	/**
	 * Register a hook to validate passwords against a blocked list
	 */
	private function _addPasswordValidation() : void {
		// Register callback to validate new passwords
		foreach (['registrationform::validate', 'changepasswordform::validate', 'loginchangepasswordform::validate'] as $hook) {
			HookRegistry::register($hook, function ($hook, $args) {
				/** @var Form $form */
				[$form] = $args;
				$passwordField = 'password';
				$password = $form->getData($passwordField);
				// Let the form itself handle the core required function
				if (!$password) {
					return;
				}

				$passwordHash = sha1(strtolower($password));
				$hashGroup = substr($passwordHash, 0, 2);
				$isBlockedPassword = CacheManager::getManager()
					->getCache(
						self::CACHE_CONTEXT,
						$hashGroup,
						function (...$args) {
							return $this->_passwordCacheMiss(...$args);
						}
					)
					->get($passwordHash);

				if ($isBlockedPassword instanceof Exception) {
					$form->addError($passwordField, __('plugins.generic.betterPassword.validation.betterPasswordUnexpectedError'));
				} elseif ($isBlockedPassword) {
					$form->addError($passwordField, __('plugins.generic.betterPassword.validation.betterPasswordCheckBlocklist'));
				}
			});
		}
	}

	/**
	 * Regenerate the cache
	 * @return bool True on success, false if an error happens while generating the cache
	 */
	private function _regenerateCache() : bool {
		$minLengthPass = DAORegistry::getDAO('SiteDAO')
			->getSite()
			->getMinPasswordLength();
		$callback = function (){};
		$hashedPasswords = [];
		foreach ($this->_getBlocklists() as $path) {
			try {
				$file = new SplFileObject($path);
				try {
					while (!$file->eof()) {
						$password = rtrim(strtolower($file->fgets()), "\n\r");
						if (strlen($password) >= $minLengthPass) {
							$hash = sha1($password);
							$hashGroup = substr($hash, 0, 2);
							$hashedPasswords[$hashGroup][$hash] = true;
						}
					}
				} finally {
					$file = null;
				}
			} catch (Exception $e) {
				error_log('ERROR: Could not open blocklist file ' . $path);
				return false;
			}
		}
		foreach($hashedPasswords as $hashGroup => $hashes) {
			CacheManager::getManager()
				->getCache(self::CACHE_CONTEXT, $hashGroup, $callback)
				->setEntireCache($hashes);
		}
		return true;
	}

	/**
	 * Callback to handle cache misses
	 * @param GenericCache $cache
	 * @param string $passwordHash The hash of the user password
	 * @return int|Exception 1 if the hash exists or an Exception if something failed
	 */
	private function _passwordCacheMiss(GenericCache $cache, string $passwordHash) {
		if (!($cache->cacheMiss instanceof generic_cache_miss)) {
			return false;
		}
		// Retrieves an Exception if the regeneration failed
		if (!$this->_regenerateCache()) {
			return new Exception('Failed to regenerate the cache');
		}
		$hashGroup = substr($passwordHash, 0, 2);
		return CacheManager::getManager()
			->getCache(self::CACHE_CONTEXT, $hashGroup, function () { return false; })
			->get($passwordHash);
	}

	/**
	 * Get the filename(s) of password blocklists.
	 * @return array a list of paths
	 */
	private function _getBlocklists() : array {
		import('lib.pkp.classes.file.PrivateFileManager');
		$privateFileManager = new PrivateFileManager();
		$paths = [implode(DIRECTORY_SEPARATOR, [$this->_plugin->getPluginPath(), 'badPasswords', 'badPasswords.txt'])];
		$userLists = $this->_plugin->getSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles') ?? [];
		foreach (array_keys($userLists) as $hash) {
			$paths[] = implode(DIRECTORY_SEPARATOR, [$privateFileManager->getBasePath(), 'betterPassword', 'blocklists', $hash]);
		}
		return $paths;
	}

	/**
	 * Register a hook to handle the upload/removal of blocklists
	 * @see PKPComponentRouter::route()
	 */
	private function _registerBlocklistFileHandler() : void {
		HookRegistry::register('LoadComponentHandler', function ($hook, $args) {
			$component = &$args[0];
			$operation = $args[1];
			if (!in_array($operation, ['deleteBlocklist', 'uploadBlocklist'])) {
				return;
			}

			define('HANDLER_CLASS', 'BlocklistHandler');
			$component = 'plugins.generic.betterPassword.handlers.BlocklistHandler';
			import($component);
			return true;
		});
	}
}
