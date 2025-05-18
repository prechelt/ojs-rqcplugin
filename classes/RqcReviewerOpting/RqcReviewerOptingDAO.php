<?php

namespace APP\plugins\generic\rqc\classes\RqcReviewerOpting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

use APP\plugins\generic\rqc\classes\RqcDevHelper;


/**
 * Operations for retrieving and modifying RqcReviewerObjects objects.
 *
 * @see     RqcReviewerOpting
 * @see     rqcReviewerOpting.json
 * @ingroup plugins_generic_rqc
 */
class RqcReviewerOptingDAO extends EntityDAO
{
    /** @copydoc SchemaDAO::$schemaName */
    public $schema = 'rqcReviewerOpting';

    /** @copydoc SchemaDAO::$tableName */
    public $table = 'rqc_reviewer_opting';

    /** @copydoc SchemaDAO::$settingsTableName */
    // create the settings table (even if it will be empty for sure) because its to big of an error source to not have it
    public $settingsTable = 'rqc_reviewer_opting_settings';

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
        Hook::add(
            'Schema::get::' . $this->schema,
            array($this, 'callbackInsertSchema')
        );
        parent::__construct(new PKPSchemaService());
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
        $schemaFile = sprintf('%s/plugins/generic/rqc/schemas/%s.json', BASE_SYS_DIR, $this->schema);
        $schema = json_decode(file_get_contents($schemaFile));
        // RqcDevHelper::writeObjectToConsole($schemaFile, "schemaFile ");
        // RqcDevHelper::writeObjectToConsole($schema, "schema ");
        return false;
    }

    /**
     * Retrieve all reviewer optings by context id, user id and year.
     * @param $contextId int
     * @param $userId    int
     * @param $year      int
     * @return LazyCollection
     */
    public function getReviewerOptingsForContextAndYear(int $contextId, int $userId, int $year): LazyCollection
    {
        $rows = DB::table($this->table)
            ->where('context_id', "=", $contextId)
            ->where('user_id', "=", $userId)
            ->where('year', "=", $year)
            ->orderByDesc('year') // this makes it a queue
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->rqc_reviewer_opting_id => $this->fromRow($row);
            }
        });
    }

    /**
     * Retrieve a reviewer opting by submission id and user id.
     * @param $submissionId int
     * @param $userId       int
     * @return RqcReviewerOpting|null
     */
    public function getReviewerOptingForSubmission(int $submissionId, int $userId): RqcReviewerOpting|null
    {
        $row = DB::table($this->table)
            ->where('submission_id', "=", $submissionId)
            ->where('user_id', "=", $userId)
            ->first(); // there can be just one or no reviewerOptings for a submission-user-pair
        return $row ? $this->fromRow($row) : null;
    }

    public function insert(RqcReviewerOpting $highlight): int
    {
        return parent::_insert($highlight);
    }

    public function update(RqcReviewerOpting $highlight): void
    {
        parent::_update($highlight);
    }

    public function delete(RqcReviewerOpting $highlight): void
    {
        parent::_delete($highlight);
    }

    public function get(int $id): ?RqcReviewerOpting
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }
}
