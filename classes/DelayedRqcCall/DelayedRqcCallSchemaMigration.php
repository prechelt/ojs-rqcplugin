<?php

namespace APP\plugins\generic\rqc\classes\DelayedRqcCall;

use APP\core\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use APP\plugins\generic\rqc\classes\RqcLogger;


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
        Schema::create('rqc_delayed_call_settings', function (Blueprint $table) {
			$table->bigIncrements('rqc_delayed_call_id');
            $table->foreign('rqc_delayed_call_id', 'rqc_delayed_call_settings_rqc_delayed_call_id')
                ->references('rqc_delayed_call_id')
                ->on('rqc_delayed_calls')
                ->onDelete('cascade');
		});
		RqcLogger::logInfo("Created table 'rqc_delayed_call_settings' in the database");
	}

	/**
	 * Reverse the migration.
	 */
	public function down(): void
	{
        Schema::drop('rqc_delayed_call_settings');
        RqcLogger::logInfo("Dropped table 'rqc_delayed_call_settings' in the database");
        Schema::drop('rqc_delayed_calls');
		RqcLogger::logInfo("Dropped table 'rqc_delayed_calls' in the database");
	}
}
