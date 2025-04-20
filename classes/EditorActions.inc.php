<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use PKP\plugins\Hook;
*/

import('lib.pkp.classes.plugins.HookRegistry');
import('lib.pkp.classes.submission.reviewRound.ReviewRoundDAO');
import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignmentDAO');
import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');

import('plugins.generic.rqc.pages.RqcCallHandler');
import('plugins.generic.rqc.classes.RqcData');
import('plugins.generic.rqc.classes.RqcDevHelper');
import('plugins.generic.rqc.classes.RqcLogger');

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
			array($this, 'callbackPageHandlers')
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
		//RqcDevHelper::writeObjectToConsole($lastReviewRound->determineStatus(), "### Lastreviewroundstatus: ");

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$assignments = $reviewAssignmentDao->getBySubmissionId($submission->getId(), $lastReviewRound->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW); // get all assignments of external reviewers in the current review round
		$atLeastOneReviewSubmitted = false; // I don't use $lastReviewRound->getStatus() because for some status it's not sure if at least one review is submitted
		foreach ($assignments as $reviewAssignment) {
			$reviewAssignmentStatus = $reviewAssignment->getStatus(); // get status of the assignment of the external reviewer
			switch ($reviewAssignmentStatus) {
				case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
				case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
				case REVIEW_ASSIGNMENT_STATUS_THANKED:
					$atLeastOneReviewSubmitted = true;
					break 2; // break out of foreach (found at least one submitted review)
				default: // review not submitted
					break 1; // only break out of switch
			}
		}
		if ($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW && $atLeastOneReviewSubmitted) { // stage 3 && at least one review has been submitted
			//----- add button for RQC grading:
			$decisionOpts[SUBMISSION_EDITOR_TRIGGER_RQCGRADE] = [
				'operation' => 'rqcGrade',
				'name'      => 'rqcGradeName',
				'title'     => 'plugins.generic.rqc.editoraction.grade.button',
			];
			// RqcDevHelper::writeToConsole("### rqcGrade Button added");
		} else {
			// RqcDevHelper::writeToConsole("### no rqcGrade Button added: wrong stage");
		}
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
	 * Callback for EditorAction::recordDecision.
	 * See lib.pkp.classes.EditorAction::recordDecision for the $args.
	 * We send data to RQC like for a non-interactive call and no redirection is performed
	 * in order to give the editors full control over when they want to visit RQC.
	 * (Besides, redirection would be enormously difficult in the OJS control flow.)
	 */
	public function callbackRecordDecision($hookName, $args): bool
	{
		$GO_ON = false;  // false continues processing (default), true stops it (for testing during development).
		$submission = &$args[0];
		$submissionId = $submission->getId();
		$decision = &$args[1];
		$isRecommendation = &$args[3];
		// RqcDevHelper::writeToConsole("### callbackRecordDecision called\n");
		$theDecision = $decision['decision'];
		$theStatus = $isRecommendation ? 'is-rec-only' : 'is-decision';
		//--- ignore non-decision:
		if ($isRecommendation || !RqcOjsData::isDecision($theDecision)) {
			// RqcDevHelper::writeToConsole("### callbackRecordDecision ignores the $theDecision|$theStatus call\n");
			return $GO_ON;
		}
		//--- act on decision:
		// RqcDevHelper::writeToConsole("### callbackRecordDecision calls RQC ($theDecision|$theStatus)\n");
		$caller = new RqcCallHandler();
		$rqcResult = $caller->sendToRqc(null, $submissionId); // Implicit call
		// TODO Q: what to do if something not send (attachments) or truncated: Logging and/or popup for the current user? (see RqcEditorDecisionHandler::rqcGrade())
		// TODO Q: what to do if some data is faulty (use response as popup? and/or just logging?)
		// TODO Q: if check for redirect is there: make the software do that redirect // TODO Q: 303 if grading didn't happen yet. Else 200? Would make sense and would make the potential "grading happend" check (as a call) irrelevant (less logic)
		if (in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_SUCESS)) {
			RqcLogger::logInfo("Implicit call to RQC for submission $submissionId successful");
		} else {
			if ($rqcResult['enqueuedCall']) {
				RqcLogger::logWarning("Implicit call to RQC for submission $submissionId resulted in status " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . "Inserted it into the db to be retried later as a delayed rqc call.");
			} else {
				RqcLogger::logError("Implicit call to RQC for submission $submissionId resulted in status " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . " The call was probably faulty (and wasn't put into the queue to retry later).\nThe original post request body: " . json_encode($rqcResult['request']) . "\n");
			}
		}
		// RqcDevHelper::writeObjectToConsole($rqcResult);
		return $GO_ON;
	}
}
