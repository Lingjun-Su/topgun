<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forward_orders', function (Blueprint $table) {
            // 多步骤转发字段
            $table->unsignedTinyInteger('current_step')->default(0)->after('forward_status')->comment('当前执行到的步骤索引');
            $table->text('step_data')->nullable()->after('target_response')->comment('步骤间传递数据(JSON)，如{"linkId":"xxx"}');
            $table->text('step_responses')->nullable()->after('step_data')->comment('各步骤响应记录(JSON)');

            // 手机号（单独拎出方便查询，同时也是验证码流程的关键标识）
            $table->string('mobile', 20)->nullable()->after('source_order_no')->comment('手机号');

            // 验证码提交尝试次数
            $table->unsignedTinyInteger('verify_attempts')->default(0)->after('retry_count')->comment('验证码提交尝试次数');

            // 索引
            $table->index('mobile');
            $table->index('current_step');
        });
    }

    public function down(): void
    {
        Schema::table('forward_orders', function (Blueprint $table) {
            $table->dropColumn(['current_step', 'step_data', 'step_responses', 'mobile', 'verify_attempts']);
        });
    }
};