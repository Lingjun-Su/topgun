<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forward_orders', function (Blueprint $table) {
            $table->string('error_code', 100)->nullable()->after('forward_error')->comment('失败时B返回的错误码');
            $table->string('product_id', 50)->nullable()->after('error_code')->comment('产品ID（取自source_data的sku_code/product_id）');

            $table->index('error_code');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('forward_orders', function (Blueprint $table) {
            $table->dropIndex(['error_code']);
            $table->dropIndex(['product_id']);
            $table->dropColumn(['error_code', 'product_id']);
        });
    }
};