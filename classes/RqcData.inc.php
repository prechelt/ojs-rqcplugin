<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/RqcData.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RqcData
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Compute the JSON-like contents of a call to the RQC API.
 */

import('classes.workflow.EditorDecisionActionsManager');  // decision action constants

/**
 * Class RqcData.
 * Builds the data object to be sent to the RQC server from the various pieces of the OJS data model:
 * submission, authors, editors, reviewers and reviews, active user, decision, etc.
 */
class RqcData {

	function __construct() {
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		//--- store DAOs:
		$this->journalDao = DAORegistry::getDAO('JournalDAO');
		$this->articleDao = DAORegistry::getDAO('ArticleDAO');
		$this->reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$this->reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$this->reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
		$this->stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$this->userDao = DAORegistry::getDAO('UserDAO');
		$this->userGroupDao = DAORegistry::getDAO('UserGroupDAO');
	}

	/**
	 * Show RQC request corresponding to a given submissionId=n arg.
	 */
	function rqcdata_array($user, $journal, $submissionId) {
		//----- prepare processing:
		$submission = $this->articleDao->getById($submissionId);
		$sectionname = $submission->getSectionAbbrev();
		if (!$sectionname)
			$sectionname = $submission->getSectionTitle();
		$data = array();

		//----- fundamentals:
		$data['submissionId'] = $submissionId;
		$data['api_version'] = '1.0alpha';
		$data['interactive_user'] = $this->get_interactive_user($user);

		//----- submission data:
		$data['title'] = $this->get_title($submission->getTitle(null));
		$lastReviewRound = $this->reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId);
		$reviewroundN = $lastReviewRound->getRound();
		$data['visible_uid'] = $this->get_uid($journal, $submission, $reviewroundN);
		$alldata = $submission->getAllData();
		$data['submitted'] = $this->rqcify_datetime($alldata['dateSubmitted']);
		// assume that round $reviewroundN-1 exists (but it may not!!!):
		$data['predecessor_submission_id'] = $this->get_uid($journal, $submission,
			$reviewroundN-1, true);
		$data['predecessor_visible_uid'] = $this->get_uid($journal, $submission,
			$reviewroundN-1, false);

		//----- authors, editor assignments, reviews, decision:
		$data['author_set'] = $this->get_author_set($submission->getAuthors());
		$data['editorassignment_set'] = $this->get_editorassignment_set($submissionId);
		$data['review_set'] = $this->get_review_set($submissionId, $lastReviewRound);
		$data['decision'] = $this->get_decision();

		return $data;
	}

	/**
	 * Return linear array of RQC-ish author objects.
	 */
	protected static function get_author_set($authorsobjects) {
		$result = array();
		foreach ($authorsobjects as $authorobject) {
			$rqcauthor = array();
			$rqcauthor['email'] = $authorobject->getEmail();
			$rqcauthor['firstname'] = $authorobject->getGivenName(RQC_LOCALE);
			$rqcauthor['lastname'] = $authorobject->getFamilyName(RQC_LOCALE);
			$rqcauthor['is_corresponding'] = true;  // TODO
			$rqcauthor['order_number'] = (int)($authorobject->getSequence());
			$result[] = $rqcauthor;
		}
		return $result;
	}

	/**
	 * Return RQC-style decision string.
	 */
	protected function get_decision() {
		// See EditDecisionDAO->getEditorDecisions
		return array("TODO: get_decision");
	}

	/**
	 * Return linear array of RQC editorship descriptor objects.
	 */
	protected function get_editorassignment_set($submissionId) {
		$result = array();
		$dao = $this->stageAssignmentDao;
		$iter = $dao->getBySubmissionAndStageId($submissionId,
			WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
		$level1N = 0;
		foreach ($iter->toArray() as $stageassign) {
			$assignment = array();
			$user = $this->userDao->getById($stageassign->getUserId());
			$userGroup = $this->userGroupDao->getById($stageassign->getUserGroupId());
			$role = $userGroup->getRoleId();
			$levelMap = array(ROLE_ID_MANAGER => 3,
				ROLE_ID_SUB_EDITOR => 1);
			$level = $levelMap[$role] ?? 0;
			if (!$level)
				continue;  // irrelevant role, skip stage assignment entry
			elseif ($level == 1)
				$level1N++;
			$assignment['level'] = $level;
			$assignment['firstname'] = $user->getGivenName(RQC_LOCALE);
			$assignment['lastname'] = $user->getFamilyName(RQC_LOCALE);
			$assignment['email'] = $user->getEmail();
			$assignment['orcid_id'] = $user->getOrcid();
			$result[] = $assignment;  // append
		}
		if (!$level1N && count($result)) {
			// there must be at least one level-1 editor:
			$result[0]['level'] = 1;
		}
		return $result;
	}

	/**
	 * Return emailaddress of user.
	 * (And hope this same address is registered with RQC as well.)
	 */
	protected static function get_interactive_user($user) {
		return $user->getEmail();
	}

	/**
	 * Return linear array of RQC review descriptor objects.
	 */
	protected function get_review_set($submissionId, $reviewRound) {
		$result = array();
		$reviewRoundN = $reviewRound->getRound();
		$assignments = $this->reviewAssignmentDao->getBySubmissionId($submissionId, $reviewRoundN-1);
		foreach ($assignments as $reviewId => $assignment) {
			if ($assignment->getRound() != $reviewRoundN ||
				$assignment->getStageId() != WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)
				continue;  // irrelevant record, skip it.
			$rqcreview = array();
			$reviewerSubmission = $this->reviewerSubmissionDao->getReviewerSubmission($reviewId);
			//--- review metadata:
			$rqcreview['visible_id'] = $reviewId;
			$rqcreview['invited'] = $this->rqcify_datetime($assignment->getDateNotified());
			$rqcreview['agreed'] = $this->rqcify_datetime($assignment->getDateConfirmed());
			$rqcreview['expected'] = $this->rqcify_datetime($assignment->getDateDue());
			$rqcreview['submitted'] = $this->rqcify_datetime($assignment->getDateCompleted());
			//--- review text:
			$comment = $reviewerSubmission->getMostRecentPeerReviewComment();
			$text = "";
			if ($comment) {
				if ($comment->getCommentType() != COMMENT_TYPE_PEER_REVIEW)
					continue;  // irrelevant record, skip it
				$title = $comment->getCommentTitle();
				$body = $comment->getComments();
				$text = $title ? "<h2>$title</h2>\r\n$body" : $body;
			}
			$rqcreview['text'] = $text;
			$rqcreview['is_html'] = true;  // TODO: make ternary!
			$recommendation = $assignment->getRecommendation();
			$rqcreview['suggested_decision'] = $this->rqc_decision("reviewer", $recommendation);
			//--- reviewer:
			$reviewerobject = $this->userDao->getById($assignment->getReviewerId());
			$rqcreviewer = array();
			$rqcreviewer['email'] = $reviewerobject->getEmail();
			$rqcreviewer['firstname'] = $reviewerobject->getGivenName(RQC_LOCALE);
			$rqcreviewer['lastname'] = $reviewerobject->getFamilyName(RQC_LOCALE);
			$rqcreviewer['orcid_id'] = $reviewerobject->getOrcid();
			$rqcreview['reviewer'] = $rqcreviewer;
			$result[] = $rqcreview;  // append
		}
		return $result;
	}

	/**
	 * Get first english title if one exists or all titles otherwise.
	 * @param array $all_titles  mapping from locale name to title string
	 */
	protected static function get_title($all_titles) {
		return RqcData::englishest($all_titles, true);
	}

	/**
	 * Get visible_uid or submission_id for given round.
	 * First round is 1;
	 * if round is 0 (for a non-existing predecessor), return null.
	 */
     protected static function get_uid($journal, $submission, $round, $for_url=false) {
		if ($round == 0) {
			return null;
		}
		else {
			$journalname = $journal->getPath();
			$submission_id = $submission->getId();
			if ($for_url) {
				$journalname = preg_replace('/[^a-z0-9-_.:()-]/i', '-', $journalname);
			}
			return sprintf("%s-%s.R%d",
				$journalname, $submission_id, $round);
		}
	}

	/**
	 * Helper: Get first english entry if one exists or else:
	 * all entries in one string if $else_all or
	 * the entry of the alphabetically first locale otherwise.
	 * @param array $all_entries  mapping from locale name to string
	 */
	protected static function englishest($all_entries, $else_all=false) {
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
		}
		else {
			return $all_nonenglish_entries[0];
		}
	}

	/**
	 * Helper: Translate OJS recommendations into RQC decisions.
	 */
	protected static function rqc_decision($role, $ojs_decision) {
		$reviewerMap = array(
			// see lib.pkp.classes.submission.reviewAssignment.ReviewAssignment
			// the values are 1,2,3,4,5,6
			0 => "",
			SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT => "accept",
			SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS => "minorrevision",
			SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE => "majorrevision",
			SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE => "reject",
			SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE => "reject",
			SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS => "majorrevision",  // generic guess!!!
		);
		$editorMap = array(
			// see classes.workflow.EditorDecisionActionsManager
			// the values are 1,2,3,4,7,9,11,12,13,14
			0 => "",
			SUBMISSION_EDITOR_DECISION_ACCEPT => "accept",
			SUBMISSION_EDITOR_DECISION_DECLINE => "reject",
			SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE => "reject",
			SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS => "minorrevision",
			SUBMISSION_EDITOR_DECISION_RESUBMIT => "majorrevision",
			SUBMISSION_EDITOR_RECOMMEND_ACCEPT => "accept",
			SUBMISSION_EDITOR_RECOMMEND_DECLINE => "reject",
			SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => "minorrevision",
			SUBMISSION_EDITOR_RECOMMEND_RESUBMIT => "majorrevision",
			SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => "accept",
		);
		if ($role == "reviewer")
			return $reviewerMap[$ojs_decision];
		elseif ($role == "editor")
			return $editorMap[$ojs_decision];
		else
			assert(False, "rqc_decision: wrong role " + $role);
			return "";
	}

	/**
	 * Helper: Transform timestamp format to RQC convention.
	 */
	protected static function rqcify_datetime($ojs_datetime) {
		if (!$ojs_datetime) {
			return NULL;
		}
		$result = str_replace(" ", "T", $ojs_datetime);
		return $result . "Z";
	}
}


class RqcOjsData {
	/**
	 * Helper: Discriminate decisions from recommendations.
	 */
	public static function is_decision($ojs_decision)
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
