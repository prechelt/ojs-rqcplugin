 <?php

/**
 * @file plugins/generic/rqc/pages/DevHelperHandler.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class DevHelperHandler
 * @ingroup plugins_generic_rqc
 *
 * @brief Handle requests to show what OJS-to-RQC requests will look like or make one "by hand".
 */

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\handler\Handler;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
*/
use Composer\Semver\Semver;  // used by x()

class DevHelperHandler extends Handler
{
	var $plugin;

	function __construct()
	{
		parent::__construct();
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	/**
	 * Show RQC request corresponding to a given submissionId (args[0]) (with ?viewonly=1) or
	 * make the RQC request and show errors or perform the RQC redirect (with ?viewonly=0&stageId=3).
	 */
	function rqcCall($args, $request)
	{
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $this->plugin->getQueryArray($request);
		$journal = $router->getContext($request);
		$submissionId = $args[0];
		$viewOnly = array_key_exists('viewonly', $requestArgs) ? $requestArgs['viewonly'] : true;
		if ($viewOnly) {
			//----- get RQC data:
			$rqcDataObj = new RqcData();
			$data = $rqcDataObj->rqcdataArray($request, $journal->getId(), $submissionId);
			//----- produce output:
			header("Content-Type: application/json; charset=utf-8");
			//header("Content-Type: text/plain; charset=utf-8");
			print(json_encode($data, JSON_PRETTY_PRINT));
		} else {  //----- make an actual RQC call:
			$handler = new RqccallHandler();
			$rqcResult = $handler->sendToRqc($request, $journal->getId(), $submissionId);
			$handler->processRqcResponse($rqcResult['status'], $rqcResult['response']);
		}
	}

	/**
	 * reset/delete the RQC API-key and Id to test if the plugin responds correctly
	 */
	public function resetRQCAPIKeyAndId($args, $request)
	{
		// http://localhost:8000/index.php/test/rqcdevhelper/resetTestAPIKeyAndId/reset
		if ($args[0] == "set") {
			$this->setRQCAPIKeyAndId($request, $args[1], $args[2]);
		} elseif ($args[0] == "delete") {
			$this->setRQCAPIKeyAndId($request, "", "");
		} else {
			header("Content-Type: text/plain; charset=utf-8");
			print("huh?");
		}
	}

	public function setRQCAPIKeyAndId($request, String $rqcId, String $rqcAPIKey)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$contextId = $request->getContext()->getId();
		$this->plugin->updateSetting($contextId, 'rqcJournalId', $rqcId, 'string');
		$this->plugin->updateSetting($contextId, 'rqcJournalAPIKey', $rqcAPIKey, 'string');

		$hasId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$hasKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		print("Id: ".$hasId."<br>Key: ".$hasKey."<br>Returns: ValidKeyPair ".(PluginRegistry::getPlugin('generic', 'rqcplugin')->hasValidRqcIdKeyPair() ? "true" : "false"));
	}

	/**
	 * Make a previously submitted OJS reviewing case RQC-submittable again.
	 */
	public function raReset($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$submissionId =& $args[0];
		$userId = $request->getUser()->getId();
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$ra = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submissionId, $userId);
		$raId = $ra->getId();
		$ra->setRecommendation(null);
		$ra->setDateCompleted(null);
		$reviewAssignmentDao->updateObject($ra);
		return("raReset $raId (submission $submissionId, reviewer $userId)\n");
	}

	/**
	 * Make the reviewers rqcOptInStatus invalid so that it has to be set again
	 */
	public function rqcOptingStatusReset($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$contextId = $request->getContext()->getId();
		$user = $request->getUser();
		$userId = $user->getId();
		$user->updateSetting(ReviewerOpting::$datename, null, 'string', $contextId);
		$user->updateSetting(ReviewerOpting::$statusname, null, 'int', $contextId);
		return("rqcOptingStatusReset for reviewer $userId in journal $contextId");
	}

	/**
	 * Sandbox operation for trying this out.
	 */
	public function x($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$version = $args[0];
		$versionspec = $args[1];
		$semver = new Semver();
		$result = $semver->satisfies($version, $versionspec);
		print("Version: " . $version . ", Versionspec: " . $versionspec . "\n");
		print(" satisifies: " . ($result ? "yes" : "no"));
	}

	/**
	  * Make review case (MRC) in the current journal.
	  * INCOMPLETE AND OUTDATED. TODO 1: remove or rewrite.
	  */
	/*
	function mrc($args, $request)
	{
		header("Content-Type: text/html; charset=utf-8");
		echo "START\n";
		//----- prepare processing:
		$router = $request->getRouter();
		$requestArgs = $this->plugin->getQueryArray($request);
		$contextId = $request->getContext()->getId();
		$user = $request->getUser();
		$now = time();
		//----- make submission:
		$article = new Article();
		$article->setJournalId($contextId);
		$article->setTitle("Test submission " . date('Y-m-d H:i:s'), RQC_LOCALE);
		//$article->sub
		$this->articleDao->insertObject($article);
		//----- make authors:
		$author = new Author();
		$author->setGivenName("Anabel", RQC_LOCALE);
		$author->setFamilyName("Author1", RQC_LOCALE);
		$author->setEmail("author1@prechelt.dialup.fu-berlin.de");
		$author->setSubmissionId($article->getId());
		$this->authorDao->insertObject($author);
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
	*/
}
