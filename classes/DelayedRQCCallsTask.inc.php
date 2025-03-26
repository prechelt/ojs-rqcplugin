<?php

/**
 * @file plugins/generic/rqc/classes/DelayedRQCCalls.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class DelayedRQCCalls
 * @ingroup tasks
 * @ingroup plugins_generic_rqc
 *
 * @brief Class to retry failed RQC calls as a scheduled task.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use PKP\db\DAORegistry;
use PKP\scheduledTask\ScheduledTask;
*/
import('lib.pkp.classes.scheduledTask.ScheduledTask');

define('RQCCALL_MAX_RETRIES', 10);

class DelayedRQCTask extends ScheduledTask {
	use RqcDevHelper;

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	function getName() {
		return __('admin.scheduledTask.delayedRQCCalls');
	}


	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	function executeActions() {
		$delayedCallDao = new DelayedRQCCallDAO(); // $delayedCallDao = DAORegistry::getDAO('DelayedRQCCallsDAO');
		$allDelayedCallsToBeRetriedNow = $delayedCallDao->getCallsToRetry(); // grab all delayed calls that should be retried now
		foreach ($allDelayedCallsToBeRetriedNow as $call) {
			if ($call['retries'] > RQCCALL_MAX_RETRIES) {  // throw away!
				$delayedCallDao->deleteById($call['call_id']);
			}
			else {  // try again:
				$callHandler = new RqccallHandler();
				$resendSuccessful = $callHandler->resend($call['request'], $call['context_id'], $call['submission_id']);
				switch ($resendSuccessful) {
					case RQC_RESEND_CANCELED: // TODO Q: What should I do?
						break;
					case RQC_RESEND_BAD_REQUEST: // TODO Q: Is that right that way?
						// TODO 2: log
					case RQC_RESEND_SUCCESS:
						$delayedCallDao->deleteById($call['call_id']);
						break;
					case RQC_RESEND_FAILURE:
					default: // TODO Q: Or what should be the default?
						$delayedCallDao->updateCall($call); // update retry-counter (and ts). If max-retries is reached: delete
						break;

				}
			}
		}
		// TODO 1: is this needed anymore?
		/**  Example code found somewhere:
		  if ($submitReminderDays>=1 && $reviewAssignment->getDateDue() != null) {
			$checkDate = strtotime($reviewAssignment->getDateDue());
			if (time() - $checkDate > 60 * 60 * 24 * $submitReminderDays) {
				$reminderType = REVIEW_REMIND_AUTO;
		*/
		return true;
	}
}

?>
