<?php

namespace APP\plugins\generic\rqc\classes;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PKP\observers\events\DecisionAdded;
use PKP\plugins\Hook;
use APP\facades\Repo;

use APP\plugins\generic\rqc\pages\RqcCallHandler;
use APP\plugins\generic\rqc\classes\RqcData;
use APP\plugins\generic\rqc\classes\RqcDevHelper;


/**
 * RQC adapter parts revolving around editorial decisions using the hooking mechanism.
 *
 * @ingroup plugins_generic_rqc
 */
class EditorActions
{
	/**
	 * Register callbacks. This is to be called from the plugin's register().
	 */
	public function register(): void
    {
        Hook::add('LoadComponentHandler', $this->callbackEditorActionRqcGrade(...));
        Hook::add('Workflow::Decisions', $this->callbackModifyDecisionOptions(...)); // TODO 3.5: https://docs.pkp.sfu.ca/dev/release-notebooks/en/3.4-release-notebook#editoraction
        Hook::add('LoadHandler', $this->callbackPageHandlers(...));
        Event::subscribe($this);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DecisionAdded::class, self::class . '@handleDecisionAdded');
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
	 * Callback for Workflow::Decisions.
	 * Adds button "RQC-Grade the Reviews" to the Workflow page.
	 */
	public function callbackModifyDecisionOptions($hookName, $args): bool
	{
        $decisionTypes = &$args[0];
        $stageId = $args[1];
        //$submission = $args[2]; // maybe add that to the hook

        //$decisionTypes[] = new RQCGrade();

//		$completedAssignments = Repo::reviewAssignment()->getCollector()
//            ->filterBySubmissionIds([$submission->getId()])
//            ->filterByLastReviewRound(true)
//            ->filterByStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)
//            ->filterByCompleted(true)
//            ->getMany(); // TODO 1: right like that?
//        $atLeastOneReviewSubmitted = !$completedAssignments->isEmpty();
//		if ($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW && $atLeastOneReviewSubmitted) { // stage 3 && at least one review has been submitted
//			//----- add button for RQC grading:
//            $decisionTypes[] = new RQCGrade(); /*[
//				'operation' => 'rqcGrade',
//				'name'      => 'rqcGradeName',
//				'title'     => 'plugins.generic.rqc.editoraction.grade.button',
//			];*/
//			// RqcDevHelper::writeToConsole("### rqcGrade Button added");
//		} else {
//			// RqcDevHelper::writeToConsole("### no rqcGrade Button added: wrong stage");
//		}
		return false;  // proceed with other callbacks, if any
	}

	/**
	 * Callback for installing page handlers.
	 */
	public function callbackPageHandlers($hookName, $params): bool
	{
		$page =& $params[0];
		$op =& $params[1];
		if ($page == 'rqccall') {
			define('HANDLER_CLASS', 'RqcCallHandler');
			return true;
		}
		return false;
	}

    /**
     * Handler for DecisionAdded
     * We send data to RQC like for a non-interactive call and no redirection is performed
     * in order to give the editors full control over when they want to visit RQC.
     * (Besides, redirection would be enormously difficult in the OJS control flow.)
     * @see lib.pkp.classes/decision/Repository::add
     */
    public function handleDecisionAdded(DecisionAdded $event): void
    {
        $submissionId = $event->submission->getId();
        $decisionType = $event->decisionType;
        //--- ignore non-decision:
        if (Repo::decision()->isRecommendation($decisionType->getDecision()) ||
            !RqcData::isDecision($decisionType->getDecision())) {
            // RqcDevHelper::writeToConsole("### callbackRecordDecision ignores the $theDecision|$theStatus call\n");
            return;
        }
        //--- act on decision:
        // RqcDevHelper::writeToConsole("### callbackRecordDecision calls RQC ($theDecision|$theStatus)\n");
        $caller = new RqcCallHandler();
        $rqcResult = $caller->sendToRqc(null, $submissionId); // Implicit call
        $caller->processRqcResponse($rqcResult, $submissionId, false);
        // RqcDevHelper::writeObjectToConsole($rqcResult);
    }
}
