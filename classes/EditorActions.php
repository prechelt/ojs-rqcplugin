<?php

namespace APP\plugins\generic\rqc\classes;

use APP\decision\Decision;
use APP\decision\types\Accept;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\steps\PromoteFiles;
use PKP\decision\types\SendToProduction;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\mailables\DecisionNewReviewRoundNotifyAuthor;
use PKP\plugins\Hook;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PKP\observers\events\DecisionAdded;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

use APP\plugins\generic\rqc\pages\RqcCallHandler;
use APP\plugins\generic\rqc\classes\RqcDevHelper;
use PKP\user\User;


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
        // e.g. test with http://localhost:8001/index.php/test/dashboard/editorial?currentViewId=external-review&workflowSubmissionId=1&workflowMenuKey=workflow_3_1

        $decisionTypes = &$args[0];
        $stageId = $args[1];
        //$submission = $args[2]; // maybe add that to the hook?

        RqcDevHelper::writeObjectToConsole($decisionTypes);

        $decisionTypes[] = new RQCGrade();

        RqcDevHelper::writeObjectToConsole($decisionTypes);

        // the old way
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



class RQCGrade extends DecisionType
{
    public function getDecision(): int
    {
        RqcDevHelper::writeObjectToConsole("hi", "hi", true);
        return Decision::NEW_EXTERNAL_ROUND;
    }

    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return "RQC-Grade the reviews"; // __('editor.submission.decision.newReviewRound', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return "RQC-Grade the reviews"; // __('editor.submission.decision.newReviewRound', [], $locale);
    }

    public function getLog(): string
    {
        return "RQC-Grade the reviews"; // __('editor.submission.decision.newReviewRound', [], $locale);
    }

    public function getCompletedLabel(): string
    {
        return "RQC-Grade the reviews"; // __('editor.submission.decision.newReviewRound', [], $locale);
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return "RQC-Grade the reviews"; // __('editor.submission.decision.newReviewRound', [], $locale);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {

    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {

    }

    public function getStageId(): int
    {
        return 3;
    }
}
