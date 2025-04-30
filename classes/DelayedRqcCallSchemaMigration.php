<?php

namespace APP\plugins\generic\rqc;

use APP\core\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use APP\plugins\generic\rqc\RqcDevHelper;
use APP\plugins\generic\rqc\RqcLogger;


/**
 * Generate database table structures
 *
 * @see     DelayedRqcCallDAO
 * @see     DelayedRqcCall
 * @see     rqcDelayedCall.json
 * @ingroup plugins_generic_rqc
 */
class DelayedRqcCallSchemaMigration extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('rqc_delayed_calls', function (Blueprint $table) {
			$table->bigIncrements('rqc_delayed_call_id');
			$table->bigInteger('submission_id');
			$table->bigInteger('context_id');
			$table->timestamp('last_try_ts')->nullable();
			$table->timestamp('original_try_ts');
			$table->tinyInteger('remaining_retries'); // does only need value between 10 and 0
			$table->index(['last_try_ts'], 'rqc_delayed_calls_last_try_ts'); // for querying the queue ORDER BY
			$table->foreign('context_id', 'rqc_delayed_calls_context_id')
                ->references(Application::getContextDAO()->primaryKeyColumn)
                ->on(Application::getContextDAO()->tableName)
                ->onDelete('cascade');
            $table->foreign('submission_id', 'rqc_delayed_calls_submission_id')
                ->references('submission_id')
                ->on('submissions')
                ->onDelete('cascade');
		});
		RqcLogger::logInfo("Created table 'rqc_delayed_calls' in the database");

		// empty settings table, but necessary for SchemaDAO
        Schema::create('rqc_delayed_calls_settings', function (Blueprint $table) {
			$table->bigIncrements('rqc_delayed_call_id');
		});
		RqcLogger::logInfo("Created table 'rqc_delayed_calls_settings' in the database");
	}

	/**
	 * Reverse the migration.
	 */
	public function down(): void
	{
        Schema::drop('rqc_delayed_calls');
        Schema::drop('rqc_delayed_calls_settings');
		RqcLogger::logInfo("Dropped table 'rqc_delayed_calls' in the database");
		RqcLogger::logInfo("Dropped table 'rqc_delayed_calls_settings' in the database");
	}
}
