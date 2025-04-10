<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

import('plugins.generic.rqc.classes.RqcLogger');
import('plugins.generic.rqc.classes.RqcDevHelper');

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
		Capsule::schema()->create('rqc_delayed_calls', function (Blueprint $table) {
			$table->bigIncrements('rqc_delayed_call_id');
			$table->bigInteger('submission_id');
			$table->bigInteger('context_id');
			$table->timestamp('last_try_ts')->nullable();
			$table->timestamp('original_try_ts');
			$table->tinyInteger('remaining_retries'); // does only need value between 10 and 0
			$table->index(['last_try_ts'], 'rqc_delayed_calls_last_try_ts'); // for querying the queue ORDER BY
			$table->foreign('submission_id')->references('submission_id')->on('submissions');
		});
		RqcLogger::logInfo("Created table 'rqc_delayed_calls' in the database");

		// there has to be a _settings table in the db (if not there are errors with some SchemaDAO-methods) TODO Forum: forum question to make pull-request so that one can have a custom table without settings via SchemaDAO
		Capsule::schema()->create('rqc_delayed_calls_settings', function (Blueprint $table) {
			$table->bigIncrements('rqc_delayed_call_id');
		});
		RqcLogger::logInfo("Created table 'rqc_delayed_calls_settings' in the database");
	}

	/**
	 * Reverse the migration.
	 */
	public function down(): void
	{
		Capsule::schema()->drop('rqc_delayed_calls');
		Capsule::schema()->drop('rqc_delayed_calls_settings');
		RqcLogger::logInfo("Dropped tables 'rqc_delayed_calls' and 'rqc_delayed_calls_settings' in the database");
	}
}
