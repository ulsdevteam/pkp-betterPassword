<?php

/**
 * @defgroup plugins_generic_betterPassword
 */

/**
 * @file plugins/generic/betterPassword/index.php
 *
 * Copyright (c) 2019 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_betterPassword
 * @brief Wrapper for Better Password plugin.
 *
 */

require_once 'BetterPasswordPlugin.inc.php';

return new BetterPasswordPlugin();
