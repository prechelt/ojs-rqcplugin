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

import('classes.handler.Handler');
import('plugins.generic.reviewqualitycollector.classes.RqcData');

class DevHelperHandler extends Handler {
	function __construct() {
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
		//--- store DAOs:
		$this->journalDao = DAORegistry::getDAO('JournalDAO');
		$this->articleDao = DAORegistry::getDAO('ArticleDAO');
		$this->authorDao = DAORegistry::getDAO('AuthorDAO');
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
	function spy($args, $request) {
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $request->getQueryArray();
		$context = $request->getContext();
		$user = $request->getUser();
		$journal = $router->getContext($request);
		$submissionId = $requestArgs['submissionId'];
		//----- get RQC data:
		$rqcDataObj = new RqcData();
		$data = $rqcDataObj->rqcdata_array($user, $journal, $submissionId);
		//----- add interesting bits:
		$data['=========='] = "####################";
		//$data['journal'] = $journal;
		$data['rqc_journal_id'] = $this->plugin->getSetting($context->getId(), 'rqcJournalId');
		$data['rqc_journal_key'] = $this->plugin->getSetting($context->getId(), 'rqcJournalKey');
        //----- produce output:
		header("Content-Type: application/json; charset=utf-8");
		//header("Content-Type: text/plain; charset=utf-8");
		print(json_encode($data, JSON_PRETTY_PRINT));
	}

	function hello($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		echo "Hello, world!";
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
	  * INCOMPLETE AND OUTDATED. TODO: remove.
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
