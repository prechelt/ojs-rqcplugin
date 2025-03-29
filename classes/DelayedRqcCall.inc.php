<?php

class DelayedRqcCall extends DataObject
{
	/**
	 * Set Unix timestamp of the latest retry of the call.
	 */
	function setLastTryTs(string | null $lastTryTs): void
	{
		$this->setData('lastTryTs', $lastTryTs);
	}

	/**
	 * Get Unix timestamp of the latest retry of the call.
	 */
	function getLastTryTs() : string | null
	{
		return $this->getData('lastTryTs');
	}

	/**
	 * Set Unix timestamp of the original call.
	 */
	function setOriginalTryTs(string $originalTryTs): void
	{
		$this->setData('originalTryTs', $originalTryTs);
	}

	/**
	 * Get Unix timestamp of the original call.
	 */
	function getOriginalTryTs() : string
	{
		return $this->getData('originalTryTs');
	}

	/**
	 * Set number of remaining retries.
	 */
	function setRemainingRetries(int $remainingRetries): void
	{
		$this->setData('remainingRetries', $remainingRetries);
	}

	/**
	 * Get number of remaining retries.
	 */
	function getRemainingRetries() : int
	{
		return $this->getData('remainingRetries');
	}

	/**
	 * Set ID of submission
	 */
	function setSubmissionId(int $submissionId): void
	{
		$this->setData('submissionId', $submissionId);
	}

	/**
	 * Get ID of submission
	 */
	function getSubmissionId() : int
	{
		return $this->getData('submissionId');
	}
}
