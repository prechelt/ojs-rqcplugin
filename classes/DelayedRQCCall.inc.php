<?php

class DelayedRQCCall extends DataObject
{
	/**
	 * Set ID of context.
	 */
//	function setContextId(int $contextId): void
//	{
//		$this->setData('contextId', $contextId);
//	}

	/**
	 * Get ID of context
	 */
//	function getContextId() : int
//	{
//		return $this->getData('contextId');
//	}

	/**
	 * Set Unix timestamp of the latest retry of the call.
	 */
	function setLastTryTS(int | null $lastTryTS): void
	{
		$this->setData('lastTryTS', $lastTryTS);
	}

	/**
	 * Get Unix timestamp of the latest retry of the call.
	 */
	function getLastTryTS() : int | null
	{
		return $this->getData('lastTryTS');
	}

	/**
	 * Set Unix timestamp of the original call.
	 */
	function setOriginalTryTS(int $originalTryTS): void
	{
		$this->setData('originalTryTS', $originalTryTS);
	}

	/**
	 * Get Unix timestamp of the original call.
	 */
	function getOriginalTryTS() : int
	{
		return $this->getData('originalTryTS');
	}

	/**
	 * Set number of retries.
	 */
	function setRetries(int $retries): void
	{
		$this->setData('retries', $retries);
	}

	/**
	 * Get number of retries.
	 */
	function getRetries() : int
	{
		return $this->getData('retries');
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
