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


function call_mhs_submission(string $rqcHostUrl, string $rqcJournalId, string $rqcJournalAPIKey,
									$request, $journal, $submissionId)
{
	$rqcdata = new RqcData();
	$data = $rqcdata->rqcdata_array($request, $journal, $submissionId);
	// $this->_print($json);
	$url = sprintf(RQC_MHS_SUBMISSION_URL, $rqcHostUrl, $rqcJournalId, $submissionId);
	return curlcall($url, $rqcJournalAPIKey, "POST", $data);
}

function call_mhs_apikeycheck(string $hostUrl, string $rqcJournalId, string $rqcJournalAPIKey)
{
	$url = sprintf(RQC_MHS_APIKEYCHECK_URL, $hostUrl, $rqcJournalId);
	return curlcall($url, $rqcJournalAPIKey, "GET");
}

/**
 * Call curl for mhs_submission (POST) or mhs_apikeycheck (GET) and return an array
 * containing status and output, where output has key 'json' (decoded JSON) or
 * 'body' (HTML string or redirect URL string).
 * @param string $url  the complete URL to call
 * @param string $rqcJournalAPIKey
 * @param string $mode  "GET" or "POST"
 * @param string $postbody  data to be sent in body (for POST mode only)
 * @return array
 */
function curlcall(string $url, string $rqcJournalAPIKey, string $mode, array $postdata = null): array
{
	assert($mode == "GET" || $mode == "POST");
	assert($postdata || $mode == "GET");
	$result = array();
	//----- prepare call:
	$cc = curl_init($url);
	$http_headers = array(
		'Authorization: Bearer ' . $rqcJournalAPIKey,  // a la https://datatracker.ietf.org/doc/html/rfc6750
		'X-RQC-API-VERSION: ' . RQC_API_VERSION,
		'X-RQC-MHS-ADAPTER: ' . RQC_MHS_ADAPTER,  // imprecise: lacking a version
		'X-RQC-MHS-VERSION: ' . RQC_PLUGIN_VERSION,  // precise for plugin, questionable for OJS itself
		'X-RQC-TIME: ' . (new DateTime())->format('Y-m-d\TH:i:s\Z'),
	);
	if ($mode == "POST") {
		$http_headers[] = 'Content-Type: application/json';
	}
	$curlopts = array(
		CURLOPT_POST => ($mode == "POST"),
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HTTPHEADER => $http_headers);
	if ($mode == "POST") {
		$curlopts[CURLOPT_POSTFIELDS] = json_encode($postdata, JSON_PRETTY_PRINT);
		$result['request'] = $postdata;
	}
	curl_setopt_array($cc, $curlopts);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  // TODO 1
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // check host name
	//curl_setopt($ch, CURLOPT_CAINFO, RQC_ROOTCERTFILE);
	//----- make call:
	$body = curl_exec($cc);
	$status = curl_getinfo($cc, CURLINFO_RESPONSE_CODE);
	$content_type = curl_getinfo($cc, CURLINFO_CONTENT_TYPE);
	curl_close($cc);
	//----- create $result:
	$result['status'] = $status;
	if ($content_type == 'application/json') {  //----- handle expected JSON response:
		$result['response'] = json_decode($body, true);
	}
	else {                                      //----- handle unexpected response:
		error_log($body);  // TODO 1: make proper log message
		$result['response'] = array('error' => "received an unexpected non-JSON response from RQC",
									'responsebody' => $body);
	}
	return $result;
}

/**
 * The HTML for a semi-intentionally crude page that signals the user is about
 * to leave OJS and go to RQC.
 */
function rqcgrading_confirmation_page($target_url, $submission_title) {
	return <<<EOD
<html>
	<head><title>Grade reviews in Review Quality Collector</title></head>
	<body>
		<h1>You are about to leave OJS</h1>
		<p>
			The link below will take you to <b>Review Quality Collector (RQC)</b>
			for handling the present submission titled<br>
			"$submission_title".
		</p>
		<ol>
			<li>
				For this to work, you need to have an account at RQC.<br>
				(If you see the present page for the first time, just
				<a href="https://reviewqualitycollector.org/accounts/signup/" target="_blank"
				>sign up for an account now</a>.
				It only takes about one minute.<br>
				Make sure you supply the email address used by OJS.
				Make sure you note down your username (which can be, but need not be, identical to the email).)
			</li>
			<li>
				Log in to that account (only needed if you are prompted to log in).
			</li>
			<li>
				Then assign the submission to the appropriate subjournal.
			</li>
			<li>
				Depending on how RQC is configured for that subjournal,
				you may then be asked to grade the submission's reviews.
				Do that.
				(The best time is typically just before making an editorial decision.)
			</li>
			<li>
				Afterwards, you will automatically be taken back to the OJS page from which you came.
			</li>
		</ol>
		<p>
			<a href="$target_url">Go to RQC now.</a>
		</p>
	</body>
</html>
EOD;
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
	 * Send reviewing data for one submission to RQC.  OUTDATED!
	 * Called explicitly via a button to prepare editorial decision and implicitly when decision is made.
	 * @param $user
	 * @param $journal
	 * @param $submissionId
	 */
	public function send($request, $journal, $submissionId) {
		$rqcJournalId = $this->plugin->getSetting($journal->getId(), 'rqcJournalId');
		$rqcJournalKey = $this->plugin->getSetting($journal->getId(), 'rqcJournalKey');
		$data = $this->rqcdata->rqcdata_array($request, $journal, $submissionId);
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
	 */
	public function resend($user, $journal, $submissionId) {
		// TODO!!!
	}
}
