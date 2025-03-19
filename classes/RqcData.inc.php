<?php

/**
 * @file plugins/generic/rqc/classes/RqcData.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcData
 * @ingroup plugins_generic_rqc
 *
 * @brief Compute the JSON-like contents of a call to the RQC API.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\site\VersionCheck;
*/
import('classes.workflow.EditorDecisionActionsManager');  // decision action constants
import('lib.pkp.classes.core.PKPPageRouter');
import('lib.pkp.classes.site.VersionCheck');
import('plugins.generic.rqc.classes.RqcDevHelper');

define("RQC_AllOWED_FILE_EXTENSIONS", array(
	"pdf", "docx", "xlsx", "pptx", "odt", "ods", "odp", "odg", "txt"
)); // which files are allowed to be included in the review_set['attachment_set']

/**
 * Class RqcData.
 * Builds the data object to be sent to the RQC server from the various pieces of the OJS data model:
 * submission, authors, editors, reviewers and reviews, active user, decision, etc.
 */
class RqcData
{
	use RqcDevHelper;

	const CONFIDENTIAL_FIELD_REGEXP = '/[Cc]onfidential/';  // review form fields with such names are excluded

	/**
	 * Build PHP array with the data for an RQC call to be made.
	 * if $request is null, interactive_user and mhs_submissionpage are transmitted as "".
	 */
	function rqcdataArray($request, $contextId, $submissionId): array
	{
		$contextDao = Application::getContextDAO();
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		//----- prepare processing:
		$journal = $contextDao->getById($contextId);
		$submission = $submissionDao->getById($submissionId);
		$data = array();
		//----- fundamentals:
		$data['interactive_user'] = $request ? $this->getInteractiveUser($request) : "";
		$data['mhs_submissionpage'] = $request ? $this->getMhsSubmissionpage($request, $submissionId) : "";


		//----- submission data:
		$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId);
		$reviewroundN = $lastReviewRound->getRound();
		$data['visible_uid'] = $this->getUid($journal, $submission, $reviewroundN);  // user-facing pseudo ID
		$data['external_uid'] = $this->getUid($journal, $submission, $reviewroundN, true);  // URL-friendly version.
        $data['title'] = $this->getTitle($submission->getTitle(null));
        $alldata = $submission->getAllData();
		$data['submitted'] = rqcifyDatetime($alldata['dateSubmitted']);

		//----- authors, editor assignments, reviews, decision:
        $data['author_set'] = $this->getAuthorSet($submission->getAuthors()); // TODO 3: but deprecated function. But no clue what to put in instead
        $data['edassgmt_set'] = $this->getEditorassignmentSet($submissionId);
        $data['review_set'] = $this->getReviewSet($submissionId, $lastReviewRound, $contextId);
        $data['decision'] = $this->getDecision($lastReviewRound);

		return $data;
	}

	/**
	 * Return linear array of RQC-ish attachment objects.
	 */
	protected static function getAttachmentSet($reviewerSubmission): array
	{
		$attachmentSet = array();
		//RqcDevHelperStatic::_staticPrint("\nReviewer: ".$reviewerSubmission->getReviewerFullName()." with Id: ".$reviewerSubmission->getReviewerId()."\n");

		$submissionFilesIterator = Services::get('submissionFile')->getMany(
			[	'submissionIds' => [$reviewerSubmission->getId()],
				'uploaderUserIds' =>  [$reviewerSubmission->getReviewerId()],
			]);
		foreach ($submissionFilesIterator as $submissionFile) {
			$attachment = array();
			$submissionFileName = englishest($submissionFile->getData('name'), false);

			$ext = pathinfo($submissionFileName, PATHINFO_EXTENSION);
			if (!in_array($ext, RQC_AllOWED_FILE_EXTENSIONS) ) {
				continue;
			}
			$fileId = $submissionFile->getData('fileId'); // !! not the submissionFileId
			//RqcDevHelperStatic::_staticPrint("SubmissionFile: ".$submissionFileName." with FileId: ".$fileId."\n");
			$fileService = Services::get('file');
			$file = $fileService->get($fileId);
			$fileContent = $fileService->fs->read($file->path);
			//RqcDevHelperStatic::_staticPrint("File: ".$file->id." ".$file->path." with mimeType: ".$file->mimetype."\nContent: ##BeginOfFile##\n".$fileContent."##EndOfFile##\n\n");

			$attachment['filename'] = $submissionFileName;
			$attachment['data'] = base64_encode($fileContent); // TODO 2: base64_encoded content gives me an error 500 from the server
			$attachmentSet[] = $attachment;
		}
		//RqcDevHelperStatic::_staticPrint("\n".print_r($attachmentSet, true)."\n");
		return $attachmentSet;
	}

	/**
	 * Return linear array of RQC-ish author objects.
	 */
	protected static function getAuthorSet($authorsobjects): array
	{
		$result = array();
		foreach ($authorsobjects as $authorobject) {
			// TODO 3 if issue closed: https://github.com/pkp/pkp-lib/issues/6178
			if (false) //!(bool)$authorobject->isCorrespondingAuthor()) is currently not available (primaryAuthor or includeInBrowse don't suffice/fulfill that role)
				continue;  // skip non-corresponding authors
			$rqcauthor = array();
			$rqcauthor['email'] = $authorobject->getEmail();
			$rqcauthor['firstname'] = getNonlocalizedAttr($authorobject, "getGivenName");
			$rqcauthor['lastname'] = getNonlocalizedAttr($authorobject, "getFamilyName");
			//RqcDevHelperStatic::_staticPrint("\n".print_r($authorobject, true)."\n");
			$rqcauthor['order_number'] = (int)($authorobject->getSequence()); // TODO 2: doesn't work like it should (it's always 0 even if it isn't in the db)
			$rqcauthor['orcid_id'] = getOrcidId($authorobject->getOrcid());
			$result[] = $rqcauthor;
		}
		return $result;
	}

    /**
     * Return RQC-style decision string.
     * The data is found in EditDecision objects. There can be multiple ones, from different editors.
     * Some describe recommendations, others decisions.
     * We use decisions only and return an 'unknown' if there are only recommendations.
     * We simply use the first decision we see.
     */
    protected function getDecision($reviewRound): string
	{
        // See EditDecisionDAO->getEditorDecisions, $this->rqcDecision
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
		$rr = $reviewRound;
        $editorDecisions = $editDecisionDao->getEditorDecisions(
            $rr->getSubmissionId(), $rr->getStageId(), $rr->getRound());
        foreach ($editorDecisions as $decision) {
            $result = $this->rqcDecision("editor", $decision['decision']);
            if ($result) {  // use the first non-undefined decision TODO 3: understand it better (maybe its generally better to use the last decision?)
                return $result;
            }
        }
        return "";  // only recommendations found, no decisions
    }

	/**
	 * Return linear array of RQC editorship descriptor objects.
	 */
	protected function getEditorassignmentSet($submissionId): array
	{
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$result = array();
		$iter = $stageAssignmentDao->getBySubmissionAndStageId($submissionId,
			WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
		$level1N = 0;
		foreach ($iter->toArray() as $stageassign) {
			$assignment = array();
			$user = $userDao->getById($stageassign->getUserId());
			$userGroup = $userGroupDao->getById($stageassign->getUserGroupId());
			$role = $userGroup->getRoleId();
			$levelMap = array(ROLE_ID_MANAGER => 3,  // OJS 3.4: use prefix Role:: to find the constants
                ROLE_ID_SUB_EDITOR => 1);
			$level = $levelMap[$role] ?? 0;
			if (!$level)
				continue;  // irrelevant role, skip stage assignment entry
			elseif ($level == 1)
				$level1N++;
            $assignment['level'] = $level;
            $assignment['email'] = $user->getEmail();
			$assignment['firstname'] = getNonlocalizedAttr($user, "getGivenName");
			$assignment['lastname'] = getNonlocalizedAttr($user, "getFamilyName");
            $assignment['orcid_id'] = getOrcidId($user->getOrcid());
            $result[] = $assignment;  // append
		}
		if (!$level1N && count($result)) {
			// there must be at least one level-1 editor:
			$result[0]['level'] = 1;
		}
		return $result;
	}

	/**
	 * Return emailaddress of current user or "" if this is not an interactive call.
	 * The adapter needs to hope this same address is registered with RQC as well.
	 */
	protected static function getInteractiveUser($request)
	{
		$user = $request->getUser();
		return $user ? $user->getEmail() : "";
	}

	/**
	 * Return the URL to which RQC should redirect after grading.
	 */
	protected function getMhsSubmissionpage(PKPRequest $request, int $submissionId)
	{
		return $request->url(null, 'workflow', 'index',
			array($submissionId, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)); // TODO 3: deprecated
	}

	/**
	 * Return linear array of RQC review descriptor objects.
	 * Would formerly use ReviewerSubmission::getMostRecentPeerReviewComment for the review text.
	 * As of 3.3, there are two cases:
	 * case 1) with configured ReviewForm (using ReviewFormElement, ReviewFormResponses),
	 * case 2) default review data structure (using SubmissionComment).
	 * See PKPReviewerReviewStep3Form::saveReviewForm() for details.
	 */
	protected function getReviewSet($submissionId, $reviewRound, $contextId): array
	{
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = array();
		$assignments = $reviewAssignmentDao->getBySubmissionId($submissionId, $reviewRound->getId());
		foreach ($assignments as $reviewId => $reviewAssignment) {
			// TODO 2: What if a reviewer has not submitted his review but some data is already there (something like save for later)? Questions @Prechelt
			// => what data is min needed?
			if ($reviewAssignment->getRound() != $reviewRound->getRound() ||
				$reviewAssignment->getStageId() != WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)
				continue;  // irrelevant record, skip it.
			$rqcreview = array();  // will become one entry in the result set
			$reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewId);
			//--- review metadata:
			$rqcreview['visible_id'] = $reviewId;
			$rqcreview['invited'] = rqcifyDatetime($reviewAssignment->getDateNotified());
			$rqcreview['agreed'] = rqcifyDatetime($reviewAssignment->getDateConfirmed());
			$rqcreview['expected'] = rqcifyDatetime($reviewAssignment->getDateDue());
			$rqcreview['submitted'] = rqcifyDatetime($reviewAssignment->getDateCompleted());
			//--- review text:
			$reviewFormId = $reviewAssignment->getReviewFormId();
			if ($reviewFormId) {  // case 1
				$reviewtext = $this->getReviewTextFromForm($reviewerSubmission, $reviewFormId);
				$isHtml = false;  // TODO 2: is there really no way to get HTML here?
			} else {  // case 2
				$reviewtext = $this->getReviewTextDefault($reviewAssignment);
				$isHtml = true;
			}
			$rqcreview['text'] = $reviewtext;
			$rqcreview['is_html'] = $isHtml;
			$rqcreview['attachment_set'] = array(); //  $this->getAttachmentSet($reviewerSubmission); // TODO 2: base64_encoded content gives me an error 500 from the server (til then I leave it commented out)
			$recommendation = $reviewAssignment->getRecommendation();
            $rqcreview['suggested_decision'] = $recommendation ? $this->rqcDecision("reviewer", $recommendation) : "";

			//--- reviewer:
			$reviewerobject = $userDao->getById($reviewAssignment->getReviewerId());
			// rqcOptOut
			$status = (new ReviewerOpting())->getStatus($contextId, $reviewerobject, !RQC_PRELIM_OPTING);
			$rqcreviewer = array();
			if ($status == RQC_OPTING_STATUS_IN) {
				$rqcreviewer['email'] = $reviewerobject->getEmail();
				$rqcreviewer['firstname'] = getNonlocalizedAttr($reviewerobject, "getGivenName");
				$rqcreviewer['lastname'] = getNonlocalizedAttr($reviewerobject, "getFamilyName");
				$rqcreviewer['orcid_id'] = getOrcidId($reviewerobject->getOrcid());
			} else {
				$rqcreviewer['email'] = generatePseudoEmail($contextId, $reviewerobject->getEmail());
				$rqcreviewer['firstname'] = "";
				$rqcreviewer['lastname'] = "";
				$rqcreviewer['orcid_id'] = "";
				$rqcreview['text'] = "";
			}
			$rqcreview['reviewer'] = $rqcreviewer;
			$result[] = $rqcreview;  // append
		}
		return $result;
	}


	/**
	 * Obtain what is to be considered the text of the review for case 1.
	 * Goes through the review form elements,
	 * works on the REVIEW_FORM_ELEMENT_TYPE_TEXTAREA fields only,
	 * producing a stretch of output text for each, using
	 * 1. the element's name as a heading and
	 * 2. the corresponding ReviewFormResponse's value as body.
	 */
	protected function getReviewTextFromForm(ReviewerSubmission $reviewerSubmission, int $reviewFormId): string
	{
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
		$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
		$reviewId = $reviewerSubmission->getReviewId();
		$reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId);
		$result = "";
		while ($reviewFormElement = $reviewFormElements->next()) {
			$this->_print("### reviewFormElement.elementType=" . $reviewFormElement->getElementType() .
				"  included='". $reviewFormElement->getIncluded() . "'\n");
			if ($reviewFormElement->getElementType() == REVIEW_FORM_ELEMENT_TYPE_TEXTAREA &&
					$reviewFormElement->getIncluded()) {
				$reviewFormElementId = $reviewFormElement->getId();
				$elementTitle = $reviewFormElement->getQuestion('en_US');  // may have HTML tags!
				$elementTitle = str_replace('<p>', '', $elementTitle);
				$elementTitle = str_replace('</p>', '', $elementTitle);
				$responseElement = $reviewFormResponseDao->getReviewFormResponse($reviewId, $reviewFormElementId);
				$responseText = $this->cleanPlaintextTextarea($responseElement->getValue());
				if (!preg_match(self::CONFIDENTIAL_FIELD_REGEXP, $elementTitle)) {
					$result .= "\n### $elementTitle\n\n$responseText\n\n";  // plain-text-format this element
				}
			}
		}
		return $result;
	}

	/**
	 * Obtain what is to be considered the text of the review for case 2.
	 */
	protected function getReviewTextDefault(ReviewAssignment $reviewAssignment): string
	{
		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		/* @var $submissionCommentDao SubmissionCommentDAO */
		$viewableOnly = true;  // will automatically skip confidential comment
		$submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId(
			$reviewAssignment->getSubmissionId(),
			$reviewAssignment->getReviewerId(), $reviewAssignment->getId(), $viewableOnly);
		$result = "";
		while($submissionComment = $submissionComments->next()) {
			if ($submissionComment->getCommentType() != COMMENT_TYPE_PEER_REVIEW) {
				continue;  // irrelevant record, skip it
			}
			$title = $submissionComment->getCommentTitle();  // will be empty
			$body = $submissionComment->getComments();
			$result .= ($title ? "\n<div>$title</div>\n\n$body\n\n" : "\n$body\n");

		}
		return str_replace("\r", '', $result);  // may contain CR LF, we want only LF
	}

	/**
	 * Get first english title if one exists or all titles otherwise.
	 * @param array $allTitles  mapping from locale name to title string
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
     protected static function getUid($journal, $submission, $round, $forUrl=false): string
	 {
		if ($round == 0) {
			return "";
		} else {
			$journalname = $journal->getPath();
			$submissionId = $submission->getId();
			if ($forUrl) {
				// TODO 3: beware: The following _could_ be non-unique and so not in fact a uid
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
            0 => "",
            SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT => "ACCEPT",
            SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS => "MINORREVISION",
            SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE => "MAJORREVISION",
            SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE => "REJECT",
            SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE => "REJECT",
            SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS => "MAJORREVISION",  // generic guess!!!
        );
        $editorMap = array(
            // see classes.workflow.EditorDecisionActionsManager
            0 => "",
            SUBMISSION_EDITOR_RECOMMEND_ACCEPT => "",
            SUBMISSION_EDITOR_RECOMMEND_DECLINE => "",
            SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => "",
            SUBMISSION_EDITOR_RECOMMEND_RESUBMIT => "",
            SUBMISSION_EDITOR_DECISION_ACCEPT => "ACCEPT",
            SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => "ACCEPT",
            SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE => "REJECT",  // probably never relevant
            SUBMISSION_EDITOR_DECISION_DECLINE => "REJECT",
            SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS => "MINORREVISION",
            SUBMISSION_EDITOR_DECISION_RESUBMIT => "MAJORREVISION",
            SUBMISSION_EDITOR_DECISION_NEW_ROUND => "MAJORREVISION",
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
}


class RqcOjsData {
	/**
	 * Helper: Discriminate decisions from recommendations.
	 */
	public static function isDecision($ojsDecision): bool
	{
		switch ($ojsDecision) {
			case SUBMISSION_EDITOR_DECISION_ACCEPT:
			case SUBMISSION_EDITOR_DECISION_DECLINE:
			case SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE:
			case SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS:
			case SUBMISSION_EDITOR_DECISION_RESUBMIT:
				return true;
		}
		return false;  // everything else isn't
	}
}

/**
 * Helper: Get first english entry if one exists or else:
 * all entries in one string if $elseAll or
 * the entry of the alphabetically first locale otherwise.
 * @param array $allEntries  mapping from locale name to string
 */
function englishest(array $allEntries, $elseAll=false)
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
 * RQC_LOCALE if possible, the englishest available one otherwise.
 */
function getNonlocalizedAttr($obj, $functionname): string
{
	$result = $obj->$functionname(RQC_LOCALE);  // get preferred value
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
function generatePseudoEmail($contextId, $reviewerEmail): string
{
	$salt = sha1($contextId);
	// we need a value that is different for each MHS (but reproducible),
	// and doesn't have to be secret (but is not shipped with in the RQC-call; it shouldn't be that easily accessible)
	// TODO 3: Question @Prechelt: Ok like that?
	$hash = sha1($reviewerEmail . $salt);
	return $hash . "@example.edu";
}
