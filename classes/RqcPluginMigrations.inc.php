<?php

use Illuminate\Database\Migrations\Migration;

import('plugins.generic.rqc.classes.RqcReviewerOpting.RqcReviewerOptingSchemaMigration');
import('plugins.generic.rqc.classes.DelayedRqcCall.DelayedRqcCallSchemaMigration');
import('plugins.generic.rqc.classes.RqcLogger');
import('plugins.generic.rqc.classes.RqcDevHelper');


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
