<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            // 结算单号：严谨起见，增加唯一业务编号
            $table->string('settle_no', 32)->unique()->comment('结算批次号'); // 财务流水号

            // 金额统计
            $table->decimal('total_amount', 18, 2)->default(0.00)->comment('本次结算总金额');
            $table->integer('order_count')->default(0)->comment('结算订单数量');

            // 结算人信息 (关联用户表)
            $table->unsignedBigInteger('settled_by')->index()->comment('操作结算的人员ID');
            $table->timestamp('settled_at')->nullable()->comment('财务确认结算时间');

            $table->string('company_id')->index(); // 标记是哪家公司的结算

            // 备注
            $table->text('remark')->nullable()->comment('结算备注');

            // 基础审计与逻辑删除
            $table->timestamps();
            $table->softDeletes();

            // 索引优化
            $table->index(['settled_at', 'settled_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
