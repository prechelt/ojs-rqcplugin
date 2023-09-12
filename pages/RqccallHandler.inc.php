<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\pages\workflow\WorkflowHandler;
use PKP\plugins\PluginRegistry;
*/
import('pages.workflow.WorkflowHandler');
import('plugins.generic.rqc.classes.RqcCall');

/**
 * Class RqccallHandler.
 * The core of the RQC plugin: Retrieve the reviewing data of one submission and send it to the RQC server
 * after the editor dialog has redirected to this page.
 * Making it a separate page helps testing.
 */
class RqccallHandler extends WorkflowHandler
{
    public function __construct()
    {
        $this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
        $this->addRoleAssignment(
            array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT),
            array('submit',));
        parent::__construct();
    }

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
        $rqc_result = $this->sendToRqc($request, $context->getId(), $submissionId);
        $this->processRqcResponse($rqc_result['status'], $rqc_result['response']);
    }

    /**
     * The workhorse for actually sending one submission's reviewing data to RQC.
     * TODO: Upon a network failure or non-response, puts call in queue.
     */
    function sendToRqc($request, $contextId, $submissionId)
    {
        $rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
        $rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		return RqcCall::call_mhs_submission($this->plugin->rqc_server(), $rqcJournalId, $rqcJournalAPIKey,
								            $request, $contextId, $submissionId,
											!$this->plugin->has_developer_functions());
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
		}
    }

    /**
     * Resend reviewing data for one submission to RQC after a previous call failed.
     * Called by DelayedRQCCallsTask.
     * @param $journal_id
     * @param $submission_id
     */
    public function resend($journal_id, $submission_id)
    {
        // TODO!!!
    }
}

