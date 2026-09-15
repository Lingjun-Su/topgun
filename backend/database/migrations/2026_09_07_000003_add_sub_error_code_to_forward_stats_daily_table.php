<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->string('sub_error_code', 100)->nullable()->after('error_code')->comment('400xx 子错误码（从主错误码 E0005 的 forward_error 中提取），用于细分管控原因');

            $table->dropUnique('uqx_stats_daily');
        });

        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->unique(
                ['stat_date', 'target_pid', 'step', 'is_success', 'product_id', 'error_code', 'sub_error_code'],
                'uqx_stats_daily'
            );
            $table->index('sub_error_code');
        });
    }

    public function down(): void
    {
        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->dropUnique('uqx_stats_daily');
            $table->dropIndex(['sub_error_code']);
        });
        Schema::table('forward_stats_daily', function (Blueprint $table) {
            $table->unique(
                ['stat_date', 'target_pid', 'step', 'is_success', 'product_id', 'error_code'],
                'uqx_stats_daily'
            );
            $table->dropColumn('sub_error_code');
        });
    }
};