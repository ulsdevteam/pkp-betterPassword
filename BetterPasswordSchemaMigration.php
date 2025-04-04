<?php

/**
 * @file classes/migration/BetterPasswordSchemaMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BetterPasswordSchemaMigration
 *
 * @brief Describe database table structures.
 */

namespace APP\plugins\generic\betterPassword;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BetterPasswordSchemaMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $con = DB::connection();
        try {
            $column = $con->getDoctrineColumn('badpw_failedlogins', 'username');
            $userNameLength = $column->getLength();
            if ($userNameLength < 255) {
                Schema::table('badpw_failedlogins', function (Blueprint $table) {
                    $table->string('username', 255)->change();
                });
            }
        } catch (\Doctrine\DBAL\Schema\Exception\ColumnDoesNotExist $e) {
        }

        Schema::create('badpw_failedlogins', function (Blueprint $table) {
            $table->string('username', 255);
            $table->bigInteger('count');
            $table->datetime('failed_login_time');
        });

        Schema::create('stored_passwords', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->text('password');
            $table->datetime('last_change_time');
        });

        $userSettings = DB::table('user_settings')
            ->where('setting_name', 'betterPasswordPlugin::lastPasswords');

        $userSettingsJoined = DB::table('user_settings as u')->where('u.setting_name', 'betterPasswordPlugin::lastPasswordUpdate')->joinSub($userSettings, 'user_settings', function (JoinClause $join) {
            $join->on('u.user_id', '=', 'user_settings.user_id');
        })->select('u.user_id', 'user_settings.setting_value as password', 'u.setting_value as last_change_time');

        $userSettingsJoined->orderBy('user_id')->lazy()->each(function ($item, $key) {
            $passwords = json_decode($item->password);
            $item->password = implode(',', $passwords);
            DB::table('stored_passwords')->insertOrIgnore([
                'user_id' => $item->user_id,
                'password' => $item->password,
                'last_change_time' => $item->last_change_time
            ]);
        });

        DB::table('user_settings')->where('setting_name', 'betterPasswordPlugin::lastPasswords')->delete();
        DB::table('user_settings')->where('setting_name', 'betterPasswordPlugin::lastPasswordUpdate')->delete();
    }

    public function down(): void
    {
        Schema::drop('badpw_failedlogins');
        Schema::drop('stored_passwords');
    }
}
