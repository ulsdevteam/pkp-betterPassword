<?php

/**
 * @file classes/migration/BetterPasswordSchemaMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BetterPasswordSchemaMigration
 * @brief Describe database table structures.
 */
namespace APP\plugins\generic\betterPassword;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BetterPasswordSchemaMigration extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		Schema::create('badpw_failedlogins', function (Blueprint $table) {
			$table->string('username', 32);
			$table->bigInteger('count');
			$table->datetime('failed_login_time');
		});

		Schema::create('stored_passwords', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->integer('user_id');
			$table->text('password');
			$table->datetime('last_change_time');
		});
	}

	public function down(): void {
		Schema::drop('badpw_failedlogins');
		Schema::drop('stored_passwords');
	}
}