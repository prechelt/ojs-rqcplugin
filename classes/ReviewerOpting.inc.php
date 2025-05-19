<?php

import('lib.pkp.classes.plugins.HookRegistry');

import('plugins.generic.rqc.RqcPlugin');
import('plugins.generic.rqc.classes.RqcDevHelper');
import('plugins.generic.rqc.classes.RqcLogger');

define('RQC_OPTING_STATUS_IN', 36);  // internal, external
define('RQC_OPTING_STATUS_OUT', 35);  // internal, external
define('RQC_OPTING_STATUS_IN_PRELIM', 32);  // internal (save for later; not submitted yet)
define('RQC_OPTING_STATUS_OUT_PRELIM', 31);  // internal (save for later; not submitted yet)
define('RQC_OPTING_STATUS_UNDEFINED', 30);  // external only
define('RQC_PRELIM_OPTING', true);  // for readability

/**
 * Handle the opt-in/opt-out status of a user (via the step3Form)
 * setStatus()/getStatus()/optingRequired() manage the status in two user settings fields.
 * rqcReviewerOptingFormField.tpl adds an opting selection field that is included into a custom step3.tpl that overwrites OJS' step3.tpl
 * these methods are usually called in this order to get the values and then take them after a submit
 *  - callbackInitOptingData() (for GET) injects the current opting value (null selected) and a flag for showing/not showing the field.
 *  - callbackAddReviewerOptingField() injects the selection values.
 *  - callbackReadOptIn() (for POST) moves the opting value from request to form.
 *  - callbackStep3execute() (for POST) stores opting value into DB.
 *  - callbackStep3saveForLater() (for POST) stores opting value into DB (preliminary).
 *
 * @see      PKPReviewerReviewStep3Form
 * @ingroup  plugins_generic_rqc
 */
class ReviewerOpting
{
	public static string $statusName = 'rqcOptingStatus_';

	/**
	 * Register callbacks. This is to be called from the plugin's register().
	 */
	public function register(): void
	{
		// used for the building of the form
		HookRegistry::register(
			'reviewerreviewstep3form::initdata',
			array($this, 'callbackInitOptingData')
		);
		HookRegistry::register(
			'TemplateManager::fetch',
			array($this, 'callbackAddReviewerOptingField')
		);
		// used for submitting the form
		HookRegistry::register(
			'reviewerreviewstep3form::readuservars',
			array($this, 'callbackReadOptIn')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::execute',
			array($this, 'callbackStep3execute')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::saveForLater',
			array($this, 'callbackStep3saveForLater')
		);
	}


	/**
	 * Callback for reviewerreviewstep3form::initData (called via PKPReviewerReviewStep3Form::initData)
	 */
	public function callbackInitOptingData($hookName, $args): bool
	{
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$contextId = $request->getContext()->getId();
		$optingRequired = $this->userOptingRequired($contextId, $user);
		RqcDevHelper::writeToConsole("##### rqcOptingRequired = '$optingRequired'\n");
		$step3Form->setData('rqcOptingRequired', $optingRequired);

		// if a preliminary Opt In or Out is stored then preselect that value in the gui. Else preselect the empty select
		$preliminaryRqcOptIn = $this->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING);
		$rqcPreselectedOptIn = in_array($preliminaryRqcOptIn, [RQC_OPTING_STATUS_IN, RQC_OPTING_STATUS_OUT]) ?
			$preliminaryRqcOptIn : "";
		$step3Form->setData('rqcPreselectedOptIn', $rqcPreselectedOptIn);
		//RqcDevHelper::writeObjectToConsole($step3Form, "####step3Form");
		return false;
	}


	/**
	 * Callback for TemplateManager::fetch. (called via PKPReviewerReviewStep3Form::fetch)
	 */
	public function callbackAddReviewerOptingField($hookName, $args): bool
	{
		$templateMgr =& $args[0]; /* @var $templateMgr TemplateManager */
		$template =& $args[1];
		if ($template == 'reviewer/review/step3.tpl') {
			$templateMgr->assign(
				['rqcReviewerOptingChoices' => [
					''                    => 'common.chooseOne',
					RQC_OPTING_STATUS_IN  => 'plugins.generic.rqc.reviewerOptIn.choice_yes',
					RQC_OPTING_STATUS_OUT => 'plugins.generic.rqc.reviewerOptIn.choice_no',
				],
				 'rqcDescription'           => 'plugins.generic.rqc.reviewerOptIn.text'
				]);
		}
		return false;  // proceed with normal processing
	}


	/**
	 * Callback for reviewerreviewstep3form::readUserVars. (called via PKPReviewerReviewStep3Form::readUserVars)
	 */
	public function callbackReadOptIn($hookName, $args): bool
	{
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$rqcOptIn = $request->getUserVar('rqcOptIn');
		$step3Form->setData('rqcOptIn', $rqcOptIn);
		RqcDevHelper::writeToConsole("##### callbackReadOptIn read '$rqcOptIn'\n");
		return false;
	}


	/**
	 * Callback for reviewerreviewstep3form::execute. (called via PKPReviewerReviewStep3Form::execute)
	 */
	public function callbackStep3execute($hookName, $args): bool
	{
		return $this->getAndSaveOptingStatus($args, !RQC_PRELIM_OPTING);
	}

	/**
	 * Callback for reviewerreviewstep3form::saveForLater. (called via PKPReviewerReviewStep3Form::saveForLater)
	 */
	public function callbackStep3saveForLater($hookName, $args): bool
	{
		return $this->getAndSaveOptingStatus($args, RQC_PRELIM_OPTING);
	}

	/**
	 * @param $optingStatus int  enum for the opting status to be converted into a readable message
	 * @param $prelimOpting bool if false the status is "undefined" else its "preliminary opted in/out"
	 * @return string
	 */
	public function statusEnumToString(int $optingStatus, bool $prelimOpting = !RQC_PRELIM_OPTING): string
	{
		return match ($optingStatus) {
			RQC_OPTING_STATUS_IN => "Opted in",
			RQC_OPTING_STATUS_OUT => "Opted out",
			RQC_OPTING_STATUS_IN_PRELIM => (!$prelimOpting) ? "Undefined status" : "Preliminary opted in",
			RQC_OPTING_STATUS_OUT_PRELIM => (!$prelimOpting) ? "Undefined status" : "Preliminary opted out",
			default => "Undefined status",
		};
	}

	/**
	 * Whether form should show opting field.
	 * True iff opting is missing, outdated, or preliminary.
	 * @param $contextId  int the ID of the context
	 * @param $user       User the user to check (typically the logged-in user)
	 * @param $year		  string|null the year which to check
	 * @returns $status: one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function userOptingRequired(int $contextId, User $user, string $year = null): bool
	{
		if ($year == null) {
			$year = date('Y');
		}
		return $this->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING, $year) == RQC_OPTING_STATUS_UNDEFINED;
	}

	/**
	 * Retrieve valid opting status or return RQC_OPTING_STATUS_UNDEFINED.
	 * @param $contextId       int the ID of the context
	 * @param $user            User the user to check (typically the logged-in user)
	 * @param $preliminary     bool whether to return preliminary statuses (else return ...UNKNOWN then)
	 * @param $year            string|null the year which to check
	 * @returns $status: one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function getUserOptingStatus(int $contextId, User $user, bool $preliminary = !RQC_PRELIM_OPTING, string $year = null): int
	{
		if ($year == null) {
			$year = date('Y');
		}
		/** @var $rqcReviewerOptingDAO RqcReviewerOptingDAO */
		$rqcReviewerOptingDAO = DAORegistry::getDAO('RqcReviewerOptingDAO');
		$rqcReviewerOptings = $rqcReviewerOptingDAO->getReviewerOptingsForContextAndYear($contextId, $user->getId(), $year);
		/** @var $rqcReviewerOpting RqcReviewerOpting */
		$rqcReviewerOpting = $rqcReviewerOptings->next(); // just get the fist opting status as all of them store the same value for the year
		if (!$rqcReviewerOpting) {
			return RQC_OPTING_STATUS_UNDEFINED;
		}
		$optingStatus = $rqcReviewerOpting->getOptingStatus();

		if ($optingStatus == RQC_OPTING_STATUS_OUT_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_OUT : RQC_OPTING_STATUS_UNDEFINED;
		} else if ($optingStatus == RQC_OPTING_STATUS_IN_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_IN : RQC_OPTING_STATUS_UNDEFINED;
		}

		return $optingStatus;  // ...IN or ...OUT
	}

	/**
	 * Store a new opting status into the db
	 * @param $contextId     int the ID of the context
	 * @param $submission    Submission the submission
	 * @param $user          User the user to set the status (typically the logged-in user)
	 * @param $status        int RQC_OPTING_STATUS_(IN/OUT)
	 * @param $preliminary   bool whether to store status as _PRELIM
	 * @param $year          string|null the year for which the status is stored (!= null just for testing)
	 */
	public function setOptingStatus(int $contextId, Submission $submission, User $user, int $status, bool $preliminary = !RQC_PRELIM_OPTING, string $year = null): void
	{
		if ($year == null) { // just for testing
			$year = date('Y');
		}
		if (!in_array($status, [RQC_OPTING_STATUS_OUT_PRELIM, RQC_OPTING_STATUS_IN_PRELIM, RQC_OPTING_STATUS_OUT, RQC_OPTING_STATUS_IN])) {
			RqcLogger::logError("Invalid opting status $status");
			$status = RQC_OPTING_STATUS_UNDEFINED;
		}

		if ($preliminary && $status == RQC_OPTING_STATUS_OUT) {
			$status = RQC_OPTING_STATUS_OUT_PRELIM;
		} else if ($preliminary && $status == RQC_OPTING_STATUS_IN) {
			$status = RQC_OPTING_STATUS_IN_PRELIM;
		}
		$rqcReviewerOpting = new RqcReviewerOpting($contextId, $submission->getId(), $user->getId(), $status, $year);
		/** @var $rqcReviewerOptingDAO RqcReviewerOptingDAO */
		$rqcReviewerOptingDAO = DAORegistry::getDAO('RqcReviewerOptingDAO');
		$rqcReviewerOptingDAO->insertObject($rqcReviewerOpting);
	}

	/**
	 * Retrieve if the reviewer $user is opted out at the Submission $submission
	 * @param $submission  Submission the submission to check
	 * @param $user        User the user to check (typically the logged-in user)
	 * @returns bool iff the opting is preliminary, OPT_IN, _UNDEFINED or no record in the database then isOptedOut() gives false
	 */
	public function isOptedOut(Submission $submission, User $user): bool
	{
		$rqcReviewerOptingDAO = DAORegistry::getDAO('RqcReviewerOptingDAO'); /** @var $rqcReviewerOptingDAO RqcReviewerOptingDAO */
		$rqcReviewerOpting = $rqcReviewerOptingDAO->getReviewerOptingForSubmission($submission->getId(), $user->getId()) ;  /** @var $rqcReviewerOpting RqcReviewerOpting */
		if ($rqcReviewerOpting == null) { // shouldn't happen but failsafe
			return false;
		}
		return ($rqcReviewerOpting->getOptingStatus() == RQC_OPTING_STATUS_OUT);
	}

	/**
	 * gets the opting status from the form and stores it into the db
	 * @param array $args        must contain the form object as the first element
	 * @param bool  $preliminary if the whole opting status should be "savedForLater" (thus preliminary) or "real" with the executeForm
	 * @return false so that the HookRegistry call can be resumed for all other functions
	 */
	public function getAndSaveOptingStatus($args, bool $preliminary): false
	{
		$step3Form =& $args[0]; /** @var ReviewerReviewStep3Form $step3Form */
		$submission = $step3Form->getReviewerSubmission();
		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$contextId = $request->getContext()->getId();
		$userOptingRequired = $this->userOptingRequired($contextId, $user);
		RqcDevHelper::writeToConsole("##### callbackStep3execute: optingRequired=$userOptingRequired\n");
		$preliminaryMessage = $preliminary ? "(preliminary)" : "";
		$rqcOptIn = $step3Form->getData('rqcOptIn');
		if (!$userOptingRequired) { // get the opting status from other submissions in the same context in the same year and store to the new submission
			$rqcOptIn = $this->getUserOptingStatus($contextId, $user);
			RqcDevHelper::writeToConsole("##### callbackStep3execute: reuse the $preliminaryMessage opting status rqcOptIn=$rqcOptIn\n");
		} else {
			if ($rqcOptIn == null) {
				return false;
			}
			$previousYear = (string) (((int) date('Y')) - 1);
			$previousRqcOptIn = $this->getUserOptingStatus($contextId, $user, $preliminary, $previousYear);
			RqcDevHelper::writeToConsole("##### callbackStep3execute: previous $preliminaryMessage rqcOptIn=$previousRqcOptIn\n");
			RqcLogger::logInfo("Stored a new $preliminaryMessage RQC opting status for user with ID " . $user->getId() .
				" for context $contextId: rqcOptIn=" . $this->statusEnumToString($rqcOptIn, $preliminary) .
				" (representation: $rqcOptIn) previous $preliminaryMessage rqcOptIn=" . $this->statusEnumToString($previousRqcOptIn, $preliminary));
		}
		$rqcReviewerOptingDAO = DAORegistry::getDAO('RqcReviewerOptingDAO'); /** @var $rqcReviewerOptingDAO RqcReviewerOptingDAO */
		$rqcReviewerOpting = $rqcReviewerOptingDAO->getReviewerOptingForSubmission($submission->getId(), $user->getId()) ;  /** @var $rqcReviewerOpting RqcReviewerOpting */
		if ($rqcReviewerOpting != null) { // shouldn't happen but failsafe because other assumptions would be broken then
			return false;
		}
		$this->setOptingStatus($contextId, $submission, $user, $rqcOptIn, $preliminary); // for the current year
		return false;
	}
}
