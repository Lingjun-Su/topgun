<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 修复 sms_time 列长度不足问题
     * varchar(15) → varchar(25)，以容纳 'Y-m-d H:i:s' 格式的 19 位字符串
     */
    public function up(): void
    {
        $tables = ['product_orders', 'product_order_tests'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            // SQL Server 修改列长度
            DB::statement("ALTER TABLE [{$tableName}] ALTER COLUMN [sms_time] VARCHAR(25) NULL");
        }
    }

    public function down(): void
    {
        $tables = ['product_orders', 'product_order_tests'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            DB::statement("ALTER TABLE [{$tableName}] ALTER COLUMN [sms_time] VARCHAR(15) NULL");
        }
    }
};