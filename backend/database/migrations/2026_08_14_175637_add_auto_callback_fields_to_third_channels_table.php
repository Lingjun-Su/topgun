<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->boolean('auto_callback_enabled')->default(false)->after('push_retry_delay')->comment('定时回调推送开关');
            $table->string('auto_callback_cron', 20)->nullable()->after('auto_callback_enabled')->comment('定时回调Cron表达式');
            $table->string('auto_callback_time_start', 5)->nullable()->after('auto_callback_cron')->comment('定时回调开始时间(HH:MM)');
            $table->string('auto_callback_time_end', 5)->nullable()->after('auto_callback_time_start')->comment('定时回调结束时间(HH:MM)');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn(['auto_callback_enabled', 'auto_callback_cron', 'auto_callback_time_start', 'auto_callback_time_end']);
        });
    }
};