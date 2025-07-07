<?php

namespace APP\plugins\generic\rqc\pages;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\plugins\generic\rqc\classes\RqcReviewerOpting\RqcReviewerOpting;
use PKP\db\DAORegistry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use Composer\Semver\Semver; // used by x()

use APP\plugins\generic\rqc\classes\DelayedRqcCall\DelayedRqcCallSchemaMigration;
use APP\plugins\generic\rqc\classes\DelayedRqcCallSender;
use APP\plugins\generic\rqc\classes\DelayedRqcCall\DelayedRqcCall;
use APP\plugins\generic\rqc\classes\DelayedRqcCall\DelayedRqcCallDAO;
use APP\plugins\generic\rqc\classes\RqcData;
use APP\plugins\generic\rqc\classes\ReviewerOpting;
use APP\plugins\generic\rqc\classes\RqcLogger;
use APP\plugins\generic\rqc\pages\RqcCallHandler;
use APP\plugins\generic\rqc\RqcPlugin;
use APP\plugins\generic\rqc\classes\RqcDevHelper;
use APP\plugins\generic\rqc\classes\RqcReviewerOpting\RqcReviewerOptingDAO;
use APP\plugins\generic\rqc\classes\RqcReviewerOpting\RqcReviewerOptingSchemaMigration;


/**
 * Handle requests to show what OJS-to-RQC requests will look like or make one "by hand"
 *
 * @ingroup plugins_generic_rqc
 */
class RqcDevHelperHandler extends Handler
{
	public RqcPlugin $plugin;

	function __construct(RqcPlugin $plugin)
	{
		parent::__construct();
		$this->plugin = $plugin;
	}

	/**
     * Show RQC request corresponding to a given submissionId (args[0]) (with ?viewonly=1) or
	 * make the RQC request and show errors or perform the RQC redirect (with ?viewonly=0 or no query parameter).
     * Only constraint is when performing an actual RQC request. Then the user logged in is the one submitting the request
     * open with:   https://base.url/context/rqcdevhelper/rqcCall/someSubmissionId?viewonly=1
     * or:          https://base.url/context/rqcdevhelper/rqcCall/someSubmissionId
	 */
	function rqcCall($args, $request)
	{
		//----- prepare processing:
		$requestArgs = $this->plugin->getQueryArray($request);
		$submissionId = $args[0];
		$viewOnly = array_key_exists('viewonly', $requestArgs) ? $requestArgs['viewonly'] : true;
		if ($viewOnly) {
			//----- get RQC data:
			$rqcDataObj = new RqcData();
			$data = $rqcDataObj->rqcDataArray($request, $submissionId);
			//----- produce output:
			header("Content-Type: application/json; charset=utf-8");
			//header("Content-Type: text/plain; charset=utf-8");
			print(json_encode($data, JSON_PRETTY_PRINT));
		} else {  //----- make an actual RQC call:
			$handler = new RqcCallHandler();
			$rqcResult = $handler->sendToRqc($request, $submissionId); // test explicit call
			$handler->processRqcResponse($rqcResult, $submissionId, true);
		}
	}

	/**
	 * reset/delete the RQC API-key and ID to test if the plugin responds correctly
     * Open from a window where a user is logged in so that journal-context is used
     * open with:   https://base.url/context/rqcdevhelper/resetRqcAPIKeyAndId/set/someRqcId/someRqcApiKey
     * or:          https://base.url/context/rqcdevhelper/resetRqcAPIKeyAndId/delete
	 */
	public function resetRqcAPIKeyAndId($args, $request): void
	{
		if ($args[0] == "set") {
			$this->setRqcAPIKeyAndId($request, $args[1], $args[2]);
		} elseif ($args[0] == "delete") {
			$this->setRqcAPIKeyAndId($request, "", "");
		} else {
			print("huh?");
		}
	}

	private function setRqcAPIKeyAndId($request, string $rqcId, string $rqcAPIKey): void
	{
		$contextId = $request->getContext()->getId();
		$this->plugin->updateSetting($contextId, 'rqcJournalId', $rqcId, 'string');
		$this->plugin->updateSetting($contextId, 'rqcJournalAPIKey', $rqcAPIKey, 'string');

		$hasId = $this->plugin->getSetting($contextId, 'rqcJournalId');
		$hasKey = $this->plugin->getSetting($contextId, 'rqcJournalAPIKey');
		print("Id: $hasId <br>Key: $hasKey<br>Returns: ValidKeyPair " . ($this->plugin->hasValidRqcIdKeyPair() ? "true" : "false"));
	}

	/**
	 * Make a previously submitted OJS reviewing case RQC-submittable again.
     * Open from a window where the reviewer is logged in
     * open with:   https://base.url/context/rqcdevhelper/raReset/someSubmissionId
	 */
	public function raReset($args, $request)
	{
		$submissionId =& $args[0];
		$userId = $request->getUser()->getId();

        $reviewAssignment = Repo::reviewAssignment()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$userId])
            ->getMany()->first(); /**@var ReviewAssignment $reviewAssignment * */

        Repo::reviewAssignment()->edit($reviewAssignment, [
            'step' => 3,
            'dateCompleted' => null,
            'recommendation' => null,
        ]);
		return ("raReset " . $reviewAssignment->getId() . "(submission $submissionId, reviewer $userId)<br>");
	}

    /**
     * Make the reviewers rqcOptInStatus invalid for all submissions and years.
     * So that the select will appear as all entries of that year and context for the user are deleted.
     * Open from a window where the reviewer is logged in
     * open with:   https://base.url/context/rqcdevhelper/rqcOptingStatusReset/someYear
     */
    public function rqcOptingStatusReset($args, $request)
    {
        $year =& $args[0];
        $contextId = $request->getContext()->getId();
        $user = $request->getUser();
        $userId = $user->getId();
        /** @var $rqcReviewerOptingDAO RqcReviewerOptingDAO */
        $rqcReviewerOptingDAO = DAORegistry::getDAO('RqcReviewerOptingDAO');
        $rqcReviewerOptings = $rqcReviewerOptingDAO->getReviewerOptingsForContextAndYear($contextId, $userId, $year)->toArray();
        foreach ($rqcReviewerOptings as $rqcReviewerOpting) {
            $rqcReviewerOptingDAO->delete($rqcReviewerOpting);
        }
        return ("rqcOptingStatusReset for reviewer $userId in $contextId and $year");
    }

    /**
     * Make the reviewers rqcOptInStatus invalid so that it has to be set again for that submission.
     * The select may still not appear as there could be other entries of that year and context for the user.
     * Open from a window where the reviewer is logged in
     * open with:	https://base.url/context/rqcdevhelper/rqcSingleOptingStatusReset/someSubmissionId
     */
    public function rqcSingleOptingStatusReset($args, $request)
    {
        $submissionId =& $args[0];
        $user = $request->getUser();
        $userId = $user->getId();
        /** @var $rqcReviewerOptingDAO RqcReviewerOptingDAO */
        $rqcReviewerOptingDAO = DAORegistry::getDAO('RqcReviewerOptingDAO');
        $rqcReviewerOpting = $rqcReviewerOptingDAO->getReviewerOptingForSubmission($submissionId, $user->getId());
        $rqcReviewerOptingDAO->delete($rqcReviewerOpting);
        return ("rqcOptingStatusReset for reviewer $userId in submission $submissionId");
    }

	/**
	 * to create/delete the table in the database (usually done after installation of the plugin)
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/updateRqcDelayedCallsTable
	 */
	public function updateRqcDelayedCallsTable($args, $request)
	{
		$migration = new DelayedRqcCallSchemaMigration();
		$migration->down();
		$migration->up();
	}

    /**
     * to create/delete the table in the database (usually done after installation of the plugin)
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/updateRqcReviewerOpting
     */
    public function updateRqcReviewerOpting($args, $request)
    {
        $migration = new RqcReviewerOptingSchemaMigration();
        $migration->down();
        $migration->up();
    }

	/**
	 * Sandbox operation for trying this out.
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/x/someVersion/someVersionSpec
	 */
	public function x($args, $request)
	{
		$version = $args[0];
		$versionspec = $args[1];
		$semver = new Semver();
		$result = $semver->satisfies($version, $versionspec);
		print("Version: $version, Versionspec: $versionspec<br> satisifies: " . ($result ? "yes" : "no"));
	}

	/**
	 * simulate one execution called by the cronjob
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/executeQueue
	 */
	public function executeQueue($args, $request)
	{
		$sender = new DelayedRqcCallSender();
		$sender->executeActions();
	}

	/**
	 * Enqueue a new delayedCall with a given submissionId (args[0])
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/enqueueDelayedRqcCall/someSubmissionId
	 */
	public function enqueueDelayedRqcCall($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$submissionId = $args[0];
		$rqcCallHandler = new RqcCallHandler();
		$delayedRqcCallId = $rqcCallHandler->putCallIntoQueue($submissionId);
        /** @var $delayedRqcCallDao DelayedRqcCallDAO */
		$delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO');
		$delayedRqcCall = $delayedRqcCallDao->get($delayedRqcCallId);
		print_r($delayedRqcCall);
	}

	/**
	 * Update an delayedRqcCall with a given delayedRqcCallId (args[0])
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/updateDelayedRqcCallById/someDelayedRqcCallId
	 */
	public function updateDelayedRqcCallById($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$delayedRqcCallId = $args[0];
        /** @var $delayedRqcCallDao DelayedRqcCallDAO */
        $delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO');
		$delayedRqcCall = $delayedRqcCallDao->get($delayedRqcCallId);
		$delayedRqcCallDao->updateCall($delayedRqcCall);
		print_r($delayedRqcCall);
	}

	/**
	 * Delete an delayedRqcCall with a given delayedRqcCallId (args[0])
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/deleteDelayedCallById/someDelayedRqcCallId
	 */
	public function deleteDelayedCallById($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$delayedRqcCallId = $args[0];
        /** @var $delayedRqcCallDao DelayedRqcCallDAO */
        $delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO');
		$delayedRqcCall = $delayedRqcCallDao->get($delayedRqcCallId);
		$delayedRqcCallDao->deleteById($delayedRqcCallId);
		print_r($delayedRqcCall);
	}

	/**
	 * Print the content of the queue right now
     * No constraints with which user to be logged in
     * open with:   https://base.url/context/rqcdevhelper/printDelayedRqcCallQueue
	 */
	public function printDelayedRqcCallQueue($args, $request)
	{
		header("Content-Type: text/plain; charset=utf-8");
		$delayedRqcCallDao = DAORegistry::getDAO('DelayedRqcCallDAO');
		$delayedRqcCalls = $delayedRqcCallDao->getCallsToRetry(0, time())->toArray();
		print_r($delayedRqcCalls);
	}

	/**
	 * Make review case (MRC) in the current journal.
	 * INCOMPLETE AND OUTDATED. TODO 3: remove or rewrite. (maybe useful for the building of tests)
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
		$article->setTitle("Test submission " . date('Y-m-d H:i:s'), RqcPlugin::RQC_LOCALE);
		//$article->sub
		$this->articleDao->insertObject($article);
		//----- make authors:
		$author = new Author();
		$author->setGivenName("Anabel", RqcPlugin::RQC_LOCALE);
		$author->setFamilyName("Author1", RqcPlugin::RQC_LOCALE);
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

class loremIpsumGenerator // https://stackoverflow.com/questions/20633310/how-to-get-random-text-from-lorem-ipsum-in-php
{
	public static function ipsumChars(int $nChars): string
	{
		$chars = "";
		while (strlen($chars) < $nChars) {
			$nWords = random_int(3, 15);
			$words = self::randomValues(self::$lorem, $nWords);
			$chars .= implode(' ', $words);
		}
		return mb_strimwidth($chars, 0, $nChars);
	}

	private static function randomValues($arr, $count): array
	{
		$keys = array_rand($arr, $count);
		if ($count == 1) {
			$keys = [$keys];
		}
		return array_intersect_key($arr, array_fill_keys($keys, null));
	}

	private static array $lorem = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
		'praesent', 'interdum', 'dictum', 'mi', 'non', 'egestas', 'nulla', 'in', 'lacus', 'sed', 'sapien', 'placerat',
		'malesuada', 'at', 'erat', 'etiam', 'id', 'velit', 'finibus', 'viverra', 'maecenas', 'mattis', 'volutpat',
		'justo', 'vitae', 'vestibulum', 'metus', 'lobortis', 'mauris', 'luctus', 'leo', 'feugiat', 'nibh', 'tincidunt',
		'a', 'integer', 'facilisis', 'lacinia', 'ligula', 'ac', 'suspendisse', 'eleifend', 'nunc', 'nec', 'pulvinar',
		'quisque', 'ut', 'semper', 'auctor', 'tortor', 'mollis', 'est', 'tempor', 'scelerisque', 'venenatis', 'quis',
		'ultrices', 'tellus', 'nisi', 'phasellus', 'aliquam', 'molestie', 'purus', 'convallis', 'cursus', 'ex', 'massa',
		'fusce', 'felis', 'fringilla', 'faucibus', 'varius', 'ante', 'primis', 'orci', 'et', 'posuere', 'cubilia',
		'curae', 'proin', 'ultricies', 'hendrerit', 'ornare', 'augue', 'pharetra', 'dapibus', 'nullam', 'sollicitudin',
		'euismod', 'eget', 'pretium', 'vulputate', 'urna', 'arcu', 'porttitor', 'quam', 'condimentum', 'consequat',
		'tempus', 'hac', 'habitasse', 'platea', 'dictumst', 'sagittis', 'gravida', 'eu', 'commodo', 'dui', 'lectus',
		'vivamus', 'libero', 'vel', 'maximus', 'pellentesque', 'efficitur', 'class', 'aptent', 'taciti', 'sociosqu',
		'ad', 'litora', 'torquent', 'per', 'conubia', 'nostra', 'inceptos', 'himenaeos', 'fermentum', 'turpis', 'donec',
		'magna', 'porta', 'enim', 'curabitur', 'odio', 'rhoncus', 'blandit', 'potenti', 'sodales', 'accumsan', 'congue',
		'neque', 'duis', 'bibendum', 'laoreet', 'elementum', 'suscipit', 'diam', 'vehicula', 'eros', 'nam', 'imperdiet',
		'sem', 'ullamcorper', 'dignissim', 'risus', 'aliquet', 'habitant', 'morbi', 'tristique', 'senectus', 'netus',
		'fames', 'nisl', 'iaculis', 'cras', 'aenean'
	];
}
