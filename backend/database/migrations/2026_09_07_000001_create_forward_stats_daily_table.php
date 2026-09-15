<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forward_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->comment('业务统计日期');
            $table->tinyInteger('step')->comment('环节：0=验证码请求(getCode) 1=订单提交(submit)');
            $table->tinyInteger('is_success')->comment('1成功 0失败');
            $table->string('product_id', 50)->nullable()->comment('产品ID细分');
            $table->string('error_code', 100)->nullable()->comment('失败错误码；成功行为空');
            $table->integer('count')->default(0)->comment('该聚合键下的记录数');
            $table->timestamps();

            $table->unique(
                ['stat_date', 'step', 'is_success', 'product_id', 'error_code'],
                'uqx_stats_daily'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_stats_daily');
    }
};