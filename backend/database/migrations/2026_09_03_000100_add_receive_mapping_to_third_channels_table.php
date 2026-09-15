<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 渠道行新增 receive_mapping（入站映射，按入口分段 order/verify）。
     * 承载"外部格式 → B 内部契约"翻译配置，幂等执行。
     */
    public function up(): void
    {
        if (! Schema::hasColumn('third_channels', 'receive_mapping')) {
            Schema::table('third_channels', function (Blueprint $table) {
                $table->text('receive_mapping')->nullable()->after('callback_config');
            });

            DB::statement('UPDATE third_channels SET receive_mapping = NULL WHERE receive_mapping IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn('receive_mapping');
        });
    }
};