<?php

namespace APP\plugins\generic\reviewqualitycollector;



use APP\pages\workflow\WorkflowHandler;
use PKP\plugins\PluginRegistry;

/**
 * Class RqccallHandler.
 * The core of the RQC plugin: Retrieve the reviewing data of one submission and send it to the RQC server.
 */
class RqccallHandler extends WorkflowHandler
{
    public function __construct()
    {
        $this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
        $this->rqcdata = new RqcData();
        $this->addRoleAssignment(
            array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT),
            array('submit',));
        $this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
        parent::__construct();
    }

    function _print($msg)
    {
        # print to php -S console stream (to be used during development only; remove calls in final code)
        if (RQCPlugin::has_developer_functions()) {
            fwrite($this->stderr, $msg);
        }
    }

    public function submit($args, $request)
    {
        $qargs = $request->getQueryArray();
        $stageId = $qargs['stageId'];
        if ($stageId != WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            print("<html><body>");
            print("stageId is $stageId.");
            print("This call makes sense only for stageId " . WORKFLOW_STAGE_ID_EXTERNAL_REVIEW . ".");
            print("</body></html>");
            return;
        }
        $user = $request->getUser();
        $context = $request->getContext();
        $submissionId = $qargs['submissionId'];
        [$statuscode, $json] = $this->_sendToRqc($user, $context, $submissionId);
        $this->_processRqcResponse($statuscode, $json);
    }

    /**
     * The workhorse for actually sending one submission's reviewing data to RQC.
     * TODO: Upon a network failure or non-response, puts call in queue.
     */
    function _sendToRqc($user, $journal, $submissionId)
    {
        $contextId = $journal->getId();
        $rqcJournalId = $this->plugin->getSetting($contextId, 'rqcJournalId');
        $rqcJournalAPIKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
        $data = $this->rqcdata->rqcdata_array($user, $journal, $submissionId);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        // $this->_print($json);
        $url = sprintf("%s/j/mhsapi/%s/%s", $this->plugin->rqc_server(), $rqcJournalId, $submissionId);
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
        $status = curl_getinfo($cc, CURLINFO_HTTP_CODE);
        curl_close($cc);
        return array($status, $output);
    }


    /**
     * Analyze RQC response and react:
     * Upon a successful call, redirects as indicated by RQC.
     * Upon an unsuccessful call, shows a simple HTML page with the entire JSON response for diagnosis.
     */
    function _processRqcResponse($statuscode, $json)
    {
        // $this->_print(substr($json, 20));
        if (substr($json, 0, 1) != "<")  # normal start: JSON "{", in error cases perhaps "<!DOCTYPE html>"
            header('Content-Type: application/json');
        print($json);
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

    /**
     * Perform an https call.
     * @param $url
     * @param $content
     * @return bool|string
     */
    public function _do_post($url, $content)
    {
        // http://unitstep.net/blog/2009/05/05/using-curl-in-php-to-access-https-ssltls-protected-sites/
        $ch = curl_init($url);  // curl handle
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
