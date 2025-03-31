<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\pages\workflow\WorkflowHandler;
use PKP\plugins\PluginRegistry;
*/
import('pages.workflow.WorkflowHandler');
import('plugins.generic.rqc.classes.RqcCall');
import('plugins.generic.rqc.classes.DelayedRqcCallDAO');
//import('plugins.generic.rqc.classes.DelayedRqcCall');

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
 */
class RqcCallHandler extends WorkflowHandler
{
	use RqcDevHelper;

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
		$this->_print("### RqcCallHandler::submit() called");
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
		$this->processRqcResponse($rqcResult['status'], $rqcResult['response']);
	}

	/**
	 * The workhorse for actually sending one submission's reviewing data to RQC.
	 * Upon a network failure or non-response, puts call in queue.
	 * @return array  "status" and "response" information
	 */
	function sendToRqc($request, int $submissionId): array
	{
		if (!$this->plugin->hasValidRqcIdKeyPair()) {
			return array(
				"status"   => "error",
				"response" => "Didn't call RQC because the RQC ID key pair is not valid."
			);
		}
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);
		$contextId = $submission->getContextId();
		$rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		$rqcResult = RqcCall::callMhsSubmission($this->plugin->rqcServer(), $rqcJournalId, $rqcJournalAPIKey,
			$request, $submissionId, !$this->plugin->hasDeveloperFunctions());
		//$this->_print("\n".print_r($rqcResult, true)."\n");
		if (in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_TO_RESEND)) {
			$this->putCallIntoQueue($submissionId); // TODO => is explicit call? => store interactive-user?
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
		} else {  // hmm, something is very very wrong: Show the JSON response
			header("Content-Type: application/json; charset=utf-8");
			print(json_encode($jsonarray, JSON_PRETTY_PRINT));
			// TODO 3: response that is better readable
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
		$this->_print("\n" . print_r($rqcResult, true) . "\n");
		return $rqcResult;
	}


	public function putCallIntoQueue(int $submissionId): int
	{
		$delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO'); /** @var $delayedRqcCallDao DelayedRqcCallDAO */
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);
		$contextId = $submission->getContextId();
		// TODO Q: if there is already a call in the queue: What should we do then?
		/*if ($delayedRqcCallDao->getById($submissionId) != null) {
			$delayedRqcCallDao->deleteById($submissionId);
		}*/
		$delayedRqcCall = $delayedRqcCallDao->newDataObject();
		$delayedRqcCall->setSubmissionId($submissionId);
		$delayedRqcCall->setContextId($contextId);
		$delayedRqcCall->setOriginalTryTs(date('Y-m-d H:i:s', time()));
		$delayedRqcCall->setLastTryTs(null);
		$delayedRqcCall->setRemainingRetries($this->_maxRetriesToResend);
		//$this->_print("\n".print_r($delayedRqcCall, true)."\n");
		return $delayedRqcCallDao->insertObject($delayedRqcCall);
	}
}

