<?php

import('lib.pkp.classes.plugins.HookRegistry');
import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.db.DAOResultFactory');
import('lib.pkp.classes.core.DataObject');

import('plugins.generic.rqc.classes.RqcReviewerOpting.RqcReviewerOpting');
import('plugins.generic.rqc.classes.RqcDevHelper');


/**
 * Operations for retrieving and modifying RqcReviewerObjects objects.
 *
 * @see     RqcReviewerOpting
 * @see     rqcReviewerOpting.json
 * @ingroup plugins_generic_rqc
 */
class RqcReviewerOptingDAO extends SchemaDAO
{
	/** @copydoc SchemaDAO::$schemaName */
	public $schemaName = 'rqcReviewerOpting';

	/** @copydoc SchemaDAO::$tableName */
	public $tableName = 'rqc_reviewer_opting';

	/** @copydoc SchemaDAO::$settingsTableName */
	// create the settings table (even if it will be empty for sure) because its to big of an error source to not have it
	public $settingsTableName = 'rqc_reviewer_opting_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	public $primaryKeyColumn = 'rqc_reviewer_opting_id';

	/** @var array Maps schema properties for the primary table to their column names */
	public $primaryTableColumns = [
		'id' => 'rqc_reviewer_opting_id',
		'contextId' => 'context_id',
		'submissionId' => 'submission_id',
		'userId' => 'user_id',
		'optingStatus' => 'opting_status',
		'year' => 'year',
	];

	/**
	 * Create a new DataObject of the appropriate class
	 */
	public function newDataObject(): RqcReviewerOpting
	{
		return new RqcReviewerOpting();
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
	 * Add schema rqcReviewerOpting into the service so that the database ops can be done.
	 * This is needed because the schema is in the plugins folder. The service only searches at the two common places for the ojs schemas
	 * (Needed for SchemaDAO-backed entities only.)
	 * @param $hookName string `Schema::get::rqcReviewerOpting`
	 * @param $params   array
	 * @return bool
	 * @see PKPSchemaService::get()
	 */
	public function callbackInsertSchema(string $hookName, array $params): bool
	{
		$schema =& $params[0]; // calculations affect the $schema variable in the service
		$schemaFile = sprintf('%s/plugins/generic/rqc/schemas/%s.json', BASE_SYS_DIR, $this->schemaName);
		$schema = json_decode(file_get_contents($schemaFile));
		// RqcDevHelper::writeObjectToConsole($schemaFile, "schemaFile ");
		// RqcDevHelper::writeObjectToConsole($schema, "schema ");
		return false;
	}

	/**
	 * Return a RqcReviewerOpting from a result row
	 *
	 * @param $primaryRow array The result row from the primary table lookup
	 */
	public function _fromRow($primaryRow): RqcReviewerOpting
	{
		$rqcReviewerOpting = $this->newDataObject();
		$rqcReviewerOpting->setId((int)$primaryRow['rqc_reviewer_opting_id']);
		$rqcReviewerOpting->setSubmissionId((int)$primaryRow['context_id']);
		$rqcReviewerOpting->setContextId((int)$primaryRow['submission_id']);
		$rqcReviewerOpting->setUserId((int)$primaryRow['user_id']);
		$rqcReviewerOpting->setOptingStatus((int)$primaryRow['opting_status']);
		$rqcReviewerOpting->setYear((int)$primaryRow['year']);
		return $rqcReviewerOpting;
	}

	/**
	 * Retrieve all reviewer optings by context id, user id and year.
	 * @param $contextId int
	 * @param $userId    int
	 * @param $year      int
	 * @return DAOResultFactory
	 */
	public function getReviewerOptingsForContextAndYear(int $contextId, int $userId, int $year): DAOResultFactory
	{
		$result = $this->retrieve(
			'SELECT	* FROM ' . $this->tableName .
			' WHERE (context_id = ?) AND (user_id = ?) AND (year = ?)
		  	ORDER BY year DESC', // most current year first
			array(
				$contextId, $userId, $year
			)
		);
		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve a reviewer opting by submission id and user id.
	 * @param $submissionId int
	 * @param $userId       int
	 * @return RqcReviewerOpting|null
	 */
	public function getReviewerOptingForSubmission(int $submissionId, int $userId): RqcReviewerOpting|null
	{
		$result = $this->retrieve(
			'SELECT	* FROM ' . $this->tableName .
			' WHERE (submission_id = ?) AND (user_id = ?)',
			array(
				$submissionId, $userId
			)
		);
		$results = (new DAOResultFactory($result, $this, '_fromRow'))->toArray();
		if ($results == null | count($results) == 0) {
			return null;
		}
		return $results[0]; // there can be just one or no reviewerOptings for a submission-user-pair
	}
}
