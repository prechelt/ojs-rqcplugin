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

	/**
	 * Show RQC request corresponding to a given submissionId=n arg.
	 */
	function look($args, $request) {
		$router = $request->getRouter();
		$requestArgs = $request->getQueryArray();
		$journal = $router->getContext($request);
		$submissionId = $requestArgs['submissionId'];
		$rqcPlugin =& PluginRegistry::getPlugin('generic', 'reviewqualitycollector');

		$journalDao = DAORegistry::getDAO('JournalDAO');
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
        $submission = $articleDao->getById($submissionId);

		// header("Content-Type: application/json; charset=utf-8");
		header("Content-Type: text/plain; charset=utf-8");
		$data = array('submissionId' => $submissionId);
		$data['api_version'] = '1.0alpha';
		$data['title'] = $this->get_title($submission->getTitle(null));
		$reviewroundN = $reviewRoundDao->getCurrentRoundBySubmissionId($submissionId);
		$data['visible_uid'] = $this->get_uid($journal, $submission, $reviewroundN);
		$alldata = $submission->getAllData();
		$data['submitted'] = $this->rqcify_datetime($alldata['dateSubmitted']);
		// we assume that round $reviewroundN-1 always exists:
		$data['predecessor_submission_id'] = $this->get_uid($journal, $submission,
				$reviewroundN-1, true);
		$data['predecessor_visible_uid'] = $this->get_uid($journal, $submission,
			$reviewroundN-1, false);
		$data['author_set'] = $this->get_author_set($submission->getAuthors());
		$data['assignment_set'] = $this->get_assignment_set();
		$assignments = $reviewAssignmentDao->getBySubmissionId($submissionId, $reviewroundN-1);
		$data['review_set'] = $this->get_review_set($assignments, $userDao);
		$data['decision'] = $this->get_decision();

        $data['====='] = "====================";
		$data['submissionId'] = $submissionId;
		$data['status'] = $submission->getStatus();
		$reviewrounddata = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId);
		$data['lastrounddata'] = $reviewrounddata;
        $assignments = $reviewAssignmentDao->getBySubmissionId($submissionId, $reviewroundN-1);
		$data['reviewassignments'] = $assignments;
		$data['user'] = $userDao->getById(3);
		$data['reviewersubmission'] = $reviewerSubmissionDao->get);
		$data['article'] = $this->getters_of($submission);
		$data['journal'] = $this->getters_of($journal);
		$data['request'] = array_keys(get_object_vars($request));
		$data['router'] = array_keys(get_object_vars($router));
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
	function get_assignment_set() {
		return array("TODO: get_assignment_set");
	}

	/**
	 * Return linear array of RQC review descriptor objects.
	 */
	function get_review_set($reviewobjects, $userDao) {
		$result = array();
		foreach ($reviewobjects as $idx => $reviewobject) {
			$rqcreview = array();
			$rqcreview['visible_id'] = $idx;
			$rqcreview['invited'] = $this->rqcify_datetime($reviewobject->getDateNotified());
			$rqcreview['agreed'] = $this->rqcify_datetime($reviewobject->getDateConfirmed());
			$rqcreview['expected'] = $this->rqcify_datetime($reviewobject->getDateDue());
			$rqcreview['submitted'] = $this->rqcify_datetime($reviewobject->getDateCompleted());
			$rqcreview['text'] = $reviewobject->();
			$rqcreview['is_html'] = false;  // TODO: make ternary!
			$reviewerobject = $userDao->getById($reviewobject->getReviewerId());
			$rqcreviewer = array();
			$rqcreviewer['email'] = $reviewerobject->getEmail();
			$rqcreviewer['firstname'] = $reviewerobject->getFirstName();
			$rqcreviewer['lastname'] = $reviewerobject->getLastName();
			$rqcreviewer['orcid_id'] = $reviewerobject->orcid();
			$rqcreview['reviewer'] = $rqcreviewer;
			$result[] = $rqcreview;
		}
		return $result;
	}

	/**
	 * Return RQC-style decision string.
	 */
	function get_decision() {
		return array("TODO: get_author_set");
	}


	/**
	 * Show list of journal sections identify types.
	 */
	function identifyTypes($args = array(), $request) {
		$this->setupTemplate($request);

		$router = $request->getRouter();
		$journal = $router->getContext($request);

		$browsePlugin =& PluginRegistry::getPlugin('generic', BROWSE_PLUGIN_NAME);
		$enableBrowseByIdentifyTypes = $browsePlugin->getSetting($journal->getId(), 'enableBrowseByIdentifyTypes');
		if ($enableBrowseByIdentifyTypes) {
			if (isset($args[0]) && $args[0] == 'view') {
				$identifyType = $request->getUserVar('identifyType');
				$sectionDao = DAORegistry::getDAO('SectionDAO');
				$sectionsIterator = $sectionDao->getByJournalId($journal->getId());
				$sections = array();
				while (($section = $sectionsIterator->next())) {
					if ($section->getLocalizedIdentifyType() == $identifyType) {
						$sections[] = $section;
					}
				}
				$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
				$publishedArticleIds = array();
				foreach ($sections as $section) {
					$publishedArticleIdsBySection = $publishedArticleDao->getPublishedArticleIdsBySection($section->getId());
					$publishedArticleIds = array_merge($publishedArticleIds, $publishedArticleIdsBySection);
				}

				$rangeInfo = $this->getRangeInfo($request, 'search');
				$totalResults = count($publishedArticleIds);
				$publishedArticleIds = array_slice($publishedArticleIds, $rangeInfo->getCount() * ($rangeInfo->getPage()-1), $rangeInfo->getCount());
				$articleSearch = new ArticleSearch();

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'results' => new VirtualArrayIterator($articleSearch->formatResults($publishedArticleIds), $totalResults, $rangeInfo->getPage(), $rangeInfo->getCount()),
					'title' => $identifyType,
					'enableBrowseByIdentifyTypes' => $enableBrowseByIdentifyTypes,
				));
				$templateMgr->display($browsePlugin->getTemplatePath() . 'searchDetails.tpl');
			} else {
				$excludedIdentifyTypes = $browsePlugin->getSetting($journal->getId(), 'excludedIdentifyTypes');
				$sectionDao = DAORegistry::getDAO('SectionDAO');
				$sectionsIterator = $sectionDao->getByJournalId($journal->getId());
				$sectionidentifyTypes = array();
				while (($section = $sectionsIterator->next())) {
					if ($section->getLocalizedIdentifyType() && !in_array($section->getId(), $excludedIdentifyTypes) && !in_array($section->getLocalizedIdentifyType(), $sectionidentifyTypes)) {
						$sectionidentifyTypes[] = $section->getLocalizedIdentifyType();
					}
				}
				sort($sectionidentifyTypes);

				$rangeInfo = $this->getRangeInfo($request, 'search');
				$totalResults = count($sectionidentifyTypes);
				$sectionidentifyTypes = array_slice($sectionidentifyTypes, $rangeInfo->getCount() * ($rangeInfo->getPage()-1), $rangeInfo->getCount());
				$results = new VirtualArrayIterator($sectionidentifyTypes, $totalResults, $rangeInfo->getPage(), $rangeInfo->getCount());

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('results', $results);
				$templateMgr->assign('enableBrowseByIdentifyTypes', $enableBrowseByIdentifyTypes);
				$templateMgr->display($browsePlugin->getTemplatePath() . 'searchIndex.tpl');
			}
		} else {
			$request->redirect(null, 'index');
		}
	}

	/**
	 * Ensure that we have a journal and the plugin is enabled.
	 */
	function authorize($request, &$args, $roleAssignments) {
		return true;
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
