 <?php

/**
 * @file plugins/generic/reviewqualitycollector/pages/SpyHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SpyHandler
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Handle requests to show what OJS-to-RQC requests will look like.
 */

import('classes.handler.Handler');
import('classes.workflow.EditorDecisionActionsManager');  // decision action constants

class SpyHandler extends Handler {

	function __construct() {
		parent::__construct();
		//----- store DAOs:
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
	function look($args, $request) {
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $request->getQueryArray();
		$journal = $router->getContext($request);
		$submissionId = $requestArgs['submissionId'];
		//$rqcPlugin =& PluginRegistry::getPlugin('generic', RQC_PLUGIN_NAME);
        $submission = $this->articleDao->getById($submissionId);
        $sectionname = $submission->getSectionAbbrev();
        if (!$sectionname)
        	$sectionname = $submission->getSectionTitle();
		$stage =

        //----- prepare response:
		// header("Content-Type: application/json; charset=utf-8");
		header("Content-Type: text/plain; charset=utf-8");
		$data = array();
		$data['submissionId'] = $submissionId;
		$data['api_version'] = '1.0alpha';

		//----- submission data:
		$data['title'] = $this->get_title($submission->getTitle(null));
		$lastReviewRound = $this->reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId);
		$reviewroundN = $lastReviewRound->getRound();
		$data['visible_uid'] = $this->get_uid($journal, $submission, $reviewroundN);
		$alldata = $submission->getAllData();
		$data['submitted'] = $this->rqcify_datetime($alldata['dateSubmitted']);
		// assume that round $reviewroundN-1 exists (but it may not):
		$data['predecessor_submission_id'] = $this->get_uid($journal, $submission,
				$reviewroundN-1, true);
		$data['predecessor_visible_uid'] = $this->get_uid($journal, $submission,
			$reviewroundN-1, false);

		//----- authors, editor assignments, reviews, decision:
		$data['author_set'] = $this->get_author_set($submission->getAuthors());
		$data['editorassignment_set'] = $this->get_editorassignment_set($submissionId);
		$assignments = $this->reviewAssignmentDao->getBySubmissionId($submissionId, $reviewroundN-1);
		$data['review_set'] = $this->get_review_set($assignments, $lastReviewRound);
		$data['decision'] = $this->get_decision();

        print(json_encode($data, JSON_PRETTY_PRINT));
	}

	/**
	 * Get first english entry if one exists or else:
	 * all entries in one string if $else_all or
	 * the entry of the alphabetically first locale otherwise.
	 * @param array $all_entries  mapping from locale name to string
	 */
	function englishest($all_entries, $else_all=false) {
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
	 * Get first english title if one exists or all titles otherwise.
	 * @param array $all_titles  mapping from locale name to title string
	 */
	function get_title($all_titles) {
		return $this->englishest($all_titles, true);
	}

	/**
	 * Get visible_uid or submission_id for given round.
	 * First round is 1;
	 * if round is 0 (for a non-existing predecessor), return null.
	 */
	function get_uid($journal, $submission, $round, $for_url=false) {
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
	 * Transform timestamp format to RQC convention.
	 */
	function rqcify_datetime($ojs_datetime) {
		if (!$ojs_datetime) {
			return NULL;
		}
		$result = str_replace(" ", "T", $ojs_datetime);
		return $result . "Z";
	}

	/**
	 * Return linear array of RQC-ish author objects.
	 */
	function get_author_set($authorsobjects) {
		$result = array();
		foreach ($authorsobjects as $authorobject) {
			$rqcauthor = array();
			$rqcauthor['email'] = $authorobject->getEmail();
			$rqcauthor['firstname'] = $authorobject->getFirstName();
			$rqcauthor['lastname'] = $authorobject->getLastName();
			$rqcauthor['is_corresponding'] = true;  // TODO
			$rqcauthor['order_number'] = (int)($authorobject->getSequence());
			$result[] = $rqcauthor;
		}
		return $result;
	}

	/**
	 * Return linear array of RQC editorship descriptor objects.
	 */
	function get_editorassignment_set($submissionId) {
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
			$level = $levelMap[$role];
			if (!$level)
				continue;  // irrelevant role, skip stage assignment entry
			elseif ($level == 1)
				$level1N++;
			$assignment['level'] = $level;
			$assignment['firstname'] = $user->getFirstName();
			$assignment['lastname'] = $user->getLastName();
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
	 * Return linear array of RQC review descriptor objects.
	 */
	function get_review_set($assignments, $reviewRound) {
		$result = array();
		foreach ($assignments as $reviewId => $assignment) {
			if ($assignment->getRound() != $reviewRound->getRound() ||
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
			//$rqcreview['__reviewerSubmission__'] = $reviewerSubmission;
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
			$rqcreview['suggested_decision'] = "TODO suggested_decision";
			//--- reviewer:
			$reviewerobject = $this->userDao->getById($assignment->getReviewerId());
			$rqcreviewer = array();
			$rqcreviewer['email'] = $reviewerobject->getEmail();
			$rqcreviewer['firstname'] = $reviewerobject->getFirstName();
			$rqcreviewer['lastname'] = $reviewerobject->getLastName();
			$rqcreviewer['orcid_id'] = $reviewerobject->getOrcid();
			$rqcreview['reviewer'] = $rqcreviewer;
			$result[] = $rqcreview;  // append
		}
		return $result;
	}

	/**
	 * Return RQC-style decision string.
	 */
	function get_decision() {
		return array("TODO: get_decision");
	}

	/**
	 * Ensure that we have a journal and the plugin is enabled.
	 */
	function authorize($request, &$args, $roleAssignments) {
		return true;  // TODO?
	}

	/**
	 * Setup common template variables.
	 * @param $subclass boolean set to true if caller is below this handler in the hierarchy
	 */
	function setupTemplate($request, $op = 'index') {
		$templateMgr = TemplateManager::getManager($request);

		$opMap = array(
			'index' => 'navigation.search',
			'categories' => 'navigation.categories'
		);

		$router = $request->getRouter();
		$journal = $router->getContext($request);
		if (!$journal || !$journal->getSetting('restrictSiteAccess')) {
			$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
		}
	}

	/**
	 * Temporary helper function for exploring the DAOs.
	 */
	function getters_of($object) {
		$getters = array_filter(get_class_methods($object),
					function($s) { return substr($s, 0, 3) == "get"; });
		$getters = array_values($getters);  // get rid of keys
		sort($getters);
		return $getters;
	}
}


?>
