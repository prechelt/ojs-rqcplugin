<?php
/* for OJS 3.4:
namespace APP\plugins\generic\reviewqualitycollector;
use PKP\plugins\PluginRegistry;
*/
import('plugins.generic.reviewqualitycollector.RQCPlugin');
import('plugins.generic.reviewqualitycollector.classes.RqcData');
import('plugins.generic.reviewqualitycollector.classes.RqcDevHelper');

define('RQC_MHS_APIKEYCHECK_URL', "%s/api/mhs_apikeycheck/%s");  // host, rqcJournalId
define('RQC_MHS_SUBMISSION_URL', "%s/api/mhs_submission/%s/%s");  // host, rqcJournalId, externalUid


function call_mhs_submission(string $hostUrl, string $rqcJournalId, string $rqcJournalAPIKey,
									$user, $journal, $submissionId)
{
	$rqcdata = new RqcData();
	$data = $rqcdata->rqcdata_array($user, $journal, $submissionId);
	$json = json_encode($data, JSON_PRETTY_PRINT);
	// $this->_print($json);
	$url = sprintf(RQC_MHS_SUBMISSION_URL, $hostUrl, $rqcJournalId, $submissionId);
	//----- call $url with POST and $json in the body:
	$cc = curl_init($url);
	curl_setopt_array($cc, array(
		CURLOPT_POST => TRUE,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $rqcJournalAPIKey,  // a la https://datatracker.ietf.org/doc/html/rfc6750
		),
		CURLOPT_POSTFIELDS => $json,
	));
	$output = curl_exec($cc);
	$status = curl_getinfo($cc, CURLINFO_RESPONSE_CODE);
	curl_close($cc);
	return array($status, $output);
}

function call_mhs_apikeycheck(string $hostUrl, string $rqcJournalId, string $rqcJournalAPIKey)
{
	$url = sprintf(RQC_MHS_APIKEYCHECK_URL, $hostUrl, $rqcJournalId);
	//----- call $url with GET:
	$cc = curl_init($url);
	curl_setopt_array($cc, array(
		CURLOPT_POST => FALSE,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer ' . $rqcJournalAPIKey,  // a la https://datatracker.ietf.org/doc/html/rfc6750
			'X-RQC-API-VERSION: ' . RQC_API_VERSION,
			'X-RQC-MHS-ADAPTER: ' . RQC_MHS_ADAPTER,  // imprecise: lacking a version
			'X-RQC-MHS-VERSION: ' . RQC_PLUGIN_VERSION,  // precise for plugin, questionable for OJS itself
			'X-RQC-TIME: ' . (new DateTime())->format('Y-m-d\TH:i:s\Z'),
		),
	));
	$output = curl_exec($cc);
	$status = curl_getinfo($cc, CURLINFO_RESPONSE_CODE);
	$content_type = curl_getinfo($cc, CURLINFO_CONTENT_TYPE);
	curl_close($cc);
	//----- return with no response:
	if ($content_type != 'application/json') {
		return array($status, array());
	}
	//----- decode response:
	error_log($output);
	$json = json_decode($output, true);
	return array('status' => $status, 'json' => $json);
}

/**
 * Class RqcCall.
 * The core of the RQC plugin: Retrieve the reviewing data of one submission and send it to the RQC server.
 */
class RqcCall extends RqcDevHelper {
	public function __construct() {
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		$this->rqcdata = new RqcData();
		parent::__construct();
	}

	/**
	 * Send reviewing data for one submission to RQC.
	 * Called explicitly via a button to prepare editorial decision and implicitly when decision is made.
	 * @param $user
	 * @param $journal
	 * @param $submissionId
	 */
	public function send($user, $journal, $submissionId) {
		$rqcJournalId = $this->plugin->getSetting($journal->getId(), 'rqcJournalId');
		$rqcJournalKey = $this->plugin->getSetting($journal->getId(), 'rqcJournalKey');
		$data = $this->rqcdata->rqcdata_array($user, $journal, $submissionId);
		$json = json_encode($data, JSON_PRETTY_PRINT);
		$url = sprintf("%s/j/mhsapi/%s/1", $this->plugin->rqc_server(), $rqcJournalId);
		//----- call $url with POST and $json in the body:
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $rqcJournalKey,
			),
			CURLOPT_POSTFIELDS => $json,
		));
		$output = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $output;
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
