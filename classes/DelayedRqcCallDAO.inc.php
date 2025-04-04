<?php

/**
 * @file    plugins/generic/rqc/classes/DelayedRqcCallDAO.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class   DelayedRqcCallDAO
 * @see     DelayedRqcCallSender
 * @see     rqcDelayedCall.json
 * @ingroup plugins_generic_rqc
 *
 * @brief   Operations for retrieving and modifying DelayedRqcCall objects.
 */


/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use \PKP\db\DAO;
use \PKP\db\DAOResultFactory;
*/
import('lib.pkp.classes.db.DAO');
import('plugins.generic.rqc.classes.DelayedRqcCall');
import('lib.pkp.classes.db.SchemaDAO');

class DelayedRqcCallDAO extends SchemaDAO
{
	use RqcDevHelper;

	/** @copydoc SchemaDAO::$schemaName */
	public $schemaName = 'rqcDelayedCall';

	/** @copydoc SchemaDAO::$tableName */
	public $tableName = 'rqc_delayed_calls';

	/** @copydoc SchemaDAO::$settingsTableName */
	public $settingsTableName = 'rqc_delayed_calls_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	public $primaryKeyColumn = 'rqc_delayed_call_id';

	/** @var array Maps schema properties for the primary table to their column names */
	public $primaryTableColumns = [
		'id'               => 'rqc_delayed_call_id',
		'submissionId'     => 'submission_id',
		'contextId'        => 'context_id',
		'lastTryTs'        => 'last_try_ts',
		'originalTryTs'    => 'original_try_ts',
		'remainingRetries' => 'remaining_retries',
	];

	/**
	 * Create a new DataObject of the appropriate class
	 */
	public function newDataObject(): DelayedRqcCall
	{
		return new DelayedRqcCall();
	}

	public function __construct()
	{
		// used to inject the schema into the SchemaDAO
		HookRegistry::register(
			'Schema::get::' . $this->schemaName,
			array($this, 'callbackInsertSchema')
		);
		parent::__construct();
	}

	/**
	 * Callback for PKPSchemaService::get. (called via SchemaDAO::_getPrimaryDbProps)
	 * Add schema rqcDelayedCall into the service so that the database ops can be done.
	 * This is needed because the schema is in the plugins folder. The service only searches at the two common places for the ojs schemas
	 * (Needed for SchemaDAO-backed entities only.)
	 * @param $hookName string `Schema::get::delayedRqcCall`
	 * @param $params   array
	 * @return bool
	 * @see PKPSchemaService::get()
	 */
	public function callbackInsertSchema(string $hookName, array $params): bool
	{
		$schema =& $params[0]; // calculations affect the $schema variable in the service
		$schemaFile = sprintf('%s/plugins/generic/rqc/schemas/%s.json', BASE_SYS_DIR, $this->schemaName);
		$schema = json_decode(file_get_contents($schemaFile));
		//$this->_print("\nschemaFile ".print_r($schemaFile, true)."\n");
		//$this->_print("\nschema ".print_r($schema, true)."\n");
		return false;
	}

	/**
	 * Retrieve a reviewer submission by submission ID.
	 * @param $contextId int  which calls to get, or 0 for all calls
	 * @param $horizon   int|null  unix timestamp. Get all calls not retried since this time.
	 *                   Defaults to 23.8 hours ago (so that it's not always retried at the same time)
	 * @return DAOResultFactory
	 */
	function getCallsToRetry(int $contextId = 0, int $horizon = null): DAOResultFactory
	{
		if (is_null($horizon)) {
			$horizon = time() - 23 * 3600 - 48 * 60;  // 23.8 hours ago
		}
		$result = $this->retrieve(
			'SELECT	* FROM ' . $this->tableName .
			' WHERE (context_id = ? OR ? = 0) AND
			      (last_try_ts < ? OR last_try_ts IS NULL)
		  	ORDER BY last_try_ts ASC', // this makes it a queue
			array(
				$contextId, $contextId,
				$this->datetimeToDB($horizon)
			)
		);
		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Return a DelayedRqcCall from a result row
	 *
	 * @param $primaryRow array The result row from the primary table lookup
	 */
	function _fromRow($primaryRow): DelayedRqcCall
	{
		$delayedRqcCall = $this->newDataObject();
		$delayedRqcCall->setId((int)$primaryRow['rqc_delayed_call_id']);
		$delayedRqcCall->setSubmissionId((int)$primaryRow['submission_id']);
		$delayedRqcCall->setContextId((int)$primaryRow['context_id']);
		$delayedRqcCall->setLastTryTs($this->datetimeFromDB($primaryRow['last_try_ts']));
		$delayedRqcCall->setOriginalTryTs($this->datetimeFromDB($primaryRow['original_try_ts']));
		$delayedRqcCall->setRemainingRetries((int)$primaryRow['remaining_retries']);
		return $delayedRqcCall;
	}

	/**
	 * Update an existing review submission,
	 * usually by decreasing remainingRetries and setting lastTryTs to current time.
	 * @param $call DelayedRqcCall|DataObject one entry from rqc_delayed_calls
	 * @param null $remainingRetries optional setting this value instead of decreasing by one
	 * @param null $now optional setting this time instead of using time()
	 */
	function updateCall(DelayedRqcCall|DataObject $call, $remainingRetries = null, $now = null): void
	{
		if (is_null($remainingRetries)) {
			$remainingRetries = $call->getRemainingRetries() - 1;
		}
		if ($remainingRetries <= 0) { // no need to queue the call again
			$this->deleteById($call->getId());
		} else {
			if (is_null($now)) {
				$now = time();
			}
			$call->setRemainingRetries($remainingRetries);
			$call->setLastTryTs(Core::getCurrentDate($now));
			$this->updateObject($call);
		}
	}
}
