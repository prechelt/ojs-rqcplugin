 <?php

/**
 * @file plugins/generic/reviewqualitycollector/pages/DevHelperHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class DevHelperHandler
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Handle requests to show what OJS-to-RQC requests will look like.
 */

/* for OJS 3.4:
namespace APP\plugins\generic\reviewqualitycollector;
use APP\handler\Handler;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
*/

class DevHelperHandler extends Handler {
	var $plugin;

	function __construct() {
		parent::__construct();
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	/**
	 * Show RQC request corresponding to a given submissionId (args[0]) (with ?viewonly=1) or
	 * make the RQC request and show errors or perform the RQC redirect (with ?viewonly=0).
	 */
	function rqccall($args, $request) {
		$show_confirmation_page = true;  // hardcoded behavior switch
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = getQueryArray($request);
		$context = $request->getContext();
		$user = $request->getUser();
		$journal = $router->getContext($request);
		$submissionId = $args[0];
		$viewOnly = array_key_exists('viewonly', $requestArgs) ? $requestArgs['viewonly'] : true;
		$rqcJournalId = $this->plugin->getSetting($context->getId(), 'rqcJournalId');
		$rqcJournalAPIKey = $this->plugin->getSetting($context->getId(), 'rqcJournalAPIKey');
		if ($viewOnly) {
			//----- get RQC data:
			$rqcDataObj = new RqcData();
			$data = $rqcDataObj->rqcdata_array($request, $journal, $submissionId);
			//----- add interesting bits:
			$data['=========='] = "####################";
			//$data['journal'] = $journal;
			$data['rqc_journal_id'] = $rqcJournalId;
			$data['rqc_journal_api_key'] = $rqcJournalAPIKey;
			//----- produce output:
			header("Content-Type: application/json; charset=utf-8");
			//header("Content-Type: text/plain; charset=utf-8");
			print(json_encode($data, JSON_PRETTY_PRINT));
		}
		else {  //----- make an actual RQC call:
			$result = call_mhs_submission($this->plugin->rqc_server(), $rqcJournalId, $rqcJournalAPIKey,
										  $request, $journal, $submissionId);
			$status = $result['status'];
			$response = $result['response'];
			if ($status == 303) {  // that's what we expect: redirect
				if ($show_confirmation_page) {
					header("Content-Type: text/html; charset=utf-8");
					$target_url = $response['redirect_target'];
					$submission_title = $result['request']['title'];
					print(rqcgrading_confirmation_page($target_url, $submission_title));
				}
				else {
					header("HTTP/1.1 303 See Other");
					header("Location: " . $response['redirect_target']);
				}
			}
			else {  // hmm, something is wrong: Show the JSON response
				header("Content-Type: application/json; charset=utf-8");
				print(json_encode($response, JSON_PRETTY_PRINT));
			}
		}
	}

	/**
	 * Make a previously submitted OJS reviewing case RQC-submittable again.
	 */
	public function ra_reset($args, $request) {
		header("Content-Type: text/plain; charset=utf-8");
		$submissionId =& $args[0];
		$userId = $request->getUser()->getId();
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$ra = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submissionId, $userId);
		$raId = $ra->getId();
		$ra->setRecommendation(null);
		$ra->setDateCompleted(null);
		$reviewAssignmentDao->updateObject($ra);
		return("ra_reset $raId (submission $submissionId, reviewer $userId)\n");
	}

	/**
	 * Simple "Hello, world!" request. TODO 2: remove?
	 */
	public function hello($args, $request) {
		header("Content-Type: text/plain; charset=utf-8");
		$rq = array(
			// "context" => $request->getContext(),
			"contextId" => $request->getContext()->getId(),
			"user" => $request->getUser(),
			"userVars" => $request->getUserVars(),
			       );
		return "Hello, this is the request\n" . json_encode($rq, JSON_PRETTY_PRINT)
			. "\nand the args:\n" . json_encode($args, JSON_PRETTY_PRINT) . "\n";
	}

	/**
	 * Temporary helper function for exploring the DAOs.
	 */
	protected function getters_of($object) {
		$getters = array_filter(get_class_methods($object),
			function($s) { return substr($s, 0, 3) == "get"; });
		$getters = array_values($getters);  // get rid of keys
		sort($getters);
		return $getters;
	}

	/**
	  * Make review case (MRC) in the current journal.
	  * INCOMPLETE AND OUTDATED. TODO 1: remove or rewrite.
	  */
	function mrc($args, $request) {
		header("Content-Type: text/html; charset=utf-8");
		echo "START\n";
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $request->getQueryArray();
		$contextId = $request->getContext()->getId();
		$user = $request->getUser();
		$now = time();
		//----- make submission:
		$article = new Article();
		$article->setJournalId($contextId);
		$article->setTitle("Test submission " . date('Y-m-d H:i:s'), RQC_LOCALE);
		//$article->sub
		printf("%s\n", $article->getTitle(RQC_LOCALE));
		$this->articleDao->insertObject($article);
		//----- make authors:
		$author = new Author();
		$author->setGivenName("Anabel", RQC_LOCALE);
		$author->setFamilyName("Author1", RQC_LOCALE);
		$author->setEmail("author1@prechelt.dialup.fu-berlin.de");
		$author->setSubmissionId($article->getId());
		$this->authorDao->insertObject($author);
		printf("context: %d\n", $contextId);
		//----- make review round:
		//-----	make editor assignments:
		//----- make reviewer assignments:
		//----- make reviews:
		//----- make decision
		//-----
		//-----
		//-----
		//-----
		//-----
		//----- produce output:
		//header("Content-Type: application/json; charset=utf-8");
		//header("Content-Type: text/plain; charset=utf-8");
		//print(json_encode($data, JSON_PRETTY_PRINT));
		echo "END.\n";
	}
}

/**
 * Workaround for $request->getQueryArray().
 * In OJS 3.3.0, that call produces the error
 * "Application::getContextList() cannot be called statically"
 */
 function getQueryArray($request) {
	$queryString = $request->getQueryString();
	$requestArgs = array();
	if (isset($queryString)) {
		parse_str($queryString, $requestArgs);
	}
	return $requestArgs;
}
