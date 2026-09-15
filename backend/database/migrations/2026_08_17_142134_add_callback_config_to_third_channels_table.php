<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->json('callback_config')->nullable()->after('auto_callback_time_end')->comment('回调接收配置');
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->dropColumn('callback_config');
        });
    }
};