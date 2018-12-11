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

import('plugins.generic.reviewqualitycollector.RQCPlugin');
import('plugins.generic.reviewqualitycollector.classes.RqcData');


class RqcCall {
	public function __construct() {
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		$this->rqcdata = new RqcData();
	}

	public function send($user, $journal, $submissionId) {
		$data = $this->rqcdata->rqcdata_array($user, $journal, $submissionId);
		$json = json_encode($data, JSON_PRETTY_PRINT);
		$url = sprintf("%s/api", RQC_SERVER);  // incomplete!!!
		// call $url with POST and $json in the body
		// treat: expected status codes, network failure
		// create delayed call in case of failure
	}


	public function resend($journal_id, $call_content) {
		// TODO!!!
	}

	public function do_post($url, $content) {
		// http://unitstep.net/blog/2009/05/05/using-curl-in-php-to-access-https-ssltls-protected-sites/
		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_POST, 1);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		//curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // check host name
		//curl_setopt($ch, CURLOPT_CAINFO, RQC_ROOTCERTFILE);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}