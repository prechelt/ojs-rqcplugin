<?php

namespace APP\plugins\generic\rqc\classes;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Form;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

// copied from https://docs.pkp.sfu.ca/dev/plugin-guide/en/example-decision#select-and-send-files
// together with implementing the required methods of a DecisionType

class ExplicitCallDecisionType extends DecisionType
{
    /**
     * Create a unique constant for your decision type.
     *
     * To avoid clashes with the core application, use
     * a number in the 900 range.
     *
     * @see Decision
     */
    public const SEND_FILES_TO_SERVICE = 900;

    /**
     * @see self::SEND_FILES_TO_SERVICE
     */
    public function getDecision(): int
    {
        return self::SEND_FILES_TO_SERVICE; // Decision::ACCEPT;
    }

    /**
     * This decision can be recorded when the submission
     * is in the production stage
     */
    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_PRODUCTION;
    }

    /**
     * Add a step to the record decision UI to select
     * the files to be sent to the third-party service
     *
     * See the dev documentation about forms to learn
     * how to create the SelectFilesForm.
     *
     * @see https://docs.pkp.sfu.ca/dev/documentation/en/frontend-forms
     */
    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = new Steps($this, $submission, $context);

        $steps->addStep(
            new Form(
                'select-files',
                'Select Files',
                'Select the files that should be sent to the third-party service.',
                new SelectFilesForm($submission)
            )
        );

        return $steps;
    }

    /**
     * Check that the files selected by the user match
     * a valid production-ready submission file
     */
    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!isset($props['actions'])) {
            return;
        }

        foreach ((array) $props['actions'] as $index => $action) {

            switch ($action['id']) {

                case 'select-files':
                    if (!is_array($action['selected-files']) || empty($action['selected-files'])) {
                        $validator->errors()->add('actions.' . $index . '.selected-files', 'You must select at least one file.');

                    } else {
                        $ids = Repo::submissionFile()
                            ->getCollector()
                            ->filterBySubmissionIds([$submission->getId()])
                            ->filterByFilestages([
                                SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
                            ])
                            ->getIds();

                        foreach ($action['selected-files'] as $selectedFile) {
                            if (!$ids->contains($selectedFile)) {
                                $validator->errors()->add('actions.' . $index . '.selected-files', 'One or more of the selected files is not valid.');
                                break;
                            }
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Send the selected files to a third-party service
     *
     * This method is only run after validating and recording
     * the decision.
     */
    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case 'select-files':

                    // Send the files

                    break;
            }
        }
    }

    /**
     * Don't change anything
     */
    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        return null;
    }

    /**
     * Don't change anything
     */
    public function getNewStatus(): ?int
    {
        return null;
    }

    /**
     * Don't change anything
     */
    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('plugins.generic.rqc.editoraction.grade.button', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('plugins.generic.rqc.editoraction.grade.button', [], $locale);
    }

    public function getLog(): string
    {
        return 'plugins.generic.rqc.editoraction.grade.button';
    }

    public function getCompletedLabel(): string
    {
        return __('plugins.generic.rqc.editoraction.grade.button');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('plugins.generic.rqc.editoraction.grade.button');
    }
}
