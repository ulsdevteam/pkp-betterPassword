<?php

/**
 * @file plugins/generic/betterPassword/handlers/BlocklistHandler.inc.php
 *
 * Copyright (c) University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the LICENSE file.
 *
 * @class BlocklistHandler
 *
 * @ingroup plugins_generic_betterPassword
 *
 * @brief Handles controller requests to upload/remove blocklists.
 */

namespace APP\plugins\generic\betterPassword\handlers;

use APP\handler\Handler;
use APP\plugins\generic\betterPassword\BetterPasswordPlugin;
use APP\plugins\generic\betterPassword\features\Blocklist;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\file\PrivateFileManager;
use PKP\plugins\PluginRegistry;

class BlocklistHandler extends Handler
{
    /** @var PrivateFileManager */
    private $_privateFileManager;

    /** @var BetterPasswordPlugin */
    private $_plugin;

    /**
     * @copydoc PKPHandler::initialize()
     */
    public function initialize($request): void
    {
        parent::initialize($request);
        // Load locale usually handled by LoginHandler
        $this->_privateFileManager = new PrivateFileManager();
        $pluginClass = BetterPasswordPlugin::class;
        $plugin = explode('\\', $pluginClass);
        $plugin = array_pop($plugin);
        $plugin = strtolower($plugin);
        $this->_plugin = PluginRegistry::getPlugin('generic', $plugin);
    }

    /**
     * Save an user blocklist
     *
     * @param array $args Arguments array expecting user uploaded file properties
     * @param  PKPRequest $request Request object.
     *
     * @return JSONMessage True is blocklist file is uploaded properly
     */
    public function uploadBlocklist(array $args, PKPRequest $request): JSONMessage
    {
        $fieldName = 'uploadedFile';
        $error = new JSONMessage(false, __('plugins.generic.betterPassword.manager.settings.betterPasswordUploadFail'));
        if (!$this->_privateFileManager->uploadedFileExists($fieldName) || $this->_privateFileManager->uploadError($fieldName)) {
            return $error;
        }

        $hash = sha1_file($this->_privateFileManager->getUploadedFilePath($fieldName));
        if (!$this->_privateFileManager->uploadFile($fieldName, $this->_getSavePath($hash))) {
            return $error;
        }

        $blocklists = $this->_getBlocklists();
        $blocklists[$hash] = $this->_privateFileManager->getUploadedFileName($fieldName);
        $this->_setBlockLists($blocklists);
        Blocklist::clearCache();
        return new JSONMessage(true);
    }

    /**
     * Delete an user blocklist
     *
     * @param array $args Arguments array expecting user uploaded file hash
     * @param PKPRequest $request Request object.
     *
     * @return JSONMessage True if blocklist is deleted
     */
    public function deleteBlocklist(array $args, PKPRequest $request): JSONMessage
    {
        $hash = $args['file'] ?? null;
        $path = $this->_getSavePath(preg_replace('/\W/', '', $hash));
        if ($this->_privateFileManager->fileExists($path) && !$this->_privateFileManager->deleteByPath($path)) {
            return new JSONMessage(false, __('plugins.generic.betterPassword.manager.settings.betterPasswordDeleteFail'));
        }

        $blocklists = $this->_getBlocklists();
        unset($blocklists[$hash]);
        $this->_setBlocklists($blocklists);
        Blocklist::clearCache();
        return new JSONMessage(true);
    }

    /**
     * Retrieve the save path for a blocklist
     *
     * @param $filename string
     */
    private function _getSavePath(string $filename): string
    {
        return implode(DIRECTORY_SEPARATOR, [$this->_privateFileManager->getBasePath(), 'betterPassword', 'blocklists', $filename]);
    }

    /**
     * Retrieve the blocklists uploaded by the user
     */
    private function _getBlocklists(): array
    {
        return $this->_plugin->getSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordUserBlacklistFiles') ?? [];
    }

    /**
     * Set the user blocklists
     *
     * @return string
     */
    private function _setBlockLists(array $blocklists): void
    {
        $this->_plugin->updateSetting(PKPApplication::CONTEXT_SITE, 'betterPasswordUserBlacklistFiles', $blocklists);
    }
}
