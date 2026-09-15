<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_products', function (Blueprint $table) {
            $table->id();

            $table->Integer('channel_id')->comment('渠道ID');
            $table->Integer('product_id')->comment('产品ID');
            $table->tinyInteger('status')->default(1)->comment('1:启用, 0:禁用');
            $table->string('remark', 100)->nullable()->comment('备注');

            $table->timestamps();
            $table->softDeletes();

        });
        // 含义：仅当产品未被删除时，business_id + sku_code 必须唯一
        DB::statement('
            CREATE UNIQUE INDEX uk_channel_products_biz_sku
            ON channel_products(channel_id, product_id)
            WHERE deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_products');
    }
};
