<?php

/**
 * @file plugins/generic/rqc/classes/EditorActions.inc.php
 *
 * Copyright (c) 2022-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class EditorActions
 * @ingroup plugins_generic_rqc
 *
 * @brief RQC adapter parts revolving around editorial decisions.
 */

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use PKP\plugins\Hook;
*/
import('lib.pkp.classes.plugins.HookRegistry');
import('plugins.generic.rqc.pages.RqccallHandler');

/**
 * ...
 */
class EditorActions
{
	use RqcDevHelper;

    /**
     * Register callbacks. This is to be called from the plugin's register().
     */
    public function register(): void
    {
        HookRegistry::register(
            'LoadComponentHandler',
            array($this, 'callbackEditorActionRqcGrade')
        );
        HookRegistry::register(
            'EditorAction::modifyDecisionOptions',
            array($this, 'callbackModifyDecisionOptions')
        );
        HookRegistry::register(
            'LoadHandler',
            array($this, 'callbackPagehandlers')
        );
        HookRegistry::register(
            'EditorAction::recordDecision',
            array($this, 'callbackRecordDecision')
        );
    }

    /**
     * Callback for LoadComponentHandler.
     * Directs clicks of button "RQC-Grade the Reviews" to RqcEditorDecisionHandler.
     */
    public function callbackEditorActionRqcGrade($hookName, $args): bool
    {
        $component = &$args[0];
        $op = &$args[1];
        if ($component == 'modals.editorDecision.EditorDecisionHandler' &&
            $op == 'rqcGrade') {
            $component = 'plugins.generic.rqc.components.editorDecision.RqcEditorDecisionHandler';
            return true;  // no more handling needed, RqcEditorDecisionHandler will do the work
        }
        return false;  // proceed with normal processing
    }


    /**
     * Callback for EditorAction::modifyDecisionOptions.
     * Adds button "RQC-Grade the Reviews" to the Workflow page.
     */
    public function callbackModifyDecisionOptions($hookName, $args): bool
    {
        $context = $args[0];
        $submission = $args[1];
        $stageId = $args[2];
        $makeDecision = &$args[3];
        $decisionOpts = &$args[4];  // result

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId());
		//$this->_print("\n\n### Lastreviewroundstatus: ".$lastReviewRound->determineStatus()."\n\n");

		if ($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW && $lastReviewRound->determineStatus() != REVIEW_ROUND_STATUS_PENDING_REVIEWERS) { // stage 3 && at least one reviewer assigned // TODO 2: assigned and has agreed? => Question @Prechelt
            //----- add button for RQC grading:
            $decisionOpts[SUBMISSION_EDITOR_TRIGGER_RQCGRADE] = [
                'operation' => 'rqcGrade',
                'name' => 'rqcGradeName',
                'title' => 'plugins.generic.rqc.editoraction.grade.button',
            ];
			// $this->_print("### rqcGrade Button added");
        }
		else {
			// $this->_print("### no rqcGrade Button added: wrong stage");
		}
		return false;  // proceed with other callbacks, if any
    }

    /**
     * Callback for installing page handlers.
     */
    public function callbackPagehandlers($hookName, $params): bool
    {
        $page =& $params[0];
        $op =& $params[1];
        if ($page == 'rqccall') {
            define('HANDLER_CLASS', 'RqccallHandler');
            return true;
        }
        return false;
    }

    /**
     * Callback for EditorAction::recordDecision.
	 * See lib.pkp.classes.EditorAction::recordDecision for the $args.
	 * We send data to RQC like for a non-interactive call and no redirection is performed
	 * in order to give the editors full control over when they want to visit RQC.
	 * (Besides, redirection would be enormously difficult in the OJS control flow.)
     */
    public function callbackRecordDecision($hookName, $args)
    {
        import('plugins.generic.rqc.classes.RqcData');
        $GO_ON = false;  // false continues processing (default), true stops it (for testing during development).
        $submission = &$args[0];
        $decision = &$args[1];
        $isRecommendation = &$args[3];
		// $this->_print("### callbackRecordDecision called\n");
		$theDecision = $decision['decision'];
		$theStatus = $isRecommendation ? 'is-rec-only' : 'is-decision';
		//--- ignore non-decision:
        if ($isRecommendation || !RqcOjsData::isDecision($theDecision)) {
			// $this->_print("### callbackRecordDecision ignores the $theDecision|$theStatus call\n");
            return $GO_ON;
        }
        //--- act on decision:
        // $this->_print("### callbackRecordDecision calls RQC ($theDecision|$theStatus)\n");
		$caller = new RqccallHandler();
		$rqcResult = $caller->sendToRqc(null, $submission->getContextId(), $submission->getId());  // TODO 2: catch failures and queue
		return $GO_ON;
    }
}
