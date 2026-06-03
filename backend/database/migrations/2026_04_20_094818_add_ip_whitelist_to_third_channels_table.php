<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            /**
             * ip_whitelist: 存储允许访问的 IP 列表
             * 使用 text 以兼容 SQL Server 的 nvarchar(max)，存储量大且灵活
             */
            $table->text('ip_whitelist')
                  ->nullable()
                  ->comment('IP白名单，支持多个IP或网段（建议换行或逗号分隔）');

            /**
             * is_ip_restricted: 是否强制校验白名单
             * SQL Server 中 bit 类型对应 Laravel 的 boolean
             * 默认为 0 (不强制)，确保现有业务不被中断
             */
            $table->boolean('is_ip_restricted')
                  ->default(false)
                  ->comment('是否强制校验IP白名单 (1:是, 0:否)');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn(['ip_whitelist', 'is_ip_restricted']);
        });
    }
};
