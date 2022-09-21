<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/ReviewerOpting.inc.php
 *
 * Copyright (c) 2022 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class ReviewerOpting
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Store or query the opt-in/opt-out status of a user.
 */

import('plugins.generic.reviewqualitycollector.RQCPlugin');

define('RQC_OPTING_STATUS_IN',  36);  // internal, external
define('RQC_OPTING_STATUS_OUT', 35);  // internal, external
define('RQC_OPTING_STATUS_IN_PRELIM',  32);  // internal
define('RQC_OPTING_STATUS_OUT_PRELIM', 31);  // internal
define('RQC_OPTING_STATUS_UNDEFINED',  30);  // external only
define('RQC_PRELIM_OPTING',  true);  // for readability

/**
 * Store or query the opt-in/opt-out status of a user.
 */
class ReviewerOpting
{
	static string $datename = 'rqc_opting_date';
	static string $statusname = 'rqc_opting_status';

	public function __construct() {
		$this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
	}

	public function _print($msg) {
		# print to php -S console stream (to be used during development only; remove calls in final code)
		fwrite($this->stderr, $msg);
	}

	/**
	 * Register callbacks. This is to be called from the plugin's register().
	 */
	public function register() {
		HookRegistry::register(
			'TemplateManager::fetch',
			array($this, 'cb_addReviewerOptingField')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::initdata',
			array($this, 'cb_initOptingRequired')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::readuservars',
			array($this, 'cb_readOptIn')
		);
		HookRegistry::register(
			'reviewerreviewstep3form::execute',
			array($this, 'cb_step3execute')
		);
		// $this->_print(">>>>>>" . json_encode(array_keys(HookRegistry::getHooks())) . "<<<<<<\n");
	}

	/**
	 * Callback for TemplateManager::display.
	 */
	public function cb_addReviewerOptingField($hookName, $args): bool {
		$templateMgr =& $args[0];  /* @var $templateMgr TemplateManager */
		$template =& $args[1];
		if ($template == 'reviewer/review/step3.tpl') {
			$templateMgr->assign(
				['rqcReviewerOptingChoices' => [
					'' => 'common.chooseOne',
					RQC_OPTING_STATUS_IN => 'plugins.generic.reviewqualitycollector.reviewer_opt_in.choice_yes',
					RQC_OPTING_STATUS_OUT => 'plugins.generic.reviewqualitycollector.reviewer_opt_in.choice_no',
				]]);
		}
		return false;  // proceed with normal processing
	}


	/**
	 * Callback for reviewerreviewstep3form::initData.
	 */
	public function cb_initOptingRequired($hookName, $args): bool {
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
	public function cb_readOptIn($hookName, $args): bool {
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$rqcOptIn = $request->getUserVar('rqcOptIn');
		$step3Form->setData('rqcOptIn', $rqcOptIn);
		$this->_print("##### cb_readOptIn read '$rqcOptIn'\n");
		return false;
	}


	/**
	 * Callback for reviewerreviewstep3form::execute.
	 */
	public function cb_step3execute($hookName, $args): bool {
		// callbacks are called last in PKPReviewerReviewStep3Form::execute()
		$step3Form =& $args[0];
		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$contextId = $request->getContext()->getId();
		$optingRequired = $this->optingRequired($contextId, $user);
		$this->_print("##### cb_step3execute: optingRequired=$optingRequired\n");
		if (!$optingRequired) return false;  // nothing to do because form field was not shown
		$rqcOptIn = $step3Form->getData('rqcOptIn');
		$this->setStatus($contextId, $user, $rqcOptIn);
		$this->_print("##### cb_step3execute stored rqcOptIn=$rqcOptIn\n");
		return false;
	}


	/**
	 * Whether form should show opting field.
	 * True iff opting is missing, outdated, or preliminary.
	 * @param $context: context ID
	 * @param $user:	user
	 * @returns $status:  one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function optingRequired(int $context_id, User $user): bool {
		return $this->getStatus($context_id, $user, false) == RQC_OPTING_STATUS_UNDEFINED;
	}

	/**
	 * Retrieve valid opting status or return RQC_OPTING_STATUS_UNDEFINED.
	 * @param $context: context ID
	 * @param $user:	typically the logged-in user
	 * @param $preliminary:	whether to return preliminary statusses (else return ...UNKNOWN then)
	 * @returns $status:  one of RQC_OPTING_STATUS_* except *_PRELIM
	 */
	public function getStatus(int $context_id, User $user, bool $preliminary=!RQC_PRELIM_OPTING): int {
		$opting_date = $user->getSetting(self::$datename, $context_id);
		if ($opting_date == null) {
			return RQC_OPTING_STATUS_UNDEFINED;  // no opting entry found at all
		}
		$currentyear = (int)substr(gmdate("Y-m-d"), 0, 4);
		$statusyear = (int)substr($opting_date, 0, 4);
		if ($currentyear > $statusyear) {
			return RQC_OPTING_STATUS_UNDEFINED;  // opting entry is outdated
		}
		$opting_status = $user->getSetting(self::$statusname, $context_id);  // one of ...IN/OUT/IN_PRELIM/OUT_PRELIM
		if ($opting_status == RQC_OPTING_STATUS_OUT_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_OUT : RQC_OPTING_STATUS_UNDEFINED;
		}
		elseif ($opting_status == RQC_OPTING_STATUS_IN_PRELIM) {
			return $preliminary ? RQC_OPTING_STATUS_IN : RQC_OPTING_STATUS_UNDEFINED;
		}
		return $opting_status;  // ...IN or ...OUT
	}

	/**
	 * Store timestamped opting status into the DB for this user and journal.
	 * @param $context: context ID
	 * @param $user:	user ID
	 * @param $status:  RQC_OPTING_STATUS_(IN/OUT)
	 * @param $preliminary:	whether to store status as _PRELIM

	 */
	public function setStatus(int $context_id, User $user, int $status, bool $preliminary=!RQC_PRELIM_OPTING) {
		if($status != RQC_OPTING_STATUS_IN and $status != RQC_OPTING_STATUS_OUT) {
			trigger_error("Illegal opting status " . $status, E_USER_ERROR);
		}
		$user->updateSetting(self::$datename, gmdate("Y-m-d"), 'string', $context_id);
		if ($preliminary) {
			$status = ($status == RQC_OPTING_STATUS_OUT) ? RQC_OPTING_STATUS_OUT_PRELIM : RQC_OPTING_STATUS_IN_PRELIM;
		}
		$user->updateSetting(self::$statusname, $status, 'int', $context_id);
	}
}
