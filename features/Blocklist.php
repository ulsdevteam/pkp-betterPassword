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
use PKP\core\PKPApplication;
use PKP\file\PrivateFileManager;
use PKP\plugins\Hook;
use SplFileObject;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\betterPassword\Models\BpwBlocklistItem;

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
    public static function clearCache() {
        return DB::table('bpw_blocklist_items')->delete();
    }
    public function regenerateCache() {
        return $this->_regenerateCache();
    }

    /**
     * Register a hook to validate passwords against a blocked list
     */
    private function _addPasswordValidation(): void
    {
        // Register callback to validate new passwords
        foreach (['registrationform::validate', 'changepasswordform::validate', 'loginchangepasswordform::validate'] as $hook) {
            Hook::add($hook, function ($hook, $args) {
                /** @var \PKP\form\Form $form */
                [$form] = $args;
                $passwordField = 'password';
                $password = $form->getData($passwordField);
                // Let the form itself handle the core required function
                if (!$password) {
                    return;
                }
                //Check the DB for a match. Returns the value of the first matching row.
                $isBlockedPassword= BpwBlocklistItem::where('blocklist_item', $password)->value('blocklist_item');
                //If something went wrong before being able to make a decision about the password
                if ($isBlockedPassword instanceof Exception) {
                    $form->addError($passwordField, __('plugins.generic.betterPassword.validation.betterPasswordUnexpectedError'));
                //Password blocked
                } elseif ($isBlockedPassword) {
                    //notify the user onscreen
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
        $callback = function () {};
        //one array to hold passwords from all blocklists
        $blocklistItems=[];
        //retrieve all available blocklist files
        foreach ($this->_getBlocklists() as $path) {
            try {
                $file = new SplFileObject($path);
                try {
                    while (!$file->eof()) {
                        //strip line endings
                        $password = rtrim($file->fgets(), "\n\r");
                        //build the array one password at a time. we'll use it to insert the values into the DB
                        $blocklistItems[]= ["blocklist_item" => $password ];
                    }
                } finally {
                    $file = null;
                }
            } catch (Exception $e) {
                error_log('ERROR: Could not open blocklist file ' . $path);
                return false;
            }
        }
        try {
            //try to insert the passwords into the DB
            $this->_insertBlocklistItems($blocklistItems);
            } catch (Exception $e) {
                error_log('ERROR: Could not save blocklist items to db ');
                return false;
            }
        return true;
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
    
        /**
     * Insert a Blocklist array into DB
     *
     * @param array $blocklistItems
     *
     * @return boolean true if successfully inserted into DB
     */
    private function _insertBlocklistItems(array $blocklistItems): bool
    {
        //Call query builder batch insert method via the model
        //no fillAndInsert() until Laravel 12.x
        $model = new BpwBlocklistItem;
        return $model->insertOrIgnore($blocklistItems);
    }
}
