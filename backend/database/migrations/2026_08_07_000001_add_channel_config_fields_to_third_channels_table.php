<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            // 推送配置
            $table->string('push_base_url', 255)
                ->nullable()
                ->after('parameter_value5')
                ->comment('推送目标系统基础URL');

            $table->string('push_endpoint', 255)
                ->nullable()
                ->after('push_base_url')
                ->comment('推送接口路径');

            $table->integer('push_timeout')
                ->default(10)
                ->after('push_endpoint')
                ->comment('推送超时时间(秒)');

            // 签名配置
            $table->string('sign_algorithm', 50)
                ->default('sha256')
                ->after('push_timeout')
                ->comment('签名算法: sha256/md5/hmac_sha256');

            $table->string('sign_key', 255)
                ->nullable()
                ->after('sign_algorithm')
                ->comment('推送签名密钥(与接收key不同时可单独设置)');

            // 回调配置
            $table->string('callback_url', 255)
                ->nullable()
                ->after('sign_key')
                ->comment('回调通知地址');

            // 服务映射
            $table->string('service_class', 255)
                ->nullable()
                ->after('callback_url')
                ->comment('渠道对应的Service类名(全限定名)');

            // 扩展配置（SQL Server 使用 text 代替 json）
            $table->text('ext_config')
                ->nullable()
                ->after('service_class')
                ->comment('扩展配置(JSON格式，存储动态配置)');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn([
                'push_base_url',
                'push_endpoint',
                'push_timeout',
                'sign_algorithm',
                'sign_key',
                'callback_url',
                'service_class',
                'ext_config',
            ]);
        });
    }
};