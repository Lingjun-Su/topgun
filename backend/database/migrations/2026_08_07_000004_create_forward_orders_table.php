<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forward_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_channel_id')->comment('源渠道ID（A公司）');
            $table->unsignedBigInteger('target_channel_id')->nullable()->comment('目标渠道ID（B公司）');
            $table->string('source_order_no', 100)->comment('原始订单号');
            $table->string('target_order_no', 100)->nullable()->comment('目标系统订单号');
            $table->string('source_pid', 50)->comment('源渠道PID');
            $table->string('target_pid', 50)->nullable()->comment('目标渠道PID');
            $table->text('source_data')->nullable()->comment('原始数据（JSON）');
            $table->text('transformed_data')->nullable()->comment('转换后数据（JSON）');
            $table->text('target_response')->nullable()->comment('目标系统响应（JSON）');
            $table->tinyInteger('forward_status')->default(0)->comment('转发状态：0待处理 1成功 2失败');
            $table->text('forward_error')->nullable()->comment('转发错误信息');
            $table->string('callback_url', 500)->nullable()->comment('回调地址（回写A公司）');
            $table->tinyInteger('callback_status')->default(0)->comment('回调状态：0待回调 1成功 2失败');
            $table->text('callback_response')->nullable()->comment('回调响应（JSON）');
            $table->unsignedTinyInteger('retry_count')->default(0)->comment('重试次数');
            $table->unsignedBigInteger('organization_id')->nullable()->comment('所属组织');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index('source_order_no');
            $table->index('forward_status');
            $table->index('source_channel_id');
            $table->index('target_channel_id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_orders');
    }
};