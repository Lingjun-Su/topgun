<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 执行迁移：创建产品与省份销售范围关联表
     */
    public function up(): void
    {
        Schema::create('product_provinces', function (Blueprint $table) {
            // 1. 关联产品 ID (使用 unsignedBigInteger 匹配 products 表默认的 id 类型)
            $table->unsignedBigInteger('product_id')->comment('关联产品ID');

            // 2. 省份标识 (建议使用行政区划代码，如 '510000' 代表四川)
            $table->string('province_code', 10)->comment('省份行政区划代码');

            // 3. 设定复合主键：防止同一个产品在同一个省份被重复添加
            $table->primary(['product_id', 'province_code']);

            // 数据审计字段 (规范化设计)
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('最后修改人ID');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('删除操作人ID');

            $table->timestamps();
            $table->softDeletes();

            // 4. 设定外键约束
            // onDelete('cascade') 表示如果产品被物理删除，关联记录自动删除
            // 注意：因为我们使用了 SoftDeletes (逻辑删除)，所以物理删除通常不会发生
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');

            // 5. 索引优化 (primary key 默认已创建聚集索引，若常按省份反查产品，可加此索引)
            $table->index('province_code');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('product_provinces');
    }
};
