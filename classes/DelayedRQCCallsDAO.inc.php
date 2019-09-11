<?php

/**
 * @file plugins/generic/reviewqualitycollector/classes/DelyedRQCCallsDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DelayedRQCCallsDAO
 * @see DelayedRQCCallsTask
 *
 * @brief Operations for retrieving and modifying DelayedRQCCall arrays/objects.
 */

import('lib.pkp.classes.db.DAO');

// TODO: use SchemaDAO  https://docs.pkp.sfu.ca/dev/documentation/en/architecture-database
class DelayedRQCCallsDAO extends DAO {

	/**
	 * Retrieve a reviewer submission by submission ID.
	 * @param $journalId int  which calls to get, or 0 for all calls
	 * @param $horizon int  unix timestamp. Get all calls not retried since this time.
	 * 				Defaults to 23.8 hours ago.
	 * @return Iterator of raw row arrays
	 */
	function getCallsToRetry($journalId = 0, $horizon = null) {
		if (isNull($horizon)) {
			$horizon = time() - 23*3600 - 48*60;  // 23.8 hours ago
		}
		$result = $this->retrieve(
			'SELECT	*
			FROM rqc_delayed_calls
			WHERE (journal_id = ? OR ? = 0) AND 
			      (last_try_ts < ?)',
			array(
				$journalId, $journalId,
				$horizon
			)
		);
		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * We use returned rows as-is, there is no DelayedRQCCalls class.
	 * @param $row array
	 * @return array
	 */
	function _fromRow($row) {
		return $row;
	}

	/**
	 * Update an existing review submission,
	 * usually by increasing retries and setting last_try_ts to current time.
	 * @param $row array one entry from rqc_delayed_calls
	 */
	function updateCall($row, $retries = null, $now = null) {
		if (isNull($retries)) {
			$retries = $row['retries'] + 1;
		}
		if (isNull($now)) {
			$now = time();
		}
		$this->update(
			'UPDATE rqc_delayed_calls
			SET	retries = ?,
				last_try_ts = ?
			WHERE call_id = ?',
			array(
				$retries,
				$now,
				$row['call_id'],
			)
		);
	}

	/**
	 * Delete a delayed call entry by its ID.
	 * @param $callId int  ID of one entry from rqc_delayed_calls
	 */
	function deleteById($callId) {
		return $this->update(
			'DELETE FROM rqc_delayed_calls WHERE call_id = ?',
			array($callId)
		);
	}
}

?>
