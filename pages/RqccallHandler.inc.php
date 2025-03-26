<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\pages\workflow\WorkflowHandler;
use PKP\plugins\PluginRegistry;
*/
import('pages.workflow.WorkflowHandler');
import('plugins.generic.rqc.classes.RqcCall');
import('plugins.generic.rqc.classes.DelayedRQCCallDAO');
//import('plugins.generic.rqc.classes.DelayedRQCCall');

define("RQC_STATUS_CODES_TO_RESEND", array(
	408, // "Request Timeout"
	500, // "Internal Server Error"
	501, // "Not Implemented"
	502, // "Bad Gateway"
	503, // "Service Unavailable"
	504, // "Gateway Timeout"
	505, // "HTTP Version Not Supported"
	0 // Unabled to communicate with the server. Aka connection closed, ...
)); // which status codes make the system put the call into the queue to retry later (as opposed to ignoring it because the call was invalidly build)
define("RQC_STATUS_SUCESS", array(
	200, // "OK"
	201, // "Created"
	202, // "Accepted"
	203, // "Non-Authoritative Information"
	204, // "No Content"
	205, // "Reset Content"
	206, // "Partial Content"
));
define("RQC_RESEND_CANCELED", -1);
define("RQC_RESEND_SUCCESS", 0);
define("RQC_RESEND_FAILURE", 1);
define("RQC_RESEND_BAD_REQUEST", 2);


/**
 * Class RqccallHandler.
 * The core of the RQC plugin: Retrieve the reviewing data of one submission and send it to the RQC server
 * after the editor dialog has redirected to this page.
 * Making it a separate page helps testing.
 */
class RqccallHandler extends WorkflowHandler
{
	use RqcDevHelper;
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
		$this->plugin->_print("### RqccallHandler::submit() called");
        $qargs = $this->plugin->getQueryArray($request);
        $stageId = $qargs['stageId'];
        if ($stageId != WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            print("<html><body lang='en'>");
            print("stageId is $stageId. ");
            print("This call makes sense only for stageId " . WORKFLOW_STAGE_ID_EXTERNAL_REVIEW . ".");
            print("</body></html>");
            return;
        }
        $user = $request->getUser();
        $context = $request->getContext();
        $submissionId = $qargs['submissionId'];
        $rqcResult = $this->sendToRqc($request, $context->getId(), $submissionId); // Explicit call
        $this->processRqcResponse($rqcResult['status'], $rqcResult['response']);
    }

    /**
     * The workhorse for actually sending one submission's reviewing data to RQC.
     * TODO 1: Upon a network failure or non-response, puts call in queue.
     */
    function sendToRqc($request, $contextId, $submissionId)
    {
		if (!$this->plugin->hasValidRqcIdKeyPair()) {
			return array(
				"status" => "error",
				"response" => "Didn't call RQC because the RQC ID key pair is not valid."
			);
		}
        $rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
        $rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		$rqcResult = RqcCall::callMhsSubmission($this->plugin->rqcServer(), $rqcJournalId, $rqcJournalAPIKey,
								            $request, $contextId, $submissionId,
											!$this->plugin->hasDeveloperFunctions());
		//$this->_print("\n".print_r($rqcResult, true)."\n");
		if (in_array($rqcResult['status'], RQC_STATUS_CODES_TO_RESEND)) {
			$this->putCallIntoQueue($contextId, $submissionId);
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
	 * Returns:
	 * - RQC_RESEND_CANCELED
	 * - RQC_RESEND_SUCCESS
	 * - RQC_RESEND_FAILURE
	 * - RQC_RESEND_BAD_REQUEST
     * Called by DelayedRQCCallsTask.
     * @param $journalId
     * @param $submissionId
     */
    public function resend($request, $contextId, $submissionId) : int
    {
		if (!$this->plugin->hasValidRqcIdKeyPair()) { // TODO Q: What should I do, if the call was queued and then the pair becomes invalid? Also when should I test if the pair is still valid? (because @Prechelt mentioned, that it could become invalid) Only here?
			return RQC_RESEND_CANCELED; /*array(
				"status" => "error",
				"response" => "Didn't call RQC because the RQC ID key pair is not valid."
			);*/
		}
		$rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		$rqcResult = RqcCall::callMhsSubmission($this->plugin->rqcServer(), $rqcJournalId, $rqcJournalAPIKey,
			null, $contextId, $submissionId,
			!$this->plugin->hasDeveloperFunctions());
		//$this->_print("\n".print_r($rqcResult, true)."\n");
		if (in_array($rqcResult['status'], RQC_STATUS_SUCESS)) {
			return RQC_RESEND_SUCCESS;
		} else if (in_array($rqcResult['status'], RQC_STATUS_CODES_TO_RESEND)) {
			return RQC_RESEND_FAILURE;
		} else {
			return RQC_RESEND_BAD_REQUEST;
		}
    }


	public function putCallIntoQueue(int $submissionId) : int
	{
		$delayedRQCCallDao = new DelayedRQCCallDAO(); //DAORegistry::getDAO('DelayedRQCCallsDAO');
		$delayedRQCCall = $delayedRQCCallDao->newDataObject();
		$delayedRQCCall->setSubmissionId($submissionId);
		$delayedRQCCall->setOriginalTryTS(time());	// TODO Q: Timezonesafe?
		$delayedRQCCall->setLastTryTS(null);	// TODO Q: Timezonesafe?
		$delayedRQCCall->setRetries(0);
		return $delayedRQCCallDao->insertObject($delayedRQCCall);
	}
}

