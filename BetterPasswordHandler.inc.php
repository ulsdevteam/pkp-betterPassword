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
		// Load local usually handled by LoginHandler
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER
		);
	}

	/**
	 * Handle markAsSpam action
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
}
