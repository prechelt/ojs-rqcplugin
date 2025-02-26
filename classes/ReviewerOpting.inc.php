<?php

/**
 * @file plugins/generic/rqc/classes/ReviewerOpting.inc.php
 *
 * Copyright (c) 2022-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class ReviewerOpting
 * @ingroup plugins_generic_rqc
 *
 * @brief Store or query the opt-in/opt-out status of a user.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\plugins\Hook;
use PKP\user\User;
*/
import('lib.pkp.classes.plugins.HookRegistry');
import('plugins.generic.rqc.RqcPlugin');
import('plugins.generic.rqc.classes.RqcDevHelper');

define('RQC_OPTING_STATUS_IN',  36);  // internal, external
define('RQC_OPTING_STATUS_OUT', 35);  // internal, external
define('RQC_OPTING_STATUS_IN_PRELIM',  32);  // internal
define('RQC_OPTING_STATUS_OUT_PRELIM', 31);  // internal
define('RQC_OPTING_STATUS_UNDEFINED',  30);  // external only
define('RQC_PRELIM_OPTING',  true);  // for readability

/**
 * Handle the opt-in/opt-out status of a user.
 * setStatus/getStatus/optingRequired manage the status in two user settings fields.
 * reviewerRecommendations.tpl adds an opting selection field into ReviewerReviewStep3Form.
 * callbackAddReviewerOptingField injects the selection values.
 * callbackInitOptingData (for GET) injects the current opting value and a flag for showing/not showing the field.
 * callbackReadOptIn (for POST) moves the opting value from request to form.
 * callbackStep3execute (for POST) stores opting value into DB.
 * The latter three hook into ReviewerReviewStep3Form.
 * TODO 3: Hook into ReviewerReviewStep3Form::saveForLater(), but no such hook exists as of 2022-09.
 */
class ReviewerOpting extends RqcDevHelper {
	static string $datename = 'rqc_opting_date';
	static string $statusname = 'rqc_opting_status';

	/**
	 * Register callbacks. This is to be called from the plugin's register().
	 */
	public function register() {
		HookRegistry::register(
			'TemplateManager::fetch',
			array($this, 'callbackAddReviewerOptingField')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::initdata',
			array($this, 'callbackInitOptingData')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::readuservars',
			array($this, 'callbackReadOptIn')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::execute',
			array($this, 'callbackStep3execute')
		);
		// $this->_print(">>>>>>" . json_encode(array_keys(HookRegistry::getHooks())) . "<<<<<<\n");
	}

	/**
	 * Callback for TemplateManager::display.
	 */
	public function callbackAddReviewerOptingField($hookName, $args): bool {
		$templateMgr =& $args[0];  /* @var $templateMgr TemplateManager */
		$template =& $args[1];
		if ($template == 'reviewer/review/step3.tpl') {
			$templateMgr->assign(
				['rqcReviewerOptingChoices' => [
					'' => 'common.chooseOne',
					RQC_OPTING_STATUS_IN => 'plugins.generic.rqc.reviewerOptIn.choice_yes',
					RQC_OPTING_STATUS_OUT => 'plugins.generic.rqc.reviewerOptIn.choice_no',
				]]);
		}
		return false;  // proceed with normal processing
	}


	/**
	 * Callback for reviewerreviewstep3form::initData.
	 */
	public function callbackInitOptingData($hookName, $args): bool {
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$contextId = $request->getContext()->getId();
		$optingRequired = $this->optingRequired($contextId, $user);
		$this->_print("##### rqcOptingRequired = '$optingRequired'\n");
		$step3Form->setData('rqcOptingRequired', $optingRequired);
		$step3Form->setData('rqcOptIn', '');
		return false;
	}


	/**
	 * Callback for reviewerreviewstep3form::readUserVars.
	 */
	public function callbackReadOptIn($hookName, $args): bool {
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$rqcOptIn = $request->getUserVar('rqcOptIn');
		$step3Form->setData('rqcOptIn', $rqcOptIn);
		$this->_print("##### callbackReadOptIn read '$rqcOptIn'\n");
		return false;
	}


	/**
	 * Callback for reviewerreviewstep3form::execute.
	 */
	public function callbackStep3execute($hookName, $args): bool {
		// callbacks are called last in PKPReviewerReviewStep3Form::execute()
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$contextId = $request->getContext()->getId();
		$optingRequired = $this->optingRequired($contextId, $user);
		$this->_print("##### callbackStep3execute: optingRequired=$optingRequired\n");
		if (!$optingRequired) return false;  // nothing to do because form field was not shown
		$rqcOptIn = $step3Form->getData('rqcOptIn');
		$this->setStatus($contextId, $user, $rqcOptIn); // TODO 1 if $rqcOptIn was not selected => reject the value (like an validator) and add an if so that the program doesn't crash
		$this->_print("##### callbackStep3execute stored rqcOptIn=$rqcOptIn\n");
		return false;
	}


	/**
	 * Whether form should show opting field.
	 * True iff opting is missing, outdated, or preliminary.
	 * @param $context: context ID
	 * @param $user:	user
	 * @returns $status:  one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function optingRequired(int $contextId, User $user): bool {
		return $this->getStatus($contextId, $user, false) == RQC_OPTING_STATUS_UNDEFINED;
	}

	/**
	 * Retrieve valid opting status or return RQC_OPTING_STATUS_UNDEFINED.
	 * @param $context: context ID
	 * @param $user:	typically the logged-in user
	 * @param $preliminary:	whether to return preliminary statusses (else return ...UNKNOWN then)
	 * @returns $status:  one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function getStatus(int $contextId, User $user, bool $preliminary=!RQC_PRELIM_OPTING): int {
		$optingDate = $user->getSetting(self::$datename, $contextId);
		if ($optingDate == null) {
			return RQC_OPTING_STATUS_UNDEFINED;  // no opting entry found at all
		}
		$currentyear = (int)substr(gmdate("Y-m-d"), 0, 4);
		$statusyear = (int)substr($optingDate, 0, 4);
		if ($currentyear > $statusyear) {
			return RQC_OPTING_STATUS_UNDEFINED;  // opting entry is outdated
		}
		$optingStatus = $user->getSetting(self::$statusname, $contextId);  // one of ...IN/OUT/IN_PRELIM/OUT_PRELIM
		if ($optingStatus == RQC_OPTING_STATUS_OUT_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_OUT : RQC_OPTING_STATUS_UNDEFINED;
		}
		elseif ($optingStatus == RQC_OPTING_STATUS_IN_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_IN : RQC_OPTING_STATUS_UNDEFINED;
		}
		return $optingStatus;  // ...IN or ...OUT
	}

	/**
	 * Store timestamped opting status into the DB for this user and journal.
	 * @param $context: context ID
	 * @param $user:	user ID
	 * @param $status:  RQC_OPTING_STATUS_(IN/OUT)
	 * @param $preliminary:	whether to store status as _PRELIM

	 */
	public function setStatus(int $contextId, User $user, int $status, bool $preliminary=!RQC_PRELIM_OPTING) {
		if($status != RQC_OPTING_STATUS_IN and $status != RQC_OPTING_STATUS_OUT) {
			trigger_error("Illegal opting status " . $status, E_USER_ERROR);
		}
		$user->updateSetting(self::$datename, gmdate("Y-m-d"), 'string', $contextId);
		if ($preliminary) {
			$status = ($status == RQC_OPTING_STATUS_OUT) ? RQC_OPTING_STATUS_OUT_PRELIM : RQC_OPTING_STATUS_IN_PRELIM;
		}
		$user->updateSetting(self::$statusname, $status, 'int', $contextId);
	}
}
