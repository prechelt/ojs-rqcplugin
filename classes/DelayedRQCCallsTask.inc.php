<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/DelayedRQCCalls.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DelayedRQCCalls
 * @ingroup tasks
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Class to retry failed RQC calls as a scheduled task.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

define('RQCCALL_MAX_RETRIES', 10);

class DelayedRQCCalls extends ScheduledTask {

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
		$delayedCallsDao = DAORegistry::getDAO('DelayedRQCCallsDAO');
		$all_delayed_calls_to_be_retried_now = $delayedCallsDao->getCallsToRetry();
		foreach ($all_delayed_calls_to_be_retried_now as $call) {
			if ($call['retries'] >= RQCCALL_MAX_RETRIES) {  // throw away!
				$delayedCallsDao->deleteById($call['call_id']);
			}
			else {  // try again:
				// retry!!!
				$delayedCallsDao->updateCall($call);
			}
		}
		/* Pseudo code:
		   grab all delayed calls;
		   foreach delayed call:
		       if more days old than retry counter suggests:
		           retry
		           if successful:
		               delete;
		           else:
		               increase retry counter;
		               store;
		        else:
		           skip;
		*/
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
