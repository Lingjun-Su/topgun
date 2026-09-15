<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->text('push_steps')
                ->nullable()
                ->after('push_fail_rule')
                ->comment('多步骤推送配置(JSON数组)。格式: [{"name":"步骤名","endpoint":"/path","method":"POST","success_rule":{...},"request_mapping":{...},"output_mapping":{...}}]');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn('push_steps');
        });
    }
};