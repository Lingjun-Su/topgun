<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 为产品订单表添加 link_id 字段，存储移动返回的验证码ID
     */
    public function up(): void
    {
        $tables = ['product_orders', 'product_order_tests'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('link_id', 100)->nullable()->after('code')->comment('移动验证码ID（linkId），getCode步骤返回');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['product_orders', 'product_order_tests'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('link_id');
            });
        }
    }
};