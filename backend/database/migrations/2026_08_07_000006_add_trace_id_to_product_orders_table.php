<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->string('trace_id', 36)->nullable()->after('id');
            $table->index('trace_id');
        });

        // 如果测试订单表存在，也添加 trace_id
        if (Schema::hasTable('product_order_tests')) {
            Schema::table('product_order_tests', function (Blueprint $table) {
                $table->string('trace_id', 36)->nullable()->after('id');
                $table->index('trace_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropIndex(['trace_id']);
            $table->dropColumn('trace_id');
        });

        if (Schema::hasTable('product_order_tests')) {
            Schema::table('product_order_tests', function (Blueprint $table) {
                $table->dropIndex(['trace_id']);
                $table->dropColumn('trace_id');
            });
        }
    }
};