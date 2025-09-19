<?php

/**
 * @file plugins/generic/betterPassword/features/Blocklist.inc.php
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class Blocklist
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Implements the blocklist feature
 */

namespace APP\plugins\generic\betterPassword\features;

use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use APP\plugins\generic\betterPassword\handlers\BlocklistHandler;
use Exception;
use PKP\cache\CacheManager;
use PKP\cache\GenericCache;
use PKP\core\PKPApplication;
use PKP\file\PrivateFileManager;
use PKP\plugins\Hook;
use SplFileObject;

class Blocklist
{
    /** @var BetterPasswordPlugin */
    private $_plugin;

    /** @var string Context which will keep the cached hashes */
    private const CACHE_CONTEXT = 'badPasswords';

    /**
     * Constructor
     */
    public function __construct(BetterPasswordPlugin $plugin)
    {
        $this->_plugin = $plugin;
        // Enable the file handler to be used by the settings form
        $this->register();
        $contextSite = PKPApplication::CONTEXT_SITE;
        if (!(bool) $plugin->getSetting($contextSite, 'betterPasswordCheckBlacklist')) {
            return;
        }

        $this->_addPasswordValidation();
    }

    /**
     * Clear the password cache
     */
    public static function clearCache(): void
    {
        CacheManager::getManager()->flush(self::CACHE_CONTEXT);
    }

    /**
     * Register a hook to validate passwords against a blocked list
     */
    private function _addPasswordValidation(): void
    {
        // Register callback to validate new passwords
        foreach (['registrationform::validate', 'changepasswordform::validate', 'loginchangepasswordform::validate', 'resetpasswordform::validate'] as $hook) {
            Hook::add($hook, function ($hook, $args) {
                /** @var \PKP\form\Form $form */
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
     *
     * @return bool True on success, false if an error happens while generating the cache
     */
    private function _regenerateCache(): bool
    {
        $pkpApplication = PKPApplication::get();
        $minLengthPass = $pkpApplication->getRequest()
            ->getSite()
            ->getMinPasswordLength();
        $callback = function () {};
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
        foreach ($hashedPasswords as $hashGroup => $hashes) {
            CacheManager::getManager()
                ->getCache(self::CACHE_CONTEXT, $hashGroup, $callback)
                ->setEntireCache($hashes);
        }
        return true;
    }

    /**
     * Callback to handle cache misses
     *
     * @param string $passwordHash The hash of the user password
     *
     * @return int|Exception 1 if the hash exists or an Exception if something failed
     */
    private function _passwordCacheMiss(GenericCache $cache, string $passwordHash): bool|Exception|null
    {
        if (!(get_class($cache->cacheMiss) == "PKP\cache\generic_cache_miss")) {
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
     *
     * @return array a list of paths
     */
    private function _getBlocklists(): array
    {
        $privateFileManager = new PrivateFileManager();
        $paths = [implode(DIRECTORY_SEPARATOR, [$this->_plugin->getPluginPath(), 'badPasswords', 'badPasswords.txt'])];
        $userLists = $this->_plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordUserBlacklistFiles') ?? [];
        foreach (array_keys($userLists) as $hash) {
            $paths[] = implode(DIRECTORY_SEPARATOR, [$privateFileManager->getBasePath(), 'betterPassword', 'blocklists', $hash]);
        }
        return $paths;
    }

    /**
     * Register a hook for LoadComponentHandler
     */
    public function register()
    {
        Hook::add('LoadComponentHandler', [$this, 'setComponentHandler']);
    }

    /**
     * Create a new BlocklistHandler
     *
     * @param string $hookname The hook name
     * @param array $args Arguments of the hook
     *
     * @return bool true if the plugin is the final handler for the hook and false if other hooks need to intervene
     */
    public function setComponentHandler(string $hookname, array $args): bool
    {
        $component = & $args[0];
        $op = & $args[1];
        $handler = & $args[2];
        if ($component == 'plugins.generic.betterpassword.handler.BlocklistHandler') {
            if ($op == 'uploadBlocklist' || $op == 'deleteBlocklist') {
                $handler = new BlocklistHandler();
                return true;
            }
        }
        return false;
    }
}
