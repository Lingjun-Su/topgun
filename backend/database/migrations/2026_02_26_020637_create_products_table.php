<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // 1. 主键
            $table->id();
            $table->tinyInteger('status')->default(1)->comment('1:启用, 0:禁用');

            // 2. 关联字段
            $table->unsignedBigInteger('business_id')->comment('业务ID');
            $table->text('contents')->nullable()->comment('描述');

            // 3. 核心业务字段
            $table->string('sku_code', 50)->comment('产品编码/SKU');
            $table->string('name', 200)->comment('产品名称');
            $table->string('specification')->nullable()->comment('规格型号');
            $table->tinyInteger('pay_model')->default(0)->comment('收费模式0:按次, 1:按月,2按年');
            $table->decimal('base_price', 18, 4)->default(0)->comment('标准单价');
            $table->string('unit', 20)->comment('计量单位');

            // 4. 数据审计字段
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('最后修改人ID');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('删除操作人ID');

            // 5. 时间戳与逻辑删除
            $table->timestamps();
            $table->softDeletes()->comment('逻辑删除时间');

            // 索引优化
            $table->index('name');

            // 严谨：定义 business_id + sku_code 的复合唯一索引
            $table->index('business_id');
        });
        // 含义：仅当产品未被删除时，business_id + sku_code 必须唯一
        DB::statement("
            CREATE UNIQUE INDEX uk_products_biz_sku
            ON products(business_id, sku_code)
            WHERE deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
