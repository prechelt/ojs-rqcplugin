<?php

/**
 * @file StaticPagesSchemaMigration.php
 *
 * Copyright (c) 2025 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class StaticPagesSchemaMigration
 * @ingroup plugins_generic_rqc
 *
 * @brief Describe database table structures.
 */

namespace APP\plugins\generic\staticPages;

use APP\core\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// used https://github.com/pkp/staticPages as a template

class DelayedRQCCallSchemaMigration extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up()
	{
		// List of delayed rqc calls for each submission
		Schema::create('rqc_delayed_calls', function (Blueprint $table) {
			$table->bigInteger('delayed_rqc_call_id')->autoIncrement();
			$table->bigInteger('submission_id');
			$table->foreign('submission_id', 'rqc_delayed_calls_submission_id')->references(Application::getSubmissionDAO->primaryKeyColumn)->on(Application::getSubmissionDAO()::tableName)->onDelete('cascade');
			$table->dateTime('last_try_ts');
			$table->dateTime('original_try_ts');
			$table->integer('retries');
		});
	}

	/**
	 * Reverse the migration.
	 */
	public function down(): void
	{
		//Schema::drop('rqc_delayed_calls_settings');
		Schema::drop('rqc_delayed_calls');
	}
}
