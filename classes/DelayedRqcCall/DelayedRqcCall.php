<?php

namespace APP\plugins\generic\rqc\classes\DelayedRqcCall;

use PKP\core\DataObject;

use APP\plugins\generic\rqc\classes\RqcDevHelper;


/**
 * DataObject representing the delayed call to rqc
 *
 * @see     DelayedRqcCallDAO
 * @see     DelayedRqcCallSchemaMigration
 * @see     rqcDelayedCall.json
 * @ingroup plugins_generic_rqc
 */
class DelayedRqcCall extends DataObject
{
	/**
	 * Set Unix timestamp of the latest retry of the call.
	 * string should be of format Y-m-d H:i:s
	 */
	public function setLastTryTs(string|null $lastTryTs): void
	{
		$this->setData('lastTryTs', $lastTryTs);
	}

	/**
	 * Get Unix timestamp of the latest retry of the call
	 * The given string should be of format Y-m-d H:i:s
	 */
	public function getLastTryTs(): string|null
	{
		return $this->getData('lastTryTs');
	}

	/**
	 * Set Unix timestamp of the original call.
	 * string should be of format Y-m-d H:i:s
	 */
	public function setOriginalTryTs(string $originalTryTs): void
	{
		$this->setData('originalTryTs', $originalTryTs);
	}

	/**
	 * Get Unix timestamp of the original call.
	 * The given string should be of format Y-m-d H:i:s
	 */
	public function getOriginalTryTs(): string
	{
		return $this->getData('originalTryTs');
	}

	public function setRemainingRetries(int $remainingRetries): void
	{
		$this->setData('remainingRetries', $remainingRetries);
	}

	public function getRemainingRetries(): int
	{
		return $this->getData('remainingRetries');
	}

	public function setSubmissionId(int $submissionId): void
	{
		$this->setData('submissionId', $submissionId);
	}

	public function getSubmissionId(): int
	{
		return $this->getData('submissionId');
	}

	public function setContextId(int $contextId): void
	{
		$this->setData('contextId', $contextId);
	}

	public function getContextId(): int
	{
		return $this->getData('contextId');
	}
}
