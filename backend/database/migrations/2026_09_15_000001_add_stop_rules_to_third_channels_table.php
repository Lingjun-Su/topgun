<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->text('stop_rules')
                ->nullable()
                ->after('receive_mapping')
                ->comment('停止条件配置(JSON)。格式: {"conditions":[{"id":"cond_1","condition_type":"request_limit","enabled":true,"step":["getCode","submit"],"message":"提示文案","params":{...}}]}');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn('stop_rules');
        });
    }
};