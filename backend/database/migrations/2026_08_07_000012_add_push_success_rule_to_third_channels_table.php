<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            // 推送成功判断规则
            $table->text('push_success_rule')
                ->nullable()
                ->after('ext_config')
                ->comment('推送成功判断规则(JSON)。如: {"field":"code","value":0,"operator":"eq"} 表示响应中 code=0 为成功');

            // 推送失败判断规则
            $table->text('push_fail_rule')
                ->nullable()
                ->after('push_success_rule')
                ->comment('推送失败判断规则(JSON)。如: {"field":"code","value":1,"operator":"eq"} 表示响应中 code=1 为失败');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn(['push_success_rule', 'push_fail_rule']);
        });
    }
};