<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 三角色模型（ADR-002）：unset / supplier_a(上游供应商A) / channel_c(下游推广C)
    public function up(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            if (! Schema::hasColumn('third_channels', 'role')) {
                $table->string('role', 32)->default('unset')->after('method');
                $table->index('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            if (Schema::hasColumn('third_channels', 'role')) {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            }
        });
    }
};