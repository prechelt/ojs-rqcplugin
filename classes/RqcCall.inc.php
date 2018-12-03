<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/RqcCall.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RqcData
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Compute the JSON-like contents of a call to the RQC API.
 */

import('plugins.generic.reviewqualitycollector.classes.RqcData');

$RQC_SERVER = 'https://reviewqualitycollector.org';

class RqcCall {
	public function __construct() {
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		$this->rqcdata = new RqcData();
	}

	public function send($user, $journal, $submissionId) {
		$data = $this->rqcdata->rqcdata_array($user, $journal, $submissionId);
		$json = json_encode($data, JSON_PRETTY_PRINT);
		$url = sprintf("%s/api", $RQC_SERVER);  // incomplete!!!
		// call $url with POST and $json in the body
		// treat: expected status codes, network failure
		// create delayed call in case of failure
	}


	public function resend($journal_id, $call_content) {

	}
}