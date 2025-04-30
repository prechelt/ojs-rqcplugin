<?php

namespace APP\plugins\generic\rqc;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\plugins\Hook;
use PKP\user\User;

use APP\plugins\generic\rqc\RqcLogger;
use APP\plugins\generic\rqc\RqcDevHelper;
use APP\plugins\generic\rqc\RqcPlugin;

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
		Hook::add(
			'reviewerreviewstep3form::initdata',
			array($this, 'callbackInitOptingData')
		);
		Hook::add(
			'TemplateManager::fetch',
			array($this, 'callbackAddReviewerOptingField')
		);
		// used for submitting the form
		Hook::add(
			'reviewerreviewstep3form::readuservars',
			array($this, 'callbackReadOptIn')
		);
		Hook::add(
			'reviewerreviewstep3form::execute',
			array($this, 'callbackStep3execute')
		);
		Hook::add(
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
		$optingRequired = $this->optingRequired($contextId, $user);
		RqcDevHelper::writeToConsole("##### rqcOptingRequired = '$optingRequired'\n");
		$step3Form->setData('rqcOptingRequired', $optingRequired);

		// if a preliminary Opt In or Out is stored then preselect that value in the gui. Else preselect the empty select
		$preliminaryRqcOptIn = $this->getStatus($contextId, $user, RQC_PRELIM_OPTING);
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
	 * @param bool $optingStatus enum for the opting status to be converted into a readable message
	 * @param bool $prelimOpting if false the status is "undefined" else its "preliminary opted in/out"
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
	public function optingRequired(int $contextId, User $user, string $year = null): bool
	{
		if ($year == null) {
			$year = date('Y');
		}
		return $this->getStatus($contextId, $user, !RQC_PRELIM_OPTING, $year) == RQC_OPTING_STATUS_UNDEFINED;
	}

	/**
	 * Retrieve valid opting status or return RQC_OPTING_STATUS_UNDEFINED.
	 * @param $contextId       int the ID of the context
	 * @param $user            User the user to check (typically the logged-in user)
	 * @param $preliminary     bool whether to return preliminary statuses (else return ...UNKNOWN then)
	 * @param $year            string|null the year which to check
	 * @returns $status: one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function getStatus(int $contextId, User $user, bool $preliminary = !RQC_PRELIM_OPTING, string $year = null): int
	{
		if ($year == null) {
			$year = date('Y');
		}
		$optingStatus = $user->getSetting(self::$statusName . $year, $contextId); // one of ...IN/OUT/IN_PRELIM/OUT_PRELIM
		if ($optingStatus == null) {
			return RQC_OPTING_STATUS_UNDEFINED;  // no opting entry found for that year
		}
		if ($optingStatus == RQC_OPTING_STATUS_OUT_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_OUT : RQC_OPTING_STATUS_UNDEFINED;
		} else if ($optingStatus == RQC_OPTING_STATUS_IN_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_IN : RQC_OPTING_STATUS_UNDEFINED;
		}
		return $optingStatus;  // ...IN or ...OUT
	}

	/**
	 * Store opting status into the DB for this user and journal (for each year separately).
	 * @param $contextId     int the ID of the context
	 * @param $user          User the user to set the status (typically the logged-in user)
	 * @param $status        int RQC_OPTING_STATUS_(IN/OUT)
	 * @param $preliminary   bool whether to store status as _PRELIM
	 * @param $year          string|null the year for which the status is stored
	 */
	public function setStatus(int $contextId, User $user, int $status, bool $preliminary = !RQC_PRELIM_OPTING, string $year = null): void
	{
		if ($year == null) {
			$year = date('Y');
		}
		if ($status != RQC_OPTING_STATUS_IN and $status != RQC_OPTING_STATUS_OUT) {
			RqcLogger::logError("Invalid opting status $status");
		}
		if ($preliminary) {
			$status = ($status == RQC_OPTING_STATUS_OUT) ? RQC_OPTING_STATUS_OUT_PRELIM : RQC_OPTING_STATUS_IN_PRELIM;
		}
		$user->updateSetting(self::$statusName . $year, $status, 'int', $contextId);
	}

	/**
	 * gets the opting status from the form and stores it into the db
	 * @param array $args        must contain the form object as the first element
	 * @param bool  $preliminary if the whole opting status should be "savedForLater" (thus preliminary) or "real" with the executeForm
	 * @return false so that the HookRegistry call can be resumed for all other functions
	 */
	public function getAndSaveOptingStatus($args, bool $preliminary): false
	{
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$contextId = $request->getContext()->getId();
		$optingRequired = $this->optingRequired($contextId, $user);
		RqcDevHelper::writeToConsole("##### callbackStep3execute: optingRequired=$optingRequired\n");
		if (!$optingRequired)
			return false;  // nothing to do because form field was not shown
		$preliminaryMessage = $preliminary ? "(preliminary)" : "";
		$rqcOptIn = $step3Form->getData('rqcOptIn');
		$previousYear = (string) (((int) date('Y')) - 1);
		$previousRqcOptIn = $this->getStatus($contextId, $user, $preliminary, $previousYear);
		RqcDevHelper::writeToConsole("##### callbackStep3execute: previous $preliminaryMessage rqcOptIn=$previousRqcOptIn\n");
		if ($rqcOptIn) {
			$this->setStatus($contextId, $user, $rqcOptIn, $preliminary); // for the current year
			RqcLogger::logInfo("Stored a new $preliminaryMessage RQC opting status for user with ID " . $user->getId() .
				" for context $contextId: rqcOptIn=" . $this->statusEnumToString($rqcOptIn, $preliminary) .
				" (representation: $rqcOptIn) previous $preliminaryMessage rqcOptIn=" . $this->statusEnumToString($previousRqcOptIn, $preliminary));
			//RqcDevHelper::writeToConsole("##### callbackStep3execute stored rqcOptIn=$rqcOptIn\n");
		}
		return false;
	}
}
