<?php

/**
 * @file     plugins/generic/rqc/classes/DelayedRqcCallSchemaMigration.inc.php
 *
 * Copyright (c) 2025 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class    DelayedRqcCallSchemaMigration
 * @see      DelayedRqcCallDAO
 * @see      DelayedRqcCall
 * @see      rqcDelayedCall.json
 * @ingroup  plugins_generic_rqc
 *
 * @brief    Generate database table structures.
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

// used https://github.com/pkp/staticPages as a template

class DelayedRqcCallSchemaMigration extends Migration
{
	use RqcDevHelper;

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

		// I have to do this: TODO 3: make pull-request so that one can have a custom table without settings via SchemaDAO
		Capsule::schema()->create('rqc_delayed_calls_settings', function (Blueprint $table) {
			$table->bigIncrements('rqc_delayed_call_id');
		});
	}

	/**
	 * Reverse the migration.
	 */
	public function down(): void
	{
		Capsule::schema()->drop('rqc_delayed_calls');
		Capsule::schema()->drop('rqc_delayed_calls_settings');
	}
}
