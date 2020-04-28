<?php

/**
 * @file plugins/generic/betterPassword/BetterPasswordHandler.inc.php
 *
 * Copyright (c) University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the LICENSE file.
 *
 * @ingroup plugins_generic_betterPassword
 * @brief Handles controller requests for Better Password plugin.
 */

import('classes.handler.Handler');

class BetterPasswordHandler extends Handler {

	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);
		// Load locale usually handled by LoginHandler
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER
		);
	}

	/**
	 * Handle signIn action override
	 * @param $args array Arguments array.
	 * @param $request PKPRequest Request object.
	 */
	function signIn($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'username' => $request->getUserVar('username'),
			'remember' => $request->getUserVar('remember'),
			'source' => $request->getUserVar('source'),
			'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
			'error' => 'user.login.loginError',
			'reason' => null,
		));
		$templateMgr->display('frontend/pages/userLogin.tpl');
	}
        
	/**
	 * Store the uploaded blacklists files
	 * @param $args array Arguments array expecting user uploaded file properties
	 * @param $request PKPRequest Request object.
	 * @return JSONMessage True is blacklist file is uploaded properly
	 */
	function uploadBlacklists($args, $request) {
		import('lib.pkp.classes.file.PrivateFileManager');
		$privateFileManager = new PrivateFileManager();
		$uploadedFile = $_FILES['uploadedFile'];
		$destFilePath = $privateFileManager->getBasePath() . DIRECTORY_SEPARATOR . 'betterPassword' . DIRECTORY_SEPARATOR . 'blacklists' . DIRECTORY_SEPARATOR . sha1($uploadedFile['name']);
		if (!$privateFileManager->uploadFile('uploadedFile', $destFilePath)) {
			return new JSONMessage(false, __('plugins.generic.betterPassword.manager.settings.betterPasswordUploadFail'));
		} else {
			$plugin = PluginRegistry::getPlugin('generic', 'betterpasswordplugin');
			$prevBlacklist = $plugin->getSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles');
			$prevBlacklist[$uploadedFile['name']] = sha1_file($destFilePath);
			$plugin->updateSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles', $prevBlacklist);
		}
		return new JSONMessage(true);
	}

	/**
	 * Delete the user uploaded blacklists files
	 * @param $args array Arguments array expecting user uploaded file hash
	 * @param $request PKPRequest Request object.
	 * @return JSONMessage True if blacklist is deleted
	 */
	function deleteBlacklists($args, $request) {
		import('lib.pkp.classes.file.PrivateFileManager');
		$privateFileManager = new PrivateFileManager();
		$fileHash = $args['fileId'];
		$plugin = PluginRegistry::getPlugin('generic', 'betterpasswordplugin');
		$currBlacklist = $plugin->getSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles');
		$filename = array_search($fileHash, $currBlacklist);
		$filePath = $privateFileManager->getBasePath() . DIRECTORY_SEPARATOR . 'betterPassword' . DIRECTORY_SEPARATOR . 'blacklists' . DIRECTORY_SEPARATOR . sha1($filename);
		if ($privateFileManager->deleteByPath($filePath)) {
			unset($currBlacklist[$filename]);
			$plugin->updateSetting(CONTEXT_SITE, 'betterPasswordUserBlacklistFiles', $currBlacklist);
		} else {
			return new JSONMessage(false, __('plugins.generic.betterPassword.manager.settings.betterPasswordDeleteFail'));;
		}
		return new JSONMessage(true);
	}
}
