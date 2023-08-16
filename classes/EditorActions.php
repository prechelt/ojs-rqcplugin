<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/EditorActions.php
 *
 * Copyright (c) 2022 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class EditorActions
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief RQC adapter parts revolving around editorial decisions.
 */


namespace APP\plugins\generic\reviewqualitycollector;

use PKP\plugins\Hook;

/**
 * ...
 */
class EditorActions extends RqcDevHelper
{

    /**
     * Register callbacks. This is to be called from the plugin's register().
     */
    public function register()
    {
        Hook::add(
            'LoadComponentHandler',
            array($this, 'cb_editorActionRqcGrade')
        );
        Hook::add(
            'EditorAction::modifyDecisionOptions',
            array($this, 'cb_modifyDecisionOptions')
        );
        Hook::add(
            'LoadHandler',
            array($this, 'cb_pagehandlers')
        );
        Hook::add(
            'EditorAction::recordDecision',
            array($this, 'cb_recordDecision')
        );

    }

    /**
     * Callback for LoadComponentHandler.
     * Directs clicks of button "RQC-Grade the Reviews" to RqcEditorDecisionHandler.
     */
    public function cb_editorActionRqcGrade($hookName, $args)
    {
        $component = &$args[0];
        $op = &$args[1];
        if ($component == 'modals.editorDecision.EditorDecisionHandler' &&
            $op == 'rqcGrade') {
            $component = 'plugins.generic.reviewqualitycollector.components.editorDecision.RqcEditorDecisionHandler';
            return true;  // no more handling needed
        }
        return false;  // proceed with normal processing
    }


    /**
     * Callback for EditorAction::modifyDecisionOptions.
     * Adds button "RQC-Grade the Reviews" to the Workflow page.
     */
    public function cb_modifyDecisionOptions($hookName, $args)
    {
        $context = $args[0];
        $submission = $args[1];
        $stageId = $args[2];
        $makeDecision = &$args[3];
        $decisionOpts = &$args[4];  // result
        if ($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            //----- add button for RQC grading:
            $decisionOpts[SUBMISSION_EDITOR_TRIGGER_RQCGRADE] = [
                'operation' => 'rqcGrade',
                'name' => 'rqcGradeName',
                'title' => 'plugins.generic.reviewqualitycollector.editoraction.grade.button',
            ];
        }
        return false;  // proceed with other callbacks, if any
    }

    /**
     * Callback for installing page handlers.
     */
    public function cb_pagehandlers($hookName, $params)
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
     */
    public function cb_recordDecision($hookName, $args)
    {
        import('plugins.generic.reviewqualitycollector.classes.RqcData');
        $GO_ON = true;  // false continues processing, true stops it (needed during development).
        $submission = &$args[0];
        $decision = &$args[1];
        $result = &$args[2];
        $is_recommendation = &$args[3];
        $this->_print('### cb_recordDecision called');
        if ($is_recommendation || !RqcOjsData::is_decision($decision)) {
            return $GO_ON;
        }
        // act on decision:
        $this->_print('### cb_recordDecision calls RQC');
        return $GO_ON;
    }
}
