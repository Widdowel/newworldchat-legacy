<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCaptchaFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('captcha_failed_attempts')->default(0)->after('is_blocked');
            $table->timestamp('captcha_failed_at')->nullable()->after('captcha_failed_attempts');
            $table->timestamp('captcha_locked_until')->nullable()->after('captcha_failed_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['captcha_failed_attempts', 'captcha_failed_at', 'captcha_locked_until']);
        });
    }
}