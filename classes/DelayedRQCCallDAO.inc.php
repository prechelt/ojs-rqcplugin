<?php

/**
 * @file plugins/generic/rqc/classes/DelyedRQCCallsDAO.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class DelayedRQCCallsDAO
 * @see DelayedRQCCallsTask
 *
 * @brief Operations for retrieving and modifying DelayedRQCCall arrays/objects.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use \PKP\db\DAO;
use \PKP\db\DAOResultFactory;
*/
import('lib.pkp.classes.db.DAO');
import('plugins.generic.rqc.classes.DelayedRQCCall');

// TODO 2: use SchemaDAO  https://docs.pkp.sfu.ca/dev/documentation/en/architecture-database . A maybe from @Prechelt as he did look into the system
class DelayedRQCCallDAO extends SchemaDAO {
	use RqcDevHelper;

	/** @copydoc SchemaDAO::$schemaName */
	var $schemaName = 'delayedRqcCall';

	/** @copydoc SchemaDAO::$tableName */
	var $tableName = 'delayed_rqc_call';

	/** @copydoc SchemaDAO::$settingsTableName */
	var $settingsTableName = ''; // TODO 2: Empty or _settings?

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	var $primaryKeyColumn = 'delayed_rqc_call_id';

	/** @var array Maps schema properties for the primary table to their column names */
	var $primaryTableColumns = [
		'id' => 'delayed_rqc_call_id',
		//'contextId' => 'context_id',
		'submissionId' => 'submission_id',
		'lastTryTS' => 'last_try_ts',
		'originalTryTS' => 'original_try_ts',
		'retries' => 'retries',
	];

	/**
	 * Create a new DataObject of the appropriate class
	 *
	 * @return DataObject
	 */
	public function newDataObject() {
		return new DelayedRQCCall();
	}

	/**
	 * Retrieve a reviewer submission by submission ID.
	 * @param $journalId int  which calls to get, or 0 for all calls
	 * @param $horizon int  unix timestamp. Get all calls not retried since this time.
	 * 				Defaults to 23.8 hours ago. // TODO Q: Why 23.8 hours? Question @Prechelt
	 * @return DAOResultFactory
	 */
	function getCallsToRetry($journalId = 0, $horizon = null) : array
	{
		if (is_null($horizon)) {
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
	function _fromRow($row)
	{
		return $row;
	}

	/**
	 * Update an existing review submission,
	 * usually by increasing retries and setting last_try_ts to current time.
	 * @param $row array one entry from rqc_delayed_calls
	 */
	function updateCall($row, $retries = null, $now = null)
	{
		if (is_null($retries)) {
			$retries = $row['retries'] + 1;
		}
		if ($retries > RQCCALL_MAX_RETRIES) { // no need to queue the call again
			return $this->deleteById($row['call_id']);
		}
		if (is_null($now)) {
			$now = time();
		}
		parent::updateObject($row);
		/*$affectedRows = $this->update(
			'UPDATE rqc_delayed_calls
			SET	retries = ?,
				last_try_ts = ?
			WHERE call_id = ?',
			array(
				$retries,
				$now,
				$row['call_id'],
			)
		);*/
		// TODO Q: Should I do something with the $affectedRows?
	}

	/**
	 * Delete a delayed call entry by its ID.
	 * @param $callId int  ID of one entry from rqc_delayed_calls
	 */
	/*function deleteById($callId)
	{
		return $this->update(
			'DELETE FROM rqc_delayed_calls WHERE call_id = ?',
			array($callId)
		);*/
		// TODO Q: Should I do something with the $affectedRows?
		/*
		 * $affectedRows = $delayedCallsDao->deleteById($call['call_id']);
						if ($affectedRows == 0) {  // TODO 2: return gives the value of affected rows => 0 if it didn't work. What to do then?
							$this->_print("ERROR: Didn't delete delayed call.");
						} else if ($affectedRows > 1) {
							$this->_print("ERROR: Did affect more than one delayed call.");
						}
		 */
	//}

	/**
	 * Store a new delayed call
	 */
	/*function insertObject($request, $contextId, $submissionId)
	{
		$this->update(
			'INSERT INTO rqc_delayed_calls ()
			SET	retries = ?,
				last_try_ts = ?
			WHERE call_id = ?',
			array(
				$retries,
				$now,
				$row['call_id'],
			)
		);
		$this->update(
			'INSERT INTO oai_resumption_tokens (token, record_offset, params, expire)
			VALUES
			(?, ?, ?, ?)',
			[$token->id, $token->offset, serialize($token->params), $token->expire]
		);
		// TODO Q: Should I do something with the $affectedRows?
		//$this->_print("\n".print_r($request, true)."\n");
		//$this->_print("\n".print_r($contextId, true)."\n");
		$this->_print("\n".print_r($submissionId, true)."\n");
	}*/
}

?>
