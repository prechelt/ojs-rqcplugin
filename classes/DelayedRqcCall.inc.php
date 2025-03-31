<?php

class DelayedRqcCall extends DataObject
{
	/**
	 * Set Unix timestamp of the latest retry of the call.
	 * string should be of format Y-m-d H:i:s
	 */
	function setLastTryTs(string|null $lastTryTs): void
	{
		$this->setData('lastTryTs', $lastTryTs);
	}

	/**
	 * Get Unix timestamp of the latest retry of the call
	 * The given string should be of format Y-m-d H:i:s
	 */
	function getLastTryTs(): string|null
	{
		return $this->getData('lastTryTs');
	}

	/**
	 * Set Unix timestamp of the original call.
	 * string should be of format Y-m-d H:i:s
	 */
	function setOriginalTryTs(string $originalTryTs): void
	{
		$this->setData('originalTryTs', $originalTryTs);
	}

	/**
	 * Get Unix timestamp of the original call.
	 * The given string should be of format Y-m-d H:i:s
	 */
	function getOriginalTryTs(): string
	{
		return $this->getData('originalTryTs');
	}

	function setRemainingRetries(int $remainingRetries): void
	{
		$this->setData('remainingRetries', $remainingRetries);
	}

	function getRemainingRetries(): int
	{
		return $this->getData('remainingRetries');
	}

	function setSubmissionId(int $submissionId): void
	{
		$this->setData('submissionId', $submissionId);
	}

	function getSubmissionId(): int
	{
		return $this->getData('submissionId');
	}

	function setContextId(int $contextId): void
	{
		$this->setData('contextId', $contextId);
	}

	function getContextId(): int
	{
		return $this->getData('contextId');
	}
}
