<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\pages\workflow\WorkflowHandler;
use PKP\plugins\PluginRegistry;
*/

import('pages.workflow.WorkflowHandler');
import('classes.submission.SubmissionDAO');

import('plugins.generic.rqc.classes.RqcCall');
import('plugins.generic.rqc.RqcPlugin');
import('plugins.generic.rqc.classes.DelayedRqcCallDAO');
import('plugins.generic.rqc.classes.DelayedRqcCall');
import('plugins.generic.rqc.classes.RqcLogger');
import('plugins.generic.rqc.classes.RqcDevHelper');

define("RQC_CALL_STATUS_CODES_TO_RESEND", array(
	408, // "Request Timeout"
	500, // "Internal Server Error"
	501, // "Not Implemented"
	502, // "Bad Gateway"
	503, // "Service Unavailable"
	504, // "Gateway Timeout"
	505, // "HTTP Version Not Supported"
	0 // Unabled to communicate with the server. Aka connection closed, ...
)); // which status codes make the system put the call into the queue to retry later (as opposed to ignoring it because the call was invalidly build) // TODO 2: review the codes
define("RQC_CALL_SERVER_DOWN", array(
	408, // "Request Timeout"
	501, // "Not Implemented"
	502, // "Bad Gateway"
	503, // "Service Unavailable"
	504, // "Gateway Timeout"
	505, // "HTTP Version Not Supported"
	0 // Unabled to communicate with the server. Aka connection closed, ...
)); // TODO 2: review the codes
define("RQC_CALL_STATUS_CODES_SUCESS", array(
	200, // "OK"
	201, // "Created"
	202, // "Accepted"
	203, // "Non-Authoritative Information"
	204, // "No Content"
	205, // "Reset Content"
	206, // "Partial Content"
));


/**
 * Class RqcCallHandler.
 * The core of the RQC plugin: Retrieve the reviewing data of one submission and send it to the RQC server
 * after the editor dialog has redirected to this page.
 * Making it a separate page helps with testing.
 *
 * @ingroup plugins_generic_rqc
 */
class RqcCallHandler extends WorkflowHandler
{
	private Plugin|null $plugin;

	private int $_maxRetriesToResend = 10; // arbitrary number

	public function __construct()
	{
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		$this->addRoleAssignment(
			array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT),
			array('submit',));
		parent::__construct();
	}

	/**
	 * Confirm submission+redirection to RQC
	 * Called by RqcEditorDecisionHandler
	 */
	public function submit($args, $request)
	{
		// TODO Q By Julius: Should we have a x Sec lock on the button to not spam calls if the system is lagging?
		$qargs = $this->plugin->getQueryArray($request);
		$stageId = $qargs['stageId'];
		if ($stageId != WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
			print("<html><body lang='en'>");
			print("stageId is $stageId. ");
			print("This call makes sense only for stageId " . WORKFLOW_STAGE_ID_EXTERNAL_REVIEW . ".");
			print("</body></html>");
			return;
		}
		$submissionId = $qargs['submissionId'];
		$rqcResult = $this->sendToRqc($request, $submissionId); // Explicit call
		if (in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_SUCESS)) {
			RqcLogger::logInfo("Explicit call to RQC for submission $submissionId successful");
		} else {
			if ($rqcResult['enqueuedCall']) {
				RqcLogger::logWarning("Explicit call to RQC for submission $submissionId resulted in status " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . "Inserted it into the db to be retried later as a delayed rqc call.");
			} else {
				RqcLogger::logError("Explicit call to RQC for submission $submissionId resulted in status " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . ": The call was probably faulty (and wasn't put into the queue to retry later).");
			}
		}
		$this->processRqcResponse($rqcResult['status'], $rqcResult['response']);
	}

	/**
	 * The workhorse for actually sending one submission's reviewing data to RQC.
	 * Upon a network failure or non-response, puts call in queue (if "status" is in RQC_CALL_STATUS_CODES_TO_RESEND)
	 * @return array  "status" and "response" information together with "enqueuedCall" (true/false)
	 */
	function sendToRqc($request, int $submissionId): array
	{
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);
		$contextId = $submission->getContextId();
		$rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		$rqcResult = RqcCall::callMhsSubmission($this->plugin->rqcServer(), $rqcJournalId, $rqcJournalAPIKey,
			$request, $submissionId, !$this->plugin->hasDeveloperFunctions());
		//RqcDevHelper::writeObjectToConsole($rqcResult);
		$rqcResult['enqueuedCall'] = false;
		if (in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_TO_RESEND)) { // queue when the error was not an implementation error
			$this->putCallIntoQueue($submissionId);
			$rqcResult['enqueuedCall'] = true; // for logging/response
		}
		return $rqcResult;
	}


	/**
	 * Analyze RQC response and react:
	 * Upon a successful call, redirects as indicated by RQC.
	 * Upon an unsuccessful call, shows a simple HTML page with the entire JSON response for diagnosis.
	 */
	function processRqcResponse($statuscode, $jsonarray)
	{
		if ($statuscode == 303) {  // that's what we expect: redirect
			header("HTTP/1.1 303 See Other");
			header("Location: " . $jsonarray['redirect_target']);
		} else {  // hmm, something is very, very wrong: Print the json.
			foreach ($jsonarray as $key => $value) {
				print("<pre>$key: " . print_r($value, true) . "</pre><br>"); // <pre> to be \n-safe e.g.
			}
		}
	}

	/**
	 * Resend reviewing data for one submission to RQC after a previous call failed. (Delayed call to RQC)
	 * Called by DelayedRqcCallSender.
	 * @return array  "status" and "response" information
	 */
	public function resend($submissionId): array
	{
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);
		$contextId = $submission->getContextId();
		$rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		$rqcResult = RqcCall::callMhsSubmission($this->plugin->rqcServer(), $rqcJournalId, $rqcJournalAPIKey,
			null, $submissionId, !$this->plugin->hasDeveloperFunctions());
		//RqcDevHelper::writeObjectToConsole($rqcResult);
		return $rqcResult;
	}


	public function putCallIntoQueue(int $submissionId): int
	{
		$delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO'); /** @var $delayedRqcCallDao DelayedRqcCallDAO */
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);
		$contextId = $submission->getContextId();
		if ($delayedRqcCallDao->getById($submissionId) != null) { // if there is already a call in the queue for this submission: Delete to not have multiple delayed calls for the same submission
			$delayedRqcCallDao->deleteById($submissionId);
			RqcLogger::logWarning("A delayed rqc call for submission $submissionId was already in the db. Deleted that delayed call in the queue.");
		}
		$delayedRqcCall = $delayedRqcCallDao->newDataObject();
		$delayedRqcCall->setSubmissionId($submissionId);
		$delayedRqcCall->setContextId($contextId);
		$delayedRqcCall->setOriginalTryTs(Core::getCurrentDate());
		$delayedRqcCall->setLastTryTs(null);
		$delayedRqcCall->setRemainingRetries($this->_maxRetriesToResend);
		//RqcDevHelper::writeObjectToConsole($delayedRqcCall);
		return $delayedRqcCallDao->insertObject($delayedRqcCall);
	}
}

