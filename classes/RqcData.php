<?php

namespace APP\plugins\generic\rqc\classes;

use APP\author\Author;
use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\submission\Submission;
use ErrorException;
use Illuminate\Support\Number;
use PKP\config\Config;
use PKP\context\Context;
use PKP\context\ContextDAO;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\decision\Decision;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponse;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\SubmissionComment;
use PKP\submissionFile\SubmissionFile;
use Random\RandomException;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\plugins\Plugin;

use APP\plugins\generic\rqc\RqcPlugin;
use APP\plugins\generic\rqc\classes\RqcDevHelper;

define("RQC_AllOWED_FILE_EXTENSIONS", array(
	"pdf", "docx", "xlsx", "pptx", "odt", "ods", "odp", "odg", "txt"
)); // which files are allowed to be included in the review_set['attachment_set']
define("RQC_ONE_LINE_STRING_SIZE_LIMIT", 2000); // All one-line strings must be no longer than 2000 characters
define("RQC_MULTI_LINE_STRING_SIZE_LIMIT", 200000); // All multi-line strings (the review texts) must be no longer than 200000 characters
define("RQC_AUTHOR_LIST_SIZE_LIMIT", 200); // Author lists must be no longer than 200 entries
define("RQC_OTHER_LIST_SIZE_LIMIT", 20); // Other lists (reviews, editor assignments) must be no longer than 20 entries
define("RQC_ATTACHMENTS_SIZE_LIMIT", 64000000); // Attachments cannot be larger than 64 MB each


/**
 * Builds the JSON-like data object to be sent to the RQC server from the various pieces of the OJS data model:
 * submission, authors, editors, reviewers and reviews, active user, decision, etc.
 *
 * @ingroup  plugins_generic_rqc
 */
class RqcData
{
	private Plugin|null $plugin;

	public const CONFIDENTIAL_FIELD_REGEXP = '/[Cc]onfidential/';  // review form fields with such names are excluded

	public function __construct()
	{
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	/**
	 * Build PHP array with the data for an RQC call to be made.
	 * if $request is null, interactive_user and mhs_submissionpage are transmitted as "".
	 * returns 'data' (the data for the call) and 'truncation_omission_info' (an array of strings: messages which data was truncated or omitted; useful for logging or printing in a popup)
	 */
	public function rqcDataArray(PKPRequest|null $request, int $submissionId): array
	{
        $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var $reviewRoundDao ReviewRoundDAO */
		//----- prepare processing:

        $submission = Repo::submission()->get((int) $submissionId);
        $contextId = $submission->getData('contextId');
        $journal = $contextDao->getById($contextId); /** @var Context $journal */

        //RqcDevHelper::writeObjectToConsole($submission, "submission", true);
		$data = array();
		$truncationOmissionInfo = array();

		//----- fundamentals:
		$data['interactive_user'] = $request ? $this->getInteractiveUser($request) : "";
		$data['mhs_submissionpage'] = $request ? $this->getMhsSubmissionPage($request, $submissionId) : "";

		//----- submission data:
		$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId);
		$reviewroundN = $lastReviewRound->getRound();

		$data['visible_uid'] = $this->getUid($journal, $submission, $reviewroundN);  // user-facing pseudo ID
		$data['external_uid'] = $this->getUid($journal, $submission, $reviewroundN, true); // URL-friendly version.
		$data['title'] = $this->getTitle($submission->getCurrentPublication()->getTitles());
		$data['submitted'] = rqcifyDatetime($submission->getData('dateSubmitted'));

		//----- authors, editor assignments, reviews, decision:
		$authorSetWithAdditionalInfo = $this->getAuthorSet($submission);
		$data['author_set'] = limitToSizeArray($authorSetWithAdditionalInfo['data'], RQC_AUTHOR_LIST_SIZE_LIMIT);
		$editorAssignmentSetWithAdditionalInfo = $this->getEditorAssignmentSet($submissionId);
		$data['edassgmt_set'] = limitToSizeArray($editorAssignmentSetWithAdditionalInfo['data']);
		$reviewSetWithAdditionalInfo = $this->getReviewSet($submissionId, $lastReviewRound, $journal->getId());
		$data['review_set'] = limitToSizeArray($reviewSetWithAdditionalInfo['data']);
		$data['decision'] = $this->getDecision($lastReviewRound);

		//----- add truncation && omission information if the truncated data is not the same as the "original" data
		// we show that the review-text and the four sets are truncated and if attachments are left out because of size limits
		if ($data['author_set'] != $authorSetWithAdditionalInfo['data']) {
			$truncationOmissionInfo[] = "The author set was truncated. Original size: " . count($authorSetWithAdditionalInfo['data']) . ". Truncated to: " . count($data['author_set']) . ". The size limit of this set is: " . RQC_AUTHOR_LIST_SIZE_LIMIT;
		}
		if ($data['edassgmt_set'] != $editorAssignmentSetWithAdditionalInfo['data']) {
			$truncationOmissionInfo[] = "The editor set was truncated. Original size: " . count($editorAssignmentSetWithAdditionalInfo['data']) . ". Truncated to: " . count($data['edassgmt_set']) . ". The size limit of this set is: " . RQC_OTHER_LIST_SIZE_LIMIT;
		}
		if ($data['review_set'] != $reviewSetWithAdditionalInfo['data']) {
			$truncationOmissionInfo[] = "The review set was truncated. Original size: " . count($reviewSetWithAdditionalInfo['data']) . ". Truncated to: " . count($data['review_set']) . ". The size limit of this set is: " . RQC_OTHER_LIST_SIZE_LIMIT;
		}

		// merge the truncation && omission information of the function the called functions
		$truncationOmissionInfo = array_merge($truncationOmissionInfo, $authorSetWithAdditionalInfo['truncation_omission_info']);
		$truncationOmissionInfo = array_merge($truncationOmissionInfo, $editorAssignmentSetWithAdditionalInfo['truncation_omission_info']);
		$truncationOmissionInfo = array_merge($truncationOmissionInfo, $reviewSetWithAdditionalInfo['truncation_omission_info']);

		return array('data' => $data, 'truncation_omission_info' => $truncationOmissionInfo);
	}

	/**
	 * Build a linear array of RQC-ish attachment objects.
	 * returns 'data' (the attachment objects) and 'truncation_omission_info' (an array of strings: messages which data was truncated or omitted; useful for logging or printing in a popup)
	 */
	protected static function getAttachmentSet(ReviewAssignment $reviewAssignment): array
	{
		$attachmentSet = array(); // the real result
		$truncationOmissionInfo = array(); // if something is left out or truncated or else
		//RqcDevHelper::writeToConsole("\nReviewer: ".$reviewAssignment->getReviewerFullName()." with Id: ".$reviewAssignment->getReviewerId()."\n");

        $attachments = Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$reviewAssignment->getSubmissionId()])
            ->filterByReviewRoundIds([$reviewAssignment->getReviewRoundId()])
            ->filterByUploaderUserIds([$reviewAssignment->getReviewerId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT])
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, [$reviewAssignment->getId()])
            ->getMany();


        /** @var SubmissionFile $submissionFile */
        foreach ($attachments as $submissionFile) {
			$attachment = array();
			$submissionFileName = englishest($submissionFile->getData('name'), false);
			$ext = pathinfo($submissionFileName, PATHINFO_EXTENSION);
			if (!in_array($ext, RQC_AllOWED_FILE_EXTENSIONS)) {
				$truncationOmissionInfo[] = "$submissionFileName could not be included because the file extension $ext is not supported by RQC. Supported file extensions: " . implode(", ", RQC_AllOWED_FILE_EXTENSIONS);
				continue;
			}

            $fullPath = rtrim(Config::getVar('files', 'files_dir'), '/') . '/' . $submissionFile->getData('path');
            //RqcDevHelper::writeToConsole($fullPath);

            set_error_handler(function ($severity, $message, $file, $line) {
                throw new \ErrorException($message, $severity, $severity, $file, $line);
            }); // file_get_contents may throw an warning. Warnings aren't try-catchable => convert to error to catch and then reset the error handler
            try {
                $fileContent = file_get_contents($fullPath);
            } catch (ErrorException $e) {
                $fileContent = false;
            } finally {
                restore_error_handler();
            }
			if ($fileContent === false) { // it may throw a warning as a not halting error or return false
				$truncationOmissionInfo[] = $fullPath . " could not be found";
				continue;
			}

			$fileSize = filesize($fullPath);
			if ($fileSize > RQC_ATTACHMENTS_SIZE_LIMIT) {
				$truncationOmissionInfo[] = "$submissionFileName could not be included because the file size exceeds the size limit of RQC: " . Number::fileSize(RQC_ATTACHMENTS_SIZE_LIMIT, 2);
				continue;
			}

            //RqcDevHelper::writeObjectToConsole($fileContent, $submissionFileName);
			$attachment['filename'] = $submissionFileName;
			$attachment['data'] = base64_encode($fileContent);
			$attachmentSet[] = $attachment;
		}
        //RqcDevHelper::writeObjectToConsole($attachmentSet);
        //RqcDevHelper::writeObjectToConsole($truncationOmissionInfo);
        $attachmentSet = []; // TODO 3: base64_encoded content gives me an error 500 from the server (til then I only give empty attachment sets)
		return array('data' => $attachmentSet, 'truncation_omission_info' => $truncationOmissionInfo);
	}

	/**
	 * Build a linear array of RQC-ish author objects.
	 * returns 'data' (the author objects) and 'truncation_omission_info' (an array of strings: messages which data was truncated or omitted; useful for logging or printing in a popup)
	 */
	protected static function getAuthorSet(Submission $submission): array
	{
        $authorObjects = $submission->getLatestPublication()->getData('authors');

		$authors = array(); // the real result
		$truncationOmissionInfo = array(); // if something is left out or truncated or else
		foreach ($authorObjects as $authorObject) { /** @var $authorObject Author */
			// TODO if issue is closed: https://github.com/pkp/pkp-lib/issues/6178
			if (false) // if (!(bool)$authorObject->isCorrespondingAuthor()) // currently not available AND (primaryAuthor or includeInBrowse don't suffice/fulfill that role!)
				continue;  // skip non-corresponding authors
			//RqcDevHelper::writeObjectToConsole($authorObject, "AuthorObject in getAuthorSet(): ");
			$rqcAuthor = array();
			$rqcAuthor['email'] = $authorObject->getEmail();
			$rqcAuthor['firstname'] = getNonlocalizedAttr($authorObject, "getGivenName");
			$rqcAuthor['lastname'] = getNonlocalizedAttr($authorObject, "getFamilyName");
			$rqcAuthor['order_number'] = (int)($authorObject->getSequence());
			$rqcAuthor['orcid_id'] = getOrcidId($authorObject->getOrcid());
			$authors[] = $rqcAuthor;
		}
		return array('data' => $authors, 'truncation_omission_info' => $truncationOmissionInfo);
	}

	/**
	 * Return RQC-style decision string.
	 * The data is found in EditDecision objects. There can be multiple ones, from different editors.
	 * Some describe recommendations, others decisions.
	 * We use decisions only and return an 'unknown' if there are only recommendations.
	 * We simply use the first decision we see.
	 */
	protected function getDecision(ReviewRound $reviewRound): string
	{
		// See EditDecisionDAO->getEditorDecisions, $this->rqcDecision
        $reviewRoundDecisions = Repo::decision()->getCollector()
            ->filterBySubmissionIds([$reviewRound->getSubmissionId()])
            ->filterByStageIds([$reviewRound->getStageId()])
            ->filterByReviewRoundIds([$reviewRound->getId()])
            ->getMany();

		for ($i = sizeof($reviewRoundDecisions) - 1; $i >= 0; $i--) { // ordered by ASC Date: most recent decision last
			$result = $this->rqcDecision("editor", $reviewRoundDecisions[$i]->getData('decision'));
			if ($result) {  // use the last non-undefined decision
				return $result;
			}
		}
		return "";  // only recommendations found, no decisions
	}

	/**
	 * Build a linear array of RQC editorship descriptor objects.
	 * returns 'data' (the editorship descriptor objects)
     * and 'truncation_omission_info' (an array of strings: messages which data was truncated or omitted; useful for logging or printing in a popup)
	 */
	protected function getEditorAssignmentSet(int $submissionId): array
	{
		$editorAssignments = array();  // the real result
		$truncationOmissionInfo = array();  // if something is left out or truncated or else

        $stageAssignments = StageAssignment::with(['userGroup'])
            ->withSubmissionIds([$submissionId])
            ->withStageIds([WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])
            ->get();
		$level1N = 0;
		foreach ($stageAssignments as $stageAssignment) {
			$assignment = array();
            $userGroup = $stageAssignment->userGroup;
			$levelMap = array(Role::ROLE_ID_MANAGER    => 3,
                              Role::ROLE_ID_SUB_EDITOR => 1
			);
			$level = $levelMap[$userGroup->roleId] ?? 0;
			if (!$level)
				continue;  // irrelevant role, skip stage assignment entry
			elseif ($level == 1)
				$level1N++;
            $user = Repo::user()->get($stageAssignment->userId);
            //RqcDevHelper::writeObjectToConsole($user);
            $assignment['level'] = $level;
			$assignment['email'] = $user->getEmail();
			$assignment['firstname'] = getNonlocalizedAttr($user, "getGivenName");
			$assignment['lastname'] = getNonlocalizedAttr($user, "getFamilyName");
			$assignment['orcid_id'] = getOrcidId($user->getOrcid());
			$editorAssignments[] = $assignment;  // append
		}
		if (!$level1N && count($editorAssignments)) {
			// there must be at least one level-1 editor:
			$editorAssignments[0]['level'] = 1; // TODO 3: maybe understand it better but until then let it be: try different editors for that
		}
        // TODO 1: if there is only a journal/section editor that is not assigned => assign because (s)he doesn't need to assigned to do things
		return array('data' => $editorAssignments, 'truncation_omission_info' => $truncationOmissionInfo);
	}

	/**
	 * Return emailaddress of current user or "" if this is not an interactive call.
	 * The adapter needs to hope this same address is registered with RQC as well.
	 */
	protected static function getInteractiveUser(PKPRequest $request): string
	{
		$user = $request->getUser();
		return $user ? $user->getEmail() : "";
	}

	/**
	 * Return the URL to which RQC should redirect after grading.
	 */
	protected function getMhsSubmissionPage(PKPRequest $request, int $submissionId): string
	{
		$pageRouter = new PageRouter();
		$pageRouter->setApplication(Application::get());  // so that url() will find context
		return $pageRouter->url($request, null, 'workflow', 'index',
			array($submissionId, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW));
	}

	/**
	 * Build a linear array of RQC review descriptor objects.
	 * Would formerly use ReviewerSubmission::getMostRecentPeerReviewComment for the review text.
	 * As of 3.3, there are two cases:
	 * case 1) with "custom" ReviewForm configured e.g.(or only?) by editor (using ReviewFormElement, ReviewFormResponses),
	 * case 2) default review data structure (using SubmissionComment).
	 * See PKPReviewerReviewStep3Form::saveReviewForm() for details.
	 * returns 'data' (the review descriptor objects) and 'truncation_omission_info' (an array of strings: messages which data was truncated or omitted; useful for logging or printing in a popup)
	 */
	protected function getReviewSet(int $submissionId, $reviewRound, int $contextId): array
	{
		$reviews = array(); // the real result
		$truncationOmissionInfo = array(); // if something is left out or truncated or else

        $assignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewRoundIds([$reviewRound->getId()])
            ->filterByStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)
            ->filterByCompleted(true)
            ->getMany();
		foreach ($assignments as $reviewId => $reviewAssignment) { /** @var $reviewAssignment ReviewAssignment */
			if ($reviewAssignment->getRound() != $reviewRound->getRound())
				continue;  // irrelevant record, skip it.
			$rqcReview = array();  // will become one entry in the result set
			//--- review metadata:
			$rqcReview['visible_id'] = $reviewId; // int: no need for limitToSize()
			$rqcReview['invited'] = rqcifyDatetime($reviewAssignment->getDateNotified());
			$rqcReview['agreed'] = rqcifyDatetime($reviewAssignment->getDateConfirmed());
			$rqcReview['expected'] = rqcifyDatetime($reviewAssignment->getDateDue());
			$rqcReview['submitted'] = rqcifyDatetime($reviewAssignment->getDateCompleted());
			//--- review text:
			$reviewFormId = $reviewAssignment->getReviewFormId();
			$reviewText = ($reviewFormId) ?
				$this->getReviewTextFromForm($reviewAssignment) : // case 1
				$this->getReviewTextDefault($reviewAssignment); // case 2
			$rqcReview['text'] = limitToSize($reviewText, RQC_MULTI_LINE_STRING_SIZE_LIMIT);
			$rqcReview['is_html'] = true;
			$attachmentSetWithAdditionalInfo = $this->getAttachmentSet($reviewAssignment);
			$rqcReview['attachment_set'] = limitToSizeArray($attachmentSetWithAdditionalInfo['data']);
			$recommendation = $reviewAssignment->getRecommendation();
			$rqcReview['suggested_decision'] = ($recommendation ? $this->rqcDecision("reviewer", $recommendation) : "");

			//--- reviewer:
			$reviewerObject = Repo::user()->get($reviewAssignment->getReviewerId(), true);
            $submission = Repo::submission()->get($submissionId, $contextId);
			$rqcReviewer = array();
			if (!(new ReviewerOpting())->isOptedOut($submission, $reviewerObject)) { // TODO 1: switch to RQC_OPTING_STATUS_OUT (in 3.3)
				$rqcReviewer['email'] = $reviewerObject->getEmail();
				$rqcReviewer['firstname'] = getNonlocalizedAttr($reviewerObject, "getGivenName");
				$rqcReviewer['lastname'] = getNonlocalizedAttr($reviewerObject, "getFamilyName");
				$rqcReviewer['orcid_id'] = getOrcidId($reviewerObject->getOrcid());

				//----- add truncation && omission information if the truncated data is not the same as the "original" data
				if ($rqcReview['text'] != $reviewText) { // check that here because of the reviewText being cleared with a pseudonym for the reviewer
					$truncationOmissionInfo[] = "The review text of the reviewer " . $reviewerObject->getEmail() . " was truncated. Original size: " .
						strlen($reviewText) . ". Truncated to: " . strlen($rqcReview['text']) . ". The size limit for the review text is: " . RQC_MULTI_LINE_STRING_SIZE_LIMIT;
				}

                if ($rqcReview['attachment_set'] != $attachmentSetWithAdditionalInfo['data']) {
					$truncationOmissionInfo[] = "The review attachments set of the reviewer " . $reviewerObject->getEmail() . " was truncated. Original size: " .
						count($attachmentSetWithAdditionalInfo['data']) . ". Truncated to: " . count($rqcReview['attachment_set']) . ". The size limit of this set is: " . RQC_OTHER_LIST_SIZE_LIMIT;
				}
			} else {
				$rqcReviewer['email'] = generatePseudoEmail($reviewerObject->getEmail(), $this->getSaltAndGenerateIfNotSet($contextId));
				$rqcReviewer['firstname'] = "";
				$rqcReviewer['lastname'] = "";
				$rqcReviewer['orcid_id'] = "";
				$rqcReview['text'] = "";
				$rqcReview['attachment_set'] = array();
			}

			// merge the truncation && omission information of the function the called functions
			$truncationOmissionInfo = array_merge($truncationOmissionInfo, $attachmentSetWithAdditionalInfo['truncation_omission_info']);

			$rqcReview['reviewer'] = $rqcReviewer;
			$reviews[] = $rqcReview;  // append
		}
		return array('data' => $reviews, 'truncation_omission_info' => $truncationOmissionInfo);
	}


	/**
	 * Obtain what is to be considered the text of the review for case 1.
	 * Goes through the review form elements,
	 * works on the REVIEW_FORM_ELEMENT_TYPE_TEXTAREA fields only,
	 * producing a stretch of output text for each, using
	 * 1. the element's name as a heading and
	 * 2. the corresponding ReviewFormResponse's value as body.
	 * @return string in HTML structure as follows
	 * <div>
	 *    <h3>my_header_text</h3>
	 *    <p>Description: <i>my_description_text</i></p>
	 *    <p>Answer: my_reviewer_answer_text</p>
	 * </div>
	 * <div>
	 *    <h3>my_header_text2</h3>
	 *    <p>Description: <i>my_description_text2</i></p>
	 *    <p>Answer: my_reviewer_answer_text2</p>
	 * </div>
	 * ...
	 */
	protected function getReviewTextFromForm(ReviewAssignment $reviewAssignment): string // see Reviewercomments::getReviewFormComments()
	{
        if (!$reviewAssignment->getReviewFormId()) {
            return '';
        }

        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());

        if ($reviewFormElements->wasEmpty()) {
            return '';
        }

		$comments = [];
		while ($reviewFormElement = $reviewFormElements->next()) {
            RqcDevHelper::writeToConsole("### reviewFormElement.elementType=" . $reviewFormElement->getElementType() .
                "  included='" . $reviewFormElement->getIncluded() . "'\n");
            if (!$reviewFormElement->getIncluded()) {
                continue;
            }
            if (in_array($reviewFormElement->getElementType(),
                array(ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
                    ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD,
                    ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA))) {
                continue; // we want only the textual elements
            }
			RqcDevHelper::writeToConsole("### reviewFormElement.elementType=" . $reviewFormElement->getElementType() .
				"  included='" . $reviewFormElement->getIncluded() . "'\n");

            $elementTitle = PKPString::stripUnsafeHtml(getNonlocalizedAttr($reviewFormElement, "getQuestion")); //use englishest to be safe // is in HTML-format (in <p> tags)
            $elementTitle = $elementTitle ? "<h3>" . $elementTitle . "</h3>" : "";
            $elementDescription = PKPString::stripUnsafeHtml(getNonlocalizedAttr($reviewFormElement, "getDescription")); //use englishest to be safe // is in HTML-format (in <p> tags)
            $elementDescription = $elementDescription ? "<p>Description: <i>" . $elementDescription . "</i></p>" : "";

            /** @var ReviewFormResponse|null $reviewFormResponse */
            $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewAssignment->getId(), $reviewFormElement->getId());
            if (!$reviewFormResponse) {
                continue;
            }

            $responseText = htmlspecialchars($this->cleanPlaintextTextarea($reviewFormResponse->getValue())); // encode the special chars (that would be interpreted as html structure in some way)
            $responseText = $responseText ? "<p>Answer: <br>" . $responseText . "</p>" : "";

            if (!preg_match(self::CONFIDENTIAL_FIELD_REGEXP, $elementTitle)) {
                $comments[] = "<div>$elementTitle$elementDescription$responseText</div>";  // format the element in the structure described above
            }
		}
		return join('', $comments);
	}

	/**
	 * Obtain what is to be considered the text of the review for case 2.
	 * @return string in HTML structure as follows:
	 * <div>
	 *     <h3>Text field without a specific question</h3>
	 *     <p>my_reviewer_text</p>
	 * </div>
	 * <div>
	 *     <h3>Text field without a specific question</h3>
	 *     <p>my_reviewer_text2</p>
	 * </div>
	 *  ...
	 */
	protected function getReviewTextDefault(ReviewAssignment $reviewAssignment): string
	{
		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		$viewableOnly = true;  // will automatically skip confidential comment
		$submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId(
			$reviewAssignment->getSubmissionId(),
			$reviewAssignment->getReviewerId(), $reviewAssignment->getId(), $viewableOnly);
		$comments = [];
        /** @var SubmissionComment $submissionComment */
        while ($submissionComment = $submissionComments->next()) {
			if ($submissionComment->getCommentType() != SubmissionComment::COMMENT_TYPE_PEER_REVIEW) {
				continue;  // irrelevant record, skip it
			}
			$title = $submissionComment->getCommentTitle() ?: "Text field without a specific question";  // will be empty but for safety-reasons we let it in there
			$body = $submissionComment->getComments(); // will be in HTML structure already
			$comments[] = "<div><h3>$title</h3>$body</div>";   // format the element in the structure described above
		}
		return str_replace("\r", '', join('', $comments));  // may contain CR LF, we want only LF
	}

	/**
	 * Get first english title if one exists or all titles otherwise.
	 * @param array $allTitles mapping from locale name to title string
	 */
	protected static function getTitle(array $allTitles): string
	{
		return englishest($allTitles, true);
	}

	/**
	 * Get visibleUid or submissionId for given round.
	 * First round is 1;
	 * if round is 0 (for a non-existing predecessor), return "".
	 * We could use $lastReviewRound->getId(), but don't.
	 */
	protected static function getUid($journal, Submission $submission, int $round, bool $forUrl = false): string
	{
		if ($round == 0) {
			return "";
		} else {
			$journalname = $journal->getPath();
			$submissionId = $submission->getId();
			if ($forUrl) {
				// beware: The following _could_ be non-unique and so not in fact a uid (not likely)
				$journalname = preg_replace('/[^a-z0-9-_.:()-]/i', '_', $journalname);
			}
			return sprintf($round == 1 ? "%s-%s" : "%s-%s.R%d",
				$journalname, $submissionId, $round);
		}
	}

	/**
	 * Helper: Translate OJS recommendations and decisions into RQC decisions.
	 * For editors, we use decisions only and return unknown for recommendations.
	 */
	protected static function rqcDecision($role, $ojsDecision)
	{
		$reviewerMap = array(
			// see lib.pkp.classes.submission.reviewAssignment.ReviewAssignment
			// the values are 1,2,3,4,5,6
			0                                                                       => "",
            ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT             => "ACCEPT",
            ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS  => "MINORREVISION",
            ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE      => "MAJORREVISION",
            ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE => "REJECT",
            ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE            => "REJECT",
            ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS       => "MAJORREVISION",  // generic guess!!!
		);
		$editorMap = array(
			// see classes.workflow.EditorDecisionActionsManager
			0                                     => "",
			Decision::RECOMMEND_ACCEPT            => "",
            Decision::RECOMMEND_DECLINE           => "",
            Decision::RECOMMEND_PENDING_REVISIONS => "",
            Decision::RECOMMEND_RESUBMIT          => "",
			Decision::ACCEPT                      => "ACCEPT",
            Decision::SEND_TO_PRODUCTION          => "ACCEPT",
            Decision::INITIAL_DECLINE             => "REJECT",  // probably never relevant
            Decision::DECLINE                     => "REJECT",
            Decision::PENDING_REVISIONS           => "MINORREVISION",
            Decision::RESUBMIT                    => "MAJORREVISION",
            Decision::NEW_EXTERNAL_ROUND          => "MAJORREVISION",
		);
		if ($role == "reviewer")
			return $reviewerMap[$ojsDecision];
		elseif ($role == "editor")
			return $editorMap[$ojsDecision];
		else
			assert(False, "rqcDecision: wrong role " . $role);
	}

	/**
	 * Helper: Remove possible unwanted properties from text coming from textarea fields.
	 */
	protected static function cleanPlaintextTextarea($text): string
	{
		return str_replace("\r", '', $text);  // may contain CR LF, we want only LF
	}

	/**
	 * get the salt for the journal
	 * if the salt was never generated: generate, store and return the new salt
	 */
	protected function getSaltAndGenerateIfNotSet(int $contextId): string
	{
		$saltLength = 32;
		$salt = $this->plugin->getSetting($contextId, 'rqcJournalSalt');
		if (!$salt) {
			try {
				if (function_exists('random_bytes')) {
					$bytes = random_bytes(floor($saltLength / 2));
				} else {
					$bytes = openssl_random_pseudo_bytes(floor($saltLength / 2));
				}
				$salt = bin2hex($bytes); // the string is likely unprintable => to hex so the string is printable (but twice as long)
			} catch (RandomException $e) {
				$salt = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $saltLength); // https://stackoverflow.com/questions/4356289/php-random-string-generator
			}
			$this->plugin->updateSetting($contextId, 'rqcJournalSalt', $salt, 'string');
			RqcLogger::logInfo("A new value for the rqcJournalSalt was set as a plugin_setting for the journal $contextId");
		}
		return $salt;
	}

    /**
     * Helper: Discriminate decisions from recommendations.
     */
    public static function isDecision($ojsDecision): bool
    {
        return match ($ojsDecision) {
            Decision::ACCEPT,
            Decision::DECLINE,
            Decision::INITIAL_DECLINE,
            Decision::PENDING_REVISIONS,
            Decision::RESUBMIT => true,
            default => false, // everything else isn't
        };
    }
}

/**
 * Helper: Get first english entry if one exists or else:
 * all entries in one string if $elseAll or
 * the entry of the alphabetically first locale otherwise.
 * @param array $allEntries mapping from locale name to string
 */
function englishest(array $allEntries, $elseAll = false)
{
	$allNonenglishLocales = array();
	foreach ($allEntries as $locale => $entry) {
		if (substr($locale, 0, 2) === "en") {
			return $entry;  // ...and we're done!
		}
		$allNonenglishLocales[] = $locale;
	}
	// no en locale found. Return first-of or all others, sorted by locale:
	sort($allNonenglishLocales);
	$allNonenglishEntries = array();
	foreach ($allNonenglishLocales as $locale) {
		$allNonenglishEntries[] = $allEntries[$locale];
	}
	if ($elseAll) {
		return implode(" / ", $allNonenglishEntries);
	} else {
		return $allNonenglishEntries[0];
	}
}

/**
 * Helper: Obtain an attribute value in the most sensible localized version we can identify:
 * RqcPlugin::RQC_LOCALE if possible, the englishest available one otherwise.
 */
function getNonlocalizedAttr($obj, $functionname): string
{
	$result = $obj->$functionname(RqcPlugin::RQC_LOCALE);  // get preferred value
	if (!$result) {  // get random first value from full localized list of attribute values
		$allLocalizedAttrs = $obj->$functionname(null);
		// $result = $allLocalizedAttrs[array_key_first($allLocalizedAttrs)];
		$result = englishest($allLocalizedAttrs);
	}
	if (!$result)  // avoid null results
		$result = "";
	return $result;
}


/**
 * Helper: Obtain the ORCID ID number from an ORCID URL as stored in OJS.
 */
function getOrcidId($orcidUrl): string
{
	$pattern = '/(https?:\/\/\w+\.\w+\/)?(\d+-\d+-\d+-\d+)/';
	$success = preg_match($pattern, $orcidUrl, $match);
	return $success ? $match[2] : "";
}


/**
 * Helper: Transform timestamp format to RQC convention.
 */
function rqcifyDatetime($ojsDatetime): string
{
	if (!$ojsDatetime) {
		return "";
	}
	$result = str_replace(" ", "T", $ojsDatetime);
	return $result . "Z";
}


/**
 * Helper: generate the pseudo email address as described in the rqc API:
 * It is not important whether you use SHA-1 or some other hash function;
 * it is not important what your salt is or that it be kept super-secret.
 * What is important is this
 * - that the same emailaddress always maps to the same pseudo-address,
 * - that all pseudo-addresses be different, and
 * - that the pseudo-address ends with @example.edu, so that RQC can recognize it is a pseudo-address.
 */
function generatePseudoEmail($reviewerEmail, $salt): string
{
	$hash = sha1($reviewerEmail . $salt, false); // hex
	return $hash . "@example.edu";
}

/**
 * Helper: truncate the string to maxLength-1 and add "…" to the end
 */
function limitToSize(string $string, int $maxLength = RQC_ONE_LINE_STRING_SIZE_LIMIT): string
{
	//RqcDevHelper::writeToConsole("maxlength: $maxLength. Length of string: ". strlen($string) . "length of \u{2026}" . strlen("\u{2026}") . "\n");
	return mb_strimwidth($string, 0, $maxLength - 2, "\u{2026}"); // strlen("\u{2026}") == 3
}

/**
 * Helper: truncate the array to the maxLength
 */
function limitToSizeArray(array $array, int $maxLength = RQC_OTHER_LIST_SIZE_LIMIT): array
{
	return array_slice($array, 0, $maxLength, true);
}
