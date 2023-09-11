<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/RqcData.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcData
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Compute the JSON-like contents of a call to the RQC API.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\reviewqualitycollector;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\site\VersionCheck;
*/
import('classes.workflow.EditorDecisionActionsManager');  // decision action constants
import('lib.pkp.classes.core.PKPPageRouter');
import('lib.pkp.classes.site.VersionCheck');
import('plugins.generic.reviewqualitycollector.classes.RqcDevHelper');

/**
 * Class RqcData.
 * Builds the data object to be sent to the RQC server from the various pieces of the OJS data model:
 * submission, authors, editors, reviewers and reviews, active user, decision, etc.
 */
class RqcData extends RqcDevHelper
{

	const CONFIDENTIAL_FIELD_REGEXP = '/[Cc]onfidential/';  // review form fields with such names are excluded

	function __construct() {
		// $this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		parent::__construct();
	}

	/**
	 * Build PHP array with the data for an RQC call to be made.
	 * if $request is null, interactive_user and mhs_submissionpage are transmitted as "".
	 */
	function rqcdata_array($request, $contextId, $submissionId): array
	{
		$contextDao = Application::getContextDAO();
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		//----- prepare processing:
		$journal = $contextDao->getById($contextId);
		$submission = $submissionDao->getById($submissionId);
		$data = array();
		//----- fundamentals:
		$data['interactive_user'] = $request ? $this->get_interactive_user($request) : "";
		$data['mhs_submissionpage'] = $request ? $this->get_mhs_submissionpage($request, $submissionId) : "";


		//----- submission data:
		$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId);
		$reviewroundN = $lastReviewRound->getRound();
		$data['visible_uid'] = $this->get_uid($journal, $submission, $reviewroundN);  // user-facing pseudo ID
		$data['external_uid'] = $this->get_uid($journal, $submission, $reviewroundN, true);  // URL-friendly version.
        $data['title'] = $this->get_title($submission->getTitle(null));
        $alldata = $submission->getAllData();
		$data['submitted'] = rqcify_datetime($alldata['dateSubmitted']);
		// assume that round $reviewroundN-1 exists (but it may not!!!):

		//----- authors, editor assignments, reviews, decision:
        $data['author_set'] = $this->get_author_set($submission->getAuthors());
        $data['edassgmt_set'] = $this->get_editorassignment_set($submissionId);
        $data['review_set'] = $this->get_review_set($submissionId, $lastReviewRound);
        $data['decision'] = $this->get_decision($lastReviewRound);

		return $data;
	}

	/**
	 * Return linear array of RQC-ish attachment objects.
	 */
	protected static function get_attachment_set($reviewerSubmission): array
	{
		$reviewFilesDao = DAORegistry::getDAO("ReviewFilesDAO");
		$submissionFileDao = DAORegistry::getDAO("SubmissionFileDAO");
		$result = array();  // TODO 1: get attachments
		/* getting attachments is not for the faint-of-heart:
		   $reviewerSubmission.reviewer_file_id points to
		   table review_files with attr submission_file_id which points to
		   table submission_file with attr file_id which points to
		   table file with attr path in dir files_dir from config.inc.php.
		   In case you want a filename (the path in file is a hash value),
		   just look in submission_file_settings, which are I18Ned.
		   However, ReviewFilesDAO has no retrieval op?
		*/
		return $result;
	}

	/**
	 * Return linear array of RQC-ish author objects.
	 */
	protected static function get_author_set($authorsobjects): array
	{
		$result = array();
		foreach ($authorsobjects as $authorobject) {
			if (!(bool)$authorobject->getIncludeInBrowse())  // TODO 2: correct like this?
				continue;  // skip non-corresponding authors
			$rqcauthor = array();
			$rqcauthor['email'] = $authorobject->getEmail();
			$rqcauthor['firstname'] = get_nonlocalized_attr($authorobject, "getGivenName");
			$rqcauthor['lastname'] = get_nonlocalized_attr($authorobject, "getFamilyName");
			$rqcauthor['order_number'] = (int)($authorobject->getSequence());
			$rqcauthor['orcid_id'] = get_orcid_id($authorobject->getOrcid());
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
    protected function get_decision($reviewRound): string
	{
        // See EditDecisionDAO->getEditorDecisions, $this->rqc_decision
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
		$rr = $reviewRound;
        $editorDecisions = $editDecisionDao->getEditorDecisions(
            $rr->getSubmissionId(), $rr->getStageId(), $rr->getRound());
        foreach ($editorDecisions as $decision) {
            $result = $this->rqc_decision("editor", $decision['decision']);
            if ($result) {  // use the first non-undefined decision
                return $result;
            }
        }
        return "";  // only recommendations found, no decisions
    }

	/**
	 * Return linear array of RQC editorship descriptor objects.
	 */
	protected function get_editorassignment_set($submissionId): array
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
			$assignment['firstname'] = get_nonlocalized_attr($user, "getGivenName");
			$assignment['lastname'] = get_nonlocalized_attr($user, "getFamilyName");
            $assignment['orcid_id'] = get_orcid_id($user->getOrcid());
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
	protected static function get_interactive_user($request)
	{
		$user = $request->getUser();
		return $user ? $user->getEmail() : "";
	}

	/**
	 * Return the URL to which RQC should redirect after grading.
	 */
	protected function get_mhs_submissionpage(PKPRequest $request, int $submissionId)
	{
		return $request->url(null, 'workflow', 'index',
			array($submissionId, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW));
	}

	/**
	 * Return linear array of RQC review descriptor objects.
	 * Would formerly use ReviewerSubmission::getMostRecentPeerReviewComment for the review text.
	 * As of 3.3, there are two cases:
	 * case 1) with configured ReviewForm (using ReviewFormElement, ReviewFormResponses),
	 * case 2) default review data structure (using SubmissionComment).
	 * See PKPReviewerReviewStep3Form::saveReviewForm() for details.
	 */
	protected function get_review_set($submissionId, $reviewRound): array
	{
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = array();
		$reviewRoundN = $reviewRound->getRound();
		$assignments = $reviewAssignmentDao->getBySubmissionId($submissionId, $reviewRoundN-1);
		foreach ($assignments as $reviewId => $reviewAssignment) {
			if ($reviewAssignment->getRound() != $reviewRoundN ||
				$reviewAssignment->getStageId() != WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)
				continue;  // irrelevant record, skip it.
			$rqcreview = array();  // will become one entry in the result set
			$reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewId);
			//--- review metadata:
			$rqcreview['visible_id'] = $reviewId;
			$rqcreview['invited'] = rqcify_datetime($reviewAssignment->getDateNotified());
			$rqcreview['agreed'] = rqcify_datetime($reviewAssignment->getDateConfirmed());
			$rqcreview['expected'] = rqcify_datetime($reviewAssignment->getDateDue());
			$rqcreview['submitted'] = rqcify_datetime($reviewAssignment->getDateCompleted());
			//--- review text:
			$reviewFormId = $reviewAssignment->getReviewFormId();
			if ($reviewFormId) {  // case 1
				$reviewtext = $this->getReviewTextFromForm($reviewerSubmission, $reviewFormId);
				$is_html = false;  // TODO 2: is there really no way to get HTML here?
			} else {  // case 2
				$reviewtext = $this->getReviewTextDefault($reviewAssignment);
				$is_html = true;
			}
			$rqcreview['text'] = $reviewtext;
			$rqcreview['is_html'] = $is_html;
			$rqcreview['attachment_set'] = $this->get_attachment_set($reviewerSubmission);
			$recommendation = $reviewAssignment->getRecommendation();
            $rqcreview['suggested_decision'] = $recommendation ? $this->rqc_decision("reviewer", $recommendation) : "";
			//--- reviewer:
			$reviewerobject = $userDao->getById($reviewAssignment->getReviewerId());
			$rqcreviewer = array();
			$rqcreviewer['email'] = $reviewerobject->getEmail();
			$rqcreviewer['firstname'] = get_nonlocalized_attr($reviewerobject, "getGivenName");
			$rqcreviewer['lastname'] = get_nonlocalized_attr($reviewerobject, "getFamilyName");
			$rqcreviewer['orcid_id'] = get_orcid_id($reviewerobject->getOrcid());
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
	 * @param array $all_titles  mapping from locale name to title string
	 */
	protected static function get_title(array $all_titles): string
	{
		return englishest($all_titles, true);
	}

	/**
	 * Get visible_uid or submission_id for given round.
	 * First round is 1;
	 * if round is 0 (for a non-existing predecessor), return "".
	 * We could use $lastReviewRound->getId(), but don't.
	 */
     protected static function get_uid($journal, $submission, $round, $for_url=false): string
	 {
		if ($round == 0) {
			return "";
		} else {
			$journalname = $journal->getPath();
			$submission_id = $submission->getId();
			if ($for_url) {
				// TODO 3: beware: The following _could_ be non-unique and so not in fact a uid
				$journalname = preg_replace('/[^a-z0-9-_.:()-]/i', '_', $journalname);
			}
			return sprintf($round == 1 ? "%s-%s" : "%s-%s.R%d",
				$journalname, $submission_id, $round);
		}
	}

    /**
     * Helper: Translate OJS recommendations and decisions into RQC decisions.
     * For editors, we use decisions only and return unknown for recommendations.
     */
    protected static function rqc_decision($role, $ojs_decision)
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
            return $reviewerMap[$ojs_decision];
        elseif ($role == "editor")
            return $editorMap[$ojs_decision];
        else
            assert(False, "rqc_decision: wrong role " . $role);
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
	public static function is_decision($ojs_decision): bool
	{
		switch ($ojs_decision) {
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
 * all entries in one string if $else_all or
 * the entry of the alphabetically first locale otherwise.
 * @param array $all_entries  mapping from locale name to string
 */
function englishest(array $all_entries, $else_all=false)
{
	$all_nonenglish_locales = array();
	foreach ($all_entries as $locale => $entry) {
		if (substr($locale, 0, 2) === "en") {
			return $entry;  // ...and we're done!
		}
		$all_nonenglish_locales[] = $locale;
	}
	// no en locale found. Return first-of or all others, sorted by locale:
	sort($all_nonenglish_locales);
	$all_nonenglish_entries = array();
	foreach ($all_nonenglish_locales as $locale) {
		$all_nonenglish_entries[] = $all_entries[$locale];
	}
	if ($else_all) {
		return implode(" / ", $all_nonenglish_entries);
	} else {
		return $all_nonenglish_entries[0];
	}
}

/**
 * Helper: Obtain an attribute value in the most sensible localized version we can identify:
 * RQC_LOCALE if possible, the englishest available one otherwise.
 */
function get_nonlocalized_attr($obj, $functionname): string
{
	$result = $obj->$functionname(RQC_LOCALE);  // get preferred value
	if (!$result) {  // get random first value from full localized list of attribute values
		$all_localized_attrs = $obj->$functionname(null);
		// $result = $all_localized_attrs[array_key_first($all_localized_attrs)];
		$result = englishest($all_localized_attrs);
	}
	if (!$result)  // avoid null results
		$result = "";
	return $result;
}


/**
 * Helper: Obtain the ORCID ID number from an ORCID URL as stored in OJS.
 */
function get_orcid_id($orcid_url): string
{
	$pattern = '/(https?:\/\/\w+\.\w+\/)?(\d+-\d+-\d+-\d+)/';
	$success = preg_match($pattern, $orcid_url, $match);
	return $success ? $match[2] : "";
}



/**
 * Helper: Transform timestamp format to RQC convention.
 */
function rqcify_datetime($ojs_datetime): string
{
	if (!$ojs_datetime) {
		return "";
	}
	$result = str_replace(" ", "T", $ojs_datetime);
	return $result . "Z";
}
