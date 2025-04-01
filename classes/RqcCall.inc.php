<?php
/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use PKP\plugins\PluginRegistry;
*/
import('plugins.generic.rqc.RqcPlugin');
import('plugins.generic.rqc.classes.RqcData');
import('plugins.generic.rqc.classes.RqcDevHelper');
import('plugins.generic.rqc.classes.RqcLogger');

define('RQC_MHS_APIKEYCHECK_URL', "%s/api/mhs_apikeycheck/%s");  // host, rqcJournalId
define('RQC_MHS_SUBMISSION_URL', "%s/api/mhs_submission/%s/%s");  // host, rqcJournalId, externalUid


/**
 * Class RqcCall.
 * The technical parts of calls to the RQC server.
 * @return array  "status" and "response" information
 */
class RqcCall
{
	static function callMhsSubmission(string $rqcHostUrl, string $rqcJournalId, string $rqcJournalAPIKey,
											 $request, int $submissionId, bool $strict = false): array
	{
		$rqcdata = new RqcData();
		$data = $rqcdata->rqcdataArray($request, $submissionId);
		$url = sprintf(RQC_MHS_SUBMISSION_URL, $rqcHostUrl, $rqcJournalId, $submissionId);
		return RqcCall::curlCall($url, $rqcJournalAPIKey, "POST", $data, $strict);
	}

	static function callMhsApikeycheck(string $hostUrl, string $rqcJournalId, string $rqcJournalAPIKey,
									   bool   $strict = false): array
	{
		$url = sprintf(RQC_MHS_APIKEYCHECK_URL, $hostUrl, $rqcJournalId);
		return RqcCall::curlCall($url, $rqcJournalAPIKey, "GET", array(), $strict);
	}

	/**
	 * Call curl for mhs_submission (POST) or mhs_apikeycheck (GET) and return an array
	 * containing status and output, where output has key 'json' (decoded JSON) or
	 * 'body' (HTML string or redirect URL string).
	 * @param string $url      the complete URL to call
	 * @param string $rqcJournalAPIKey
	 * @param string $mode     "GET" or "POST"
	 * @param array  $postData data to be sent in body (for POST mode only)
	 * @param bool   $strict   whether to do proper SSL checking
	 * @return array  "status" and "response" information
	 */
	static function curlCall(string $url, string $rqcJournalAPIKey, string $mode, array $postData,
							 bool   $strict): array
	{
		assert($mode == "GET" || $mode == "POST");
		assert($postData || $mode == "GET");
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
			CURLOPT_POST           => ($mode == "POST"),
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER     => $http_headers
		);
		if ($mode == "POST") {
			$curlopts[CURLOPT_POSTFIELDS] = json_encode($postData, JSON_PRETTY_PRINT);
			$result['request'] = $postData;
		}
		curl_setopt_array($cc, $curlopts);
		if ($strict) {
			curl_setopt($cc, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($cc, CURLOPT_SSL_VERIFYHOST, 2);  // 2:check host name against cert, 0:don't
		} else {
			curl_setopt($cc, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($cc, CURLOPT_SSL_VERIFYHOST, 0);  // 2:check host name against cert, 0:don't

		}
		//----- make call:
		$body = curl_exec($cc);
		//RqcDevHelperStatic::_staticPrint("\nbody: ".print_r($body, true)."\n");
		$curl_error = curl_error($cc);
		//RqcDevHelperStatic::_staticPrint("\ncurl_Error: ".print_r($curl_error, true)."\n");
		$status = curl_getinfo($cc, CURLINFO_RESPONSE_CODE);
		//RqcDevHelperStatic::_staticPrint("\nstatus:".print_r($status, true)."\n");
		$content_type = curl_getinfo($cc, CURLINFO_CONTENT_TYPE);
		//RqcDevHelperStatic::_staticPrint("\ncontent_type: ".print_r($content_type, true)."\n");
		curl_close($cc);
		//----- handle call errors:
		$result['status'] = $status;
		if ($curl_error) {
			$result['response'] = array('error' => $curl_error);
			return $result;  // return prematurely
		}
		//----- create $result:
		if ($content_type == 'application/json') {  //----- handle expected JSON response:
			$result['response'] = json_decode($body, true);
		} else {                                    //----- handle unexpected response:
			RqcLogger::logError("Received an unexpected non-JSON response from RQC while making a $mode-request to $url. Resulted in http status code $status with response " . print_r($body, true));
			$result['response'] = array(
				'error'        => "received an unexpected non-JSON response from RQC",
				'responsebody' => $body
			);
		}
		return $result;
	}
}
