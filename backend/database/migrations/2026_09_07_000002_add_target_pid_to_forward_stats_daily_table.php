<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->string('target_pid', 50)->nullable()->after('stat_date')->comment('上游A渠道PID（用于区分各A各自不同的错误码体系）');

            // 删除旧唯一索引，重建为包含 target_pid
            $table->dropUnique('uqx_stats_daily');
        });

        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->unique(
                ['stat_date', 'target_pid', 'step', 'is_success', 'product_id', 'error_code'],
                'uqx_stats_daily'
            );
            $table->index('target_pid');
        });
    }

    public function down(): void
    {
        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->dropUnique('uqx_stats_daily');
            $table->dropIndex(['target_pid']);
        });
        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->unique(
                ['stat_date', 'step', 'is_success', 'product_id', 'error_code'],
                'uqx_stats_daily'
            );
            $table->dropColumn('target_pid');
        });
    }
};