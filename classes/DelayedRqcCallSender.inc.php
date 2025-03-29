<?php

/**
 * @file plugins/generic/rqc/classes/DelayedRqcCallSender.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class DelayedRqcCallSender
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
import('lib.pkp.classes.db.DAORegistry');
import('plugins.generic.rqc.classes.DelayedRqcCallDAO');
import('plugins.generic.rqc.classes.DelayedRqcCall');

class DelayedRqcCallSender extends ScheduledTask {
	use RqcDevHelper;

	private int $_secSleepBetweenRetries = 3; // arbitrary number to sleep between retries in the queue

	private int $_NRetriesToAbort = 3; // arbitrary number

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	function getName() : string
	{
		return __('admin.scheduledTask.delayedRqcCall');
	}


	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	function executeActions() : bool
	{
		$lastNRetriesFailed = 0;

		$delayedCallDao = DAORegistry::getDAO('DelayedRqcCallDAO'); /** @var $delayedCallDao DelayedRqcCallDAO */
		$allDelayedCallsToBeRetriedNow = $delayedCallDao->getCallsToRetry(); // grab all delayed calls that should be retried now

		foreach ($allDelayedCallsToBeRetriedNow as $call) { /** @var $call DelayedRqcCall */
			if ($call->getRemainingRetries() <= 0) {  // throw away!
				$delayedCallDao->deleteById($call->getId());
				continue;
			}

			// If some calls in a row fail we assume that some unexpected error has occurred so that all calls fail (we expect that most of the calls do make it)
			// If that assumption is false because we are "unlucky" we continue next time executeActions() is executed anyway. So no big problem
			if ($lastNRetriesFailed >= $this->_NRetriesToAbort) {
				break;
			}
			sleep($this->_secSleepBetweenRetries);

			$callHandler = new RqcCallHandler();
			$rqcResult = $callHandler->resend($call->getSubmissionId()); // try again

			switch ($rqcResult) {
				case in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_SUCESS): // resend successfully
					$delayedCallDao->deleteById($call->getId());
					$lastNRetriesFailed = 0; // reset counter
					break;
				case (in_array($rqcResult['status'], RQC_CALL_SERVER_DOWN)): // no connection to server: abort trying the next calls in the queue
					$delayedCallDao->updateCall($call);
					$lastNRetriesFailed = $this->_NRetriesToAbort;
					error_log("Delayed RQC call error: Tried to send data from submission ".$call->getSubmissionId()." originally send at ".$call->getOriginalTryTs()
						." resulted in http status code ".$rqcResult['status']." with response ".$rqcResult['response']."\n");
					break;
				case (in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_TO_RESEND)): // other errors (probably not an implementation error)
					$delayedCallDao->updateCall($call);
					$lastNRetriesFailed += 1;
					error_log("Delayed RQC call error: Tried to send data from submission ".$call->getSubmissionId()." originally send at ".$call->getOriginalTryTs()
						." resulted in http status code ".$rqcResult['status']." with response ".$rqcResult['response']."\n");
					break;
				default:	// something else went wrong (implementation error or else)
					$delayedCallDao->updateCall($call);
					$lastNRetriesFailed += 1;
					error_log("Delayed RQC call error: Tried to send (probably faulty) data from submission ".$call->getSubmissionId()." originally send at ".$call->getOriginalTryTs()
						." resulted in http status code ".$rqcResult['status']." with response ".$rqcResult['response']."\n");
					break;
			}
		}
		return true;
	}
}

?>
