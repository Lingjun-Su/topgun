<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            // ====== 接收上家信息配置 ======
            $table->string('receive_endpoint', 255)
                ->nullable()
                ->after('callback_url')
                ->comment('接收上家信息的接口地址');

            $table->string('receive_auth_mode', 50)
                ->default('signature')
                ->after('receive_endpoint')
                ->comment('接收认证方式: signature/header/none');

            $table->boolean('auto_forward')
                ->default(false)
                ->after('receive_auth_mode')
                ->comment('是否自动转发至下家');

            $table->string('forward_target_pid', 50)
                ->nullable()
                ->after('auto_forward')
                ->comment('自动转发目标渠道PID');

            // ====== 向下家推送信息配置 ======
            $table->string('push_notify_url', 255)
                ->nullable()
                ->after('forward_target_pid')
                ->comment('向下家推送处理结果通知地址');

            $table->integer('push_max_retries')
                ->default(3)
                ->after('push_notify_url')
                ->comment('推送最大重试次数');

            $table->integer('push_retry_delay')
                ->default(10)
                ->after('push_max_retries')
                ->comment('推送重试间隔(秒)');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn([
                'receive_endpoint',
                'receive_auth_mode',
                'auto_forward',
                'forward_target_pid',
                'push_notify_url',
                'push_max_retries',
                'push_retry_delay',
            ]);
        });
    }
};