 <?php

/**
 * @file plugins/generic/reviewqualitycollector/pages/SpyHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SpyHandler
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Handle requests to show what OJS-to-RQC requests will look like.
 */

import('classes.handler.Handler');
import('plugins.generic.reviewqualitycollector.classes.RqcData');

class SpyHandler extends Handler {
	function __construct()
	{
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	/**
	 * Show RQC request corresponding to a given submissionId=n arg.
	 */
	function look($args, $request) {
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $request->getQueryArray();
		$context = $request->getContext();
		$user = $request->getUser();
		$journal = $router->getContext($request);
		$submissionId = $requestArgs['submissionId'];
		//----- get RQC data:
		$rqcDataObj = new RqcData();
		$data = $rqcDataObj->rqcdata_array($user, $journal, $submissionId);
		//----- add interesting bits:
		$data['=========='] = "####################";
		//$data['journal'] = $journal;
		$data['rqc_journal_id'] = $this->plugin->getSetting($context->getId(), 'rqcJournalId');
		$data['rqc_journal_key'] = $this->plugin->getSetting($context->getId(), 'rqcJournalKey');
        //----- produce output:
		header("Content-Type: application/json; charset=utf-8");
		//header("Content-Type: text/plain; charset=utf-8");
		print(json_encode($data, JSON_PRETTY_PRINT));
	}

	/**
	 * Temporary helper function for exploring the DAOs.
	 */
	protected function getters_of($object) {
		$getters = array_filter(get_class_methods($object),
			function($s) { return substr($s, 0, 3) == "get"; });
		$getters = array_values($getters);  // get rid of keys
		sort($getters);
		return $getters;
	}

}
