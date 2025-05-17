<?php

namespace APP\plugins\generic\rqc\classes\RqcReviewerOpting;

use APP\core\Application;
use APP\plugins\generic\rqc\classes\RqcLogger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use APP\plugins\generic\rqc\classes\RqcDevHelper;

/**
 * Generate database table structures
 *
 * @see     RqcReviewerOptingDAO
 * @see     RqcReviewerOpting
 * @see     rqcReviewerOpting.json
 * @ingroup plugins_generic_rqc
 */
class RqcReviewerOptingSchemaMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rqc_reviewer_opting', function (Blueprint $table) {
            $table->bigIncrements('rqc_reviewer_opting_id');
            $table->bigInteger('context_id');
            $table->foreign('context_id', 'rqc_reviewer_opting_context_id')
                ->references(Application::getContextDAO()->primaryKeyColumn)
                ->on(Application::getContextDAO()->tableName)
                ->onDelete('cascade');
            $table->bigInteger('submission_id');
            $table->foreign('submission_id', 'rqc_reviewer_opting_submission_id')
                ->references('submission_id')
                ->on('submissions')
                ->onDelete('cascade');
            $table->bigInteger('user_id');
            $table->foreign('user_id', 'rqc_reviewer_opting_user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
            $table->tinyInteger('opting_status');
            $table->tinyInteger('year');
        });
        RqcLogger::logInfo("Created table 'rqc_reviewer_opting' in the database");

        // empty settings table, but necessary for SchemaDAO
        Schema::create('rqc_reviewer_opting_settings', function (Blueprint $table) {
            $table->bigIncrements('rqc_reviewer_opting_id');
            $table->foreign('rqc_reviewer_opting_id', 'rqc_reviewer_opting_settings_rqc_reviewer_opting_id')
                ->references('rqc_reviewer_opting_id')
                ->on('rqc_reviewer_opting')
                ->onDelete('cascade');
        });
        RqcLogger::logInfo("Created table 'rqc_reviewer_opting_settings' in the database");
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('rqc_reviewer_opting');
        Schema::drop('rqc_reviewer_opting_settings');
        RqcLogger::logInfo("Dropped table 'rqc_reviewer_opting' in the database");
        RqcLogger::logInfo("Dropped table 'rqc_reviewer_opting_settings' in the database");
    }
}
