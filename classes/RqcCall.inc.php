<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/RqcCall.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcData
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Compute the JSON-like contents of a call to the RQC API.
 */

import('plugins.generic.reviewqualitycollector.RQCPlugin');
import('plugins.generic.reviewqualitycollector.classes.RqcData');


/**
 * Class RqcCall.
 * The core of the RQC plugin: Retrieve the reviewing data of one submission and send it to the RQC server.
 */
class RqcCall {
	public function __construct() {
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		$this->rqcdata = new RqcData();
	}

	/**
	 * Send reviewing data for one submission to RQC.
	 * Called explicitly via a button to prepare editorial decision and implicitly when decision is made.
	 * @param $user
	 * @param $journal
	 * @param $submissionId
	 */
	public function send($user, $journal, $submissionId) {
		$data = $this->rqcdata->rqcdata_array($user, $journal, $submissionId);
		$json = json_encode($data, JSON_PRETTY_PRINT);
		$url = sprintf("%s/api", RQC_SERVER);  // TODO: incomplete!!!
		// call $url with POST and $json in the body
		// treat: expected status codes, network failure
		// create delayed call in case of failure
	}


	/**
	 * Resend reviewing data for one submission to RQC after a previous call failed.
	 * Called by DelayedRQCCallsTask.
	 * TODO: Trade off simplicity vs. security: time-based signatures allow to swart replay attacks,
	 * but require patching the stored $call_content.
	 * @param $journal_id
	 * @param $call_content
	 */
	public function resend($journal_id, $call_content) {
		// TODO!!!
	}

	/**
	 * Perform an https call.
	 * @param $url
	 * @param $content
	 * @return bool|string
	 */
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
