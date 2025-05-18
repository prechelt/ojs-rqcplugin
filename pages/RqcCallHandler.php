<?php

namespace APP\plugins\generic\rqc\pages;

use APP\core\Application;
use APP\facades\Repo;
use APP\pages\workflow\WorkflowHandler;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;

use APP\plugins\generic\rqc\classes\RqcCall;
use APP\plugins\generic\rqc\classes\DelayedRqcCall\DelayedRqcCallDAO;
use APP\plugins\generic\rqc\classes\RqcLogger;
use APP\plugins\generic\rqc\classes\RqcDevHelper;


define("RQC_CALL_STATUS_CODES_TO_RESEND", array(
	408, // "Request Timeout"
	428, // "Too Many Requests"
	500, // "Internal Server Error"
	501, // "Not Implemented"
	502, // "Bad Gateway"
	503, // "Service Unavailable"
	504, // "Gateway Timeout"
	505, // "HTTP Version Not Supported"
	506, // "Variant Also Negotiates"
	507, // "Insufficient Storage"
	508, // "Loop detected"
	510, // "Not Extended"
	511, // "Network Authentication Required"
	0 // Unabled to communicate with the server. Aka connection closed, ...
)); // these status codes make the system put the call into the queue to retry later (as opposed to ignoring it because the call was invalidly build)
// use 200, 303, 400, 403, 404 explicitly and/or catch via "else"

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
			array(Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT),
			array('submit',));
		parent::__construct();
	}

	/**
	 * Confirm submission+redirection to RQC
	 * submit function of the popup form that is created by RqcEditorDecisionHandler::rqcGrade()
	 */
	public function submit($args, $request): void
	{
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
		$this->processRqcResponse($rqcResult, $submissionId, true);
	}

	/**
	 * The workhorse for actually sending one submission's reviewing data to RQC.
	 * @return array  "status" and "response" information
	 */
	function sendToRqc($request, int $submissionId): array
	{
        $submission = Repo::submission()->get((int) $submissionId);
        $contextId = $submission->getData('contextId');
		$rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		return RqcCall::callMhsSubmission($this->plugin->rqcServer(), $rqcJournalId, $rqcJournalAPIKey,
			$request, $submissionId, !$this->plugin->hasDeveloperFunctions());
	}

	/**
	 * Resend reviewing data for one submission to RQC after a previous call failed. (Delayed call to RQC)
	 * Called by DelayedRqcCallSender.
	 * @return array  "status" and "response" information
	 */
	public function resend($submissionId): array
	{
		return $this->sendToRqc(null, $submissionId); // Delayed call
	}

	/**
	 * Analyze RQC response and react:
	 * Upon a successful call, redirects (explicit=>303) or does nothing (200 or implicit=>303)
	 * Upon an unsuccessful call, log the error (and show a simple HTML page with the entire JSON response for diagnosis if explicit call)
	 * Upon a network failure or non-response, puts call in queue (if "status" is in RQC_CALL_STATUS_CODES_TO_RESEND)
	 * Logging depending on http status
	 */
	function processRqcResponse(array $rqcCallResult, int $submissionId, bool $explicitCall): void
	{
		$statusCode = $rqcCallResult['status'];
		$responseBodyArray = $rqcCallResult['response'];
		$postRequestBody = $rqcCallResult['request'];
		$logMessageStarter = $explicitCall ? "Explicit" : "Implicit";
		if (in_array($statusCode, [200, 303])) { // request successful
			if ($explicitCall && $statusCode == 200) {
				print(__('plugins.generic.rqc.editoraction.grade.notRedirect')); // show message that no redirect is needed for explicit call // TODO Q: is the message right?
				RqcLogger::logInfo("Explicit call to RQC for submission $submissionId successful: Redirect not needed!");
			} else if ($explicitCall && $statusCode == 303) {
                $request = Application::get()->getRequest();
                $request->redirectUrl($responseBodyArray['redirect_target']);
				RqcLogger::logInfo("Explicit call to RQC for submission $submissionId successfully redirected");
			} else { // implicit call is successful for both statusCodes (because it doesn't redirect)
				RqcLogger::logInfo("Implicit call to RQC for submission $submissionId successful");
			}
		} else { // request unsuccessful
			if (in_array($statusCode, RQC_CALL_STATUS_CODES_TO_RESEND)) { // error: probably not an implementation error
				$this->putCallIntoQueue($submissionId);
				RqcLogger::logWarning("$logMessageStarter call to RQC for submission $submissionId resulted in status "
					. $statusCode . " with response body " . json_encode($responseBodyArray)
					. "\nInserted it into the db to be retried later as a delayed rqc call.");
			} else { // something else went wrong (implementation error or else)
				RqcLogger::logError("$logMessageStarter call to RQC for submission $submissionId resulted in status "
					. $statusCode . " with response body " . json_encode($responseBodyArray)
					. "\nThe call was probably faulty (and wasn't put into the queue to retry later).\nThe original post request body: "
					. json_encode($postRequestBody));
			}
			if ($explicitCall) { // show the error if explicit call
				foreach ($responseBodyArray as $key => $value) {
					print("<pre>$key: " . print_r($value, true) . "</pre>"); // <pre> to be \n-safe e.g.
				}
			}
		}
	}

	/**
	 * @return int The ID of the new delayedRqcCall that is generated and put into the queue
	 */
	public function putCallIntoQueue(int $submissionId): int
	{
        /** @var $delayedRqcCallDao DelayedRqcCallDAO */
		$delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO');
        $submission = Repo::submission()->get($submissionId);
        $contextId = $submission->getData('contextId');
		$delayedRqcCallDao->deleteCallsBySubmissionId($submissionId); // if there is already a call in the queue for this submission: Delete to not have multiple delayed calls for the same submission
		$delayedRqcCall = $delayedRqcCallDao->newDataObject();
		$delayedRqcCall->setSubmissionId($submissionId);
		$delayedRqcCall->setContextId($contextId);
		$delayedRqcCall->setOriginalTryTs(Core::getCurrentDate());
		$delayedRqcCall->setLastTryTs(Core::getCurrentDate());
		$delayedRqcCall->setRemainingRetries($this->_maxRetriesToResend);
		//RqcDevHelper::writeObjectToConsole($delayedRqcCall);
		return $delayedRqcCallDao->insert($delayedRqcCall);
	}
}

