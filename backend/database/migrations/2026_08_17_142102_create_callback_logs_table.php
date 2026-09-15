<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel_pid', 50)->comment('渠道标识');
            $table->string('callback_type', 50)->comment('回调类型');
            $table->string('request_method', 10)->comment('请求方法 GET/POST');
            $table->text('request_url')->comment('完整请求URL');
            $table->json('request_params')->comment('请求参数');
            $table->json('request_headers')->nullable()->comment('请求头');
            $table->text('raw_body')->nullable()->comment('POST原始body');
            $table->tinyInteger('status')->default(0)->comment('0待处理 1已处理 2处理失败');
            $table->timestamp('processed_at')->nullable()->comment('处理时间');
            $table->json('process_result')->nullable()->comment('处理结果');
            $table->text('process_error')->nullable()->comment('处理失败信息');
            $table->unsignedBigInteger('forward_order_id')->nullable()->comment('关联中转记录');
            $table->unsignedBigInteger('product_order_id')->nullable()->comment('关联产品订单');
            $table->timestamps();

            $table->index('channel_pid');
            $table->index('callback_type');
            $table->index('status');
            $table->index('forward_order_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_logs');
    }
};