<?php

/**
 * @file plugins/generic/betterPassword/handlers/DisabledLoginHandler.inc.php
 *
 * Copyright (c) University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the LICENSE file.
 *
 * @class DisabledLoginHandler
 * @ingroup plugins_generic_betterPassword
 * 
 * @brief Handles controller requests for the login page, it's used to temporarily disable the logon.
 */

//import('classes.handler.Handler');
use APP\handler\Handler;

class DisabledLoginHandler extends Handler {
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	public function initialize($request) : void {
		parent::initialize($request);
		// Load locale usually handled by LoginHandler
		//AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER); //needs changed
	}

	/**
	 * Handle signIn action override
	 * @param $args array Arguments array.
	 * @param $request PKPRequest Request object.
	 */
	public function signIn(array $args, PKPRequest $request) : void {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'username' => $request->getUserVar('username'),
			'remember' => $request->getUserVar('remember'),
			'source' => $request->getUserVar('source'),
			'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
			'error' => 'user.login.loginError',
			'reason' => null,
		]);
		$templateMgr->display('frontend/pages/userLogin.tpl');
	}
}
