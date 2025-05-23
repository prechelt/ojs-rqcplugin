<?php

namespace APP\plugins\generic\rqc\classes;

use DateTime;
use PKP\plugins\PluginRegistry;

use APP\plugins\generic\rqc\classes\RqcLogger;
use APP\plugins\generic\rqc\classes\RqcDevHelper;
use APP\plugins\generic\rqc\classes\RqcData;
use APP\plugins\generic\rqc\RqcPlugin;

define('RQC_MHS_APIKEYCHECK_URL', "%s/api/mhs_apikeycheck/%s");  // host, rqcJournalId
define('RQC_MHS_SUBMISSION_URL', "%s/api/mhs_submission/%s/%s");  // host, rqcJournalId, externalUid


/**
 * Class RqcCall.
 * The technical parts to execute calls to the RQC server.
 * @return array  "status" and "response" information
 *
 * @ingroup  plugins_generic_rqc
 */
class RqcCall
{
	/**
	 * used by send/resend to give the request with all data to the curlCall method
	 */
	public static function callMhsSubmission(string $rqcHostUrl, string $rqcJournalId, string $rqcJournalAPIKey,
													$request, int $submissionId, bool $strict = false): array
	{
		$rqcData = new RqcData();
		$data = $rqcData->rqcDataArray($request, $submissionId);
		$postData = $data['data'];
		$difference = $data['truncation_omission_info'];
		if (count($difference) > 0) {
			RqcLogger::logWarning("For submission $submissionId: The following data is truncated because of the size limits of rqc: " . implode("; ", $difference));
		}
		$url = sprintf(RQC_MHS_SUBMISSION_URL, $rqcHostUrl, $rqcJournalId, $submissionId);
		return RqcCall::curlCall($url, $rqcJournalAPIKey, "POST", $postData, $strict);
	}

	/**
	 * used by the isValid-check to give the request to the curlCall method
	 */
	public static function callMhsApikeyCheck(string $hostUrl, string $rqcJournalId, string $rqcJournalAPIKey,
											  bool   $strict = false): array
	{
		$url = sprintf(RQC_MHS_APIKEYCHECK_URL, $hostUrl, $rqcJournalId);
		return RqcCall::curlCall($url, $rqcJournalAPIKey, "GET", array(), $strict);
	}

	/**
	 * Call curl for callMhsSubmission (POST) or callMhsApikeyCheck (GET) and return an array
	 * containing status and output, where output has key 'json' (decoded JSON) or
	 * 'body' (HTML string or redirect URL string).
	 * @param string $url      the complete URL to call
	 * @param string $rqcJournalAPIKey
	 * @param string $mode     "GET" or "POST"
	 * @param array  $postData data to be sent in body (for POST mode only)
	 * @param bool   $strict   whether to do proper SSL checking
	 * @return array  "status" and "response" information
	 */
	protected static function curlCall(string $url, string $rqcJournalAPIKey, string $mode, array $postData,
									   bool   $strict): array
	{
		assert($mode == "GET" || $mode == "POST");
		assert($postData || $mode == "GET");
		$result = array();
		//----- prepare call:
		$cc = curl_init($url);
		$http_headers = array(
			'Authorization: Bearer ' . $rqcJournalAPIKey,  // a la https://datatracker.ietf.org/doc/html/rfc6750
			'X-RQC-API-VERSION: ' . RqcPlugin::RQC_API_VERSION,
			'X-RQC-MHS-ADAPTER: ' . RqcPlugin::RQC_MHS_ADAPTER,  // imprecise: lacking a version
			'X-RQC-MHS-VERSION: ' . RqcPlugin::RQC_PLUGIN_VERSION,  // precise for plugin, questionable for OJS itself
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
		//RqcDevHelper::writeObjectToConsole($body, "body: ");
		$curl_error = curl_error($cc);
		//RqcDevHelper::writeObjectToConsole($curl_error, "curl_Error: ");
		//RqcDevHelper::writeObjectToConsole(curl_errno($cc), "curl_Errno: ");
		$status = curl_getinfo($cc, CURLINFO_RESPONSE_CODE);
		//RqcDevHelper::writeObjectToConsole($status, "status: ");
		$content_type = curl_getinfo($cc, CURLINFO_CONTENT_TYPE);
		//RqcDevHelper::writeObjectToConsole($content_type, "content_type: ");
		curl_close($cc);
		//----- handle curl_call errors:
		$result['status'] = $status;
		if ($curl_error || $body === false) {
			$result['response'] = array(
				'error'        => $curl_error,
				'responseBody' => $body // maybe (not likely) are important information in there if an error occurred
			);
			return $result;  // return prematurely
		}
		//----- create $result:
		if ($content_type == 'application/json') {  //----- handle expected JSON response:
			$result['response'] = json_decode($body, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$result['response'] = array(
					'error'        => 'JSON parsing error: ' . json_last_error_msg(),
					'responseBody' => $body
				);
			}
		} else {                                    //----- handle unexpected response:
			RqcLogger::logError("Received an unexpected non-JSON response from RQC while making a $mode-request to $url. Resulted in http status code $status with response " . json_encode($body));
			$result['response'] = array(
				'error'        => "received an unexpected non-JSON response from RQC",
				'responseBody' => $body
			);
		}
		return $result;
	}
}
