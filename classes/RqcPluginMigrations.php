<?php

namespace APP\plugins\generic\rqc\classes;

use Illuminate\Database\Migrations\Migration;

use APP\plugins\generic\rqc\classes\RqcReviewerOpting\RqcReviewerOptingSchemaMigration;
use APP\plugins\generic\rqc\classes\DelayedRqcCall\DelayedRqcCallSchemaMigration;


/**
 * Generate database table structures
 *
 * @see     RqcReviewerOptingSchemaMigration
 * @see     DelayedRqcCallSchemaMigration
 * @ingroup plugins_generic_rqc
 */
class RqcPluginMigrations extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rqcReviewerOptingSchemaMigration = new RqcReviewerOptingSchemaMigration();
        $delayedRqcCallSchemaMigration = new DelayedRqcCallSchemaMigration();
        $rqcReviewerOptingSchemaMigration->up();
        $delayedRqcCallSchemaMigration->up();
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $rqcReviewerOptingSchemaMigration = new RqcReviewerOptingSchemaMigration();
        $delayedRqcCallSchemaMigration = new DelayedRqcCallSchemaMigration();
        $rqcReviewerOptingSchemaMigration->down();
        $delayedRqcCallSchemaMigration->down();
    }
}
