<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_id')->index();

            // 多态关联
            $table->string('item_type');
            $table->unsignedBigInteger('item_id');

            // 财务快照：不仅记录金额，记录逻辑依据
            $table->decimal('price', 18, 2)->comment('结算时单价');
            $table->integer('quantity')->comment('结算时数量');
            $table->decimal('subtotal', 18, 2)->comment('结算时小计');

            // 核心快照：记录当时订单的完整状态 (JSON)
            // SQL Server 2019 支持 JSON 校验
            $table->text('data_snapshot')->nullable()->comment('原始数据全量快照');

            $table->timestamps();

            // 严谨：防止同一张表的同一条记录被结两次
            $table->unique(['item_type', 'item_id'], 'unique_biz_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};
