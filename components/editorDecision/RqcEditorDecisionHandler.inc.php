<?php

/**
 * @file plugins/generic/reviewqualitycollector/components/editorDecision/RqcEditorDecisionHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcEditorDecisionHandler
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Handle modal dialog before submitting and redirecting to RQC.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\reviewqualitycollector;
use APP\core\Application;
use APP\core\PageRouter;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\handler\PKPHandler;
use PKP\plugins\PluginRegistry;
*/
import('classes.handler.Handler');

class RqcEditorDecisionHandler extends PKPHandler {
    function __construct()
    {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
        $this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
    }

    public function _print($msg) {
        # print to php -S console stream (to be used during development only; remove calls in final code)
        if (RQCPlugin::has_developer_functions()) {
            fwrite($this->stderr, $msg);
        }
    }

    /**
     * Confirm redirection to RQC.
     */
    function rqcGrade($args, $request)
    {
        //----- prepare processing:
        $requestArgs = $request->getQueryArray();
        $context = $request->getContext();
        $submissionId = $requestArgs['submissionId'];
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        //----- modal dialog:
        $pageRouter = new PageRouter();
        $pageRouter->setApplication(Application::get());  // so that url() will find context
        // $this->_print("### context: " . $context->getId() . ":" . $context->getPath() .
        //	"; pageRouter._contextList: '" . json_encode($pageRouter->_contextList) . "'\n");
        # url($request, $newContext = null, $page = null, $op = null, $path = null, $params = null, $anchor = null, $escape = false)
        $target = $pageRouter->url($request, null, 'rqccall', 'submit', null,
            array('submissionId' => $submissionId, 'stageId' => $submission->getStageId()));
        $okButton = "<a href='$target' class='pkp_button_primary submitFormButton'>" . __('common.ok') . '</a>';  // TODO: set focus
        // $cancelButton = '<a href="#" class="pkp_button pkpModalCloseButton cancelButton">' . __('common.cancel') . '</a>';  // TODO: does not do anything
        $content = __('plugins.generic.reviewqualitycollector.editoraction.grade.explanation');
        $buttons = "<p>$okButton</p>";  // TODO: add a working cancel button, use PKP button bar layout
        return new JSONMessage(true, "$content$buttons");
    }
}
