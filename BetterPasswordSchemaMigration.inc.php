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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class BetterPasswordSchemaMigration extends Migration {
		/**
		 * Run the migrations.
		 * @return void
		 */
        public function up() {
		// Bad Password failed login attempts
		Capsule::schema()->create('badpw_failedlogins', function (Blueprint $table) {
			$table->string('username', 32);
			$table->bigInteger('count');
			$table->datetime('failed_login_time');
		});
	}
}