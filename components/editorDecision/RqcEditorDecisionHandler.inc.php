<?php

/**
 * @file plugins/generic/reviewqualitycollector/components/editorDecision/RqcEditorDecisionHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcEditorDecisionHandler
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Handle modal dialog before submitting and redirecting to RQC.
 */

import('classes.handler.Handler');

class RqcEditorDecisionHandler extends PKPHandler
{

	function __construct()
	{
		parent::__construct();
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	/**
	 * Confirm redirection to RQC.
	 */
	function rqcGrade($args, $request)
	{
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $request->getQueryArray();
		$context = $request->getContext();
		$journal = $router->getContext($request);
		$submissionId = $requestArgs['submissionId'];
		//----- modal dialog:
		return new JSONMessage(true, "<h1>Hello, Lutz!</h1>");  // TODO
	}
}
