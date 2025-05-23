<?php

namespace APP\plugins\generic\rqc\classes\DelayedRqcCall;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\core\DataObject;
use PKP\core\EntityDAO;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

use APP\plugins\generic\rqc\classes\RqcDevHelper;
use APP\plugins\generic\rqc\classes\RqcLogger;


/**
 * Operations for retrieving and modifying DelayedRqcCall objects.
 *
 * @see     DelayedRqcCallSender
 * @see     rqcDelayedCall.json
 * @ingroup plugins_generic_rqc
 */
class DelayedRqcCallDAO extends EntityDAO
{
	/** @copydoc SchemaDAO::$schemaName */
	public $schema = 'rqcDelayedCall';

	/** @copydoc SchemaDAO::$tableName */
	public $table = 'rqc_delayed_calls';

	/** @copydoc SchemaDAO::$settingsTableName */
	// create the settings table (even if it will be empty for sure) because its to big of an error source to not have it
	public $settingsTable = 'rqc_delayed_call_settings';

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
		Hook::add(
			'Schema::get::' . $this->schema,
			array($this, 'callbackInsertSchema')
		);
		parent::__construct(new PKPSchemaService());
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
		$schemaFile = sprintf('%s/plugins/generic/rqc/schemas/%s.json', BASE_SYS_DIR, $this->schema);
		$schema = json_decode(file_get_contents($schemaFile));
		// RqcDevHelper::writeObjectToConsole($schemaFile, "schemaFile ");
		// RqcDevHelper::writeObjectToConsole($schema, "schema ");
		return false;
	}

	/**
	 * Retrieve a reviewer submission by submission ID.
	 * @param $contextId int  which calls to get, or 0 for all calls
	 * @param $horizon   int|null  unix timestamp. Get all calls not retried since this time.
	 *                   Defaults to 23.8 hours ago (so that it's not always retried at the same time)
	 * @return LazyCollection
	 */
	public function getCallsToRetry(int $contextId = 0, int $horizon = null): LazyCollection
	{
		if (is_null($horizon)) {
			$horizon = time() - 23 * 3600 - 48 * 60;  // 23.8 hours ago
		}
        $rows = DB::table($this->table)
            ->when($contextId != 0, fn (Builder $query) => $query->where('context_id', '=', $contextId))
            ->whereRaw('last_try_ts <= ? OR last_try_ts IS NULL', [$this->convertToDB($horizon, 'date')])
            ->orderBy('last_try_ts') // this makes it a queue
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->rqc_delayed_call_id => $this->fromRow($row);
            }
        });
	}

	/**
	 * Update an existing review submission,
	 * usually by decreasing remainingRetries and setting lastTryTs to current time.
	 * @param      $call             DelayedRqcCall|DataObject one entry from rqc_delayed_calls
	 * @param null $remainingRetries optional setting this value instead of decreasing by one
	 * @param null $now              optional setting this time instead of using time()
	 */
	public function updateCall(DelayedRqcCall|DataObject $call, $remainingRetries = null, $now = null): void
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
			$this->update($call);
		}
	}

	public function deleteCallsBySubmissionId(int $submissionId): void
	{
        $rows = DB::table($this->table)
            ->where('submission_id', "=", $submissionId)
            ->get();

        if (count($rows) != 0) {
            RqcLogger::logWarning("A delayed rqc call for submission $submissionId was already in the db. Deleted that delayed call in the queue.");
            foreach ($rows as $rqcDelayedCallObject) {
                $this->deleteById($rqcDelayedCallObject->rqc_delayed_call_id);
            }
        }
	}

    public function insert(DelayedRqcCall $highlight): int
    {
        return parent::_insert($highlight);
    }

    public function update(DelayedRqcCall $highlight): void
    {
        parent::_update($highlight);
    }

    public function delete(DelayedRqcCall $highlight): void
    {
        parent::_delete($highlight);
    }

    public function get(int $id): ?DelayedRqcCall
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }
}
