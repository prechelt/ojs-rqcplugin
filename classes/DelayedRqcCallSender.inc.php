<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use PKP\db\DAORegistry;
use PKP\scheduledTask\ScheduledTask;
*/

import('lib.pkp.classes.core.Core');
import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.db.DAORegistry');
import('plugins.generic.rqc.classes.DelayedRqcCallDAO');
import('plugins.generic.rqc.classes.DelayedRqcCall');
import('plugins.generic.rqc.pages.RqcCallHandler');
import('plugins.generic.rqc.classes.RqcDevHelper');

/**
 * Class to retry failed RQC calls as a scheduled task.
 *
 * @see     RqcCallHandler
 * @see     DelayedRqcCallDAO
 * @ingroup plugins_generic_rqc
 */
class DelayedRqcCallSender extends ScheduledTask
{
	private int $_secSleepBetweenRetries = 3; // arbitrary number to sleep between retries in the queue

	private int $_NRetriesToAbort = 3; // arbitrary number

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	public function getName(): string
	{
		return __('admin.scheduledTask.delayedRqcCall');
	}


	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	public function executeActions(): bool
	{
		$lastNRetriesFailed = 0;

		$delayedRqcCallDao = new DelayedRqcCallDAO();
		$allDelayedCallsToBeRetriedNow = $delayedRqcCallDao->getCallsToRetry()->toArray(); // grab all delayed calls that should be retried now
		//RqcDevHelper::writeObjectToConsole($allDelayedCallsToBeRetriedNow, "all delayed calls");
		foreach ($allDelayedCallsToBeRetriedNow as $call) { /** @var $call DelayedRqcCall */
			if ($call->getRemainingRetries() <= 0) {  // throw away!
				$delayedRqcCallDao->deleteById($call->getId());
				continue;
			}

			// If some calls in a row fail we assume that some unexpected error has occurred so that all calls fail (we expect that most of the calls do make it)
			// If that assumption is false because we are "unlucky" we continue next time executeActions() is executed anyway. So no big problem
			if ($lastNRetriesFailed >= $this->_NRetriesToAbort) {
				$logMessage = "Abort retrying other delayed RQC call in the queue for now";
				$this->addExecutionLogEntry($logMessage, SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
				RqcLogger::logWarning($logMessage);
				break;
			}
			sleep($this->_secSleepBetweenRetries);

			$callHandler = new RqcCallHandler();
			$rqcResult = $callHandler->resend($call->getSubmissionId()); // try again

			switch ($rqcResult) {
				case in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_SUCESS): // resend successfully
					$delayedRqcCallDao->deleteById($call->getId());
					$lastNRetriesFailed = 0; // reset counter
					$logMessage = "Successfully send the delayed RQC call from from submission " . $call->getSubmissionId() . " (contextId: " . $call->getContextId() .
						") originally send at " . $call->getOriginalTryTs() . " with http status code " . $rqcResult['status'] . " and response body " . json_encode($rqcResult['response']) . "\n";
					$this->addExecutionLogEntry($logMessage, SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
					RqcLogger::logInfo($logMessage);
					break;
				case (in_array($rqcResult['status'], RQC_CALL_SERVER_DOWN)): // no connection to server: abort trying the next calls in the queue // TODO Q: Ok like that or should I delete that case (subclass of RQC_CALL_STATUS_CODES_TO_RESEND)?
					$delayedRqcCallDao->updateCall($call);
					$lastNRetriesFailed = $this->_NRetriesToAbort;
					$logMessage = "Delayed RQC call error: Tried to send data from submission " . $call->getSubmissionId() . " (contextId: " . $call->getContextId() .
						") originally send at " . $call->getOriginalTryTs() . " resulted in http status code " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . "\n";
					$this->addExecutionLogEntry($logMessage, SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
					RqcLogger::logWarning($logMessage);
					break;
				case (in_array($rqcResult['status'], RQC_CALL_STATUS_CODES_TO_RESEND)): // other errors (probably not an implementation error)
					$delayedRqcCallDao->updateCall($call);
					$lastNRetriesFailed += 1;
					$logMessage = "Delayed RQC call error: Tried to send data from submission " . $call->getSubmissionId() . " (contextId: " . $call->getContextId() .
						") originally send at " . $call->getOriginalTryTs() . " resulted in http status code " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . "\n";
					$this->addExecutionLogEntry($logMessage, SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
					RqcLogger::logWarning($logMessage);
					break;
				default:    // something else went wrong (implementation error or else)
					$delayedRqcCallDao->updateCall($call);
					$lastNRetriesFailed += 1;
					$logMessage = "Delayed RQC call error: Tried to send (probably faulty) data from submission " . $call->getSubmissionId() . " (contextId: " . $call->getContextId() .
						") originally send at " . $call->getOriginalTryTs() . " resulted in http status code " . $rqcResult['status'] . " with response body " . json_encode($rqcResult['response']) . "\nThe original post request body: " . json_encode($rqcResult['request']) . "\n";
					$this->addExecutionLogEntry($logMessage, SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
					RqcLogger::logError($logMessage);
					break;
			}
		}
		return true;
	}
}

?>
