<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forward_orders', function (Blueprint $table) {
            $table->string('trace_id', 36)
                ->nullable()
                ->after('id')
                ->comment('全链路追踪ID');

            $table->index('trace_id', 'idx_fo_trace_id');
        });
    }

    public function down(): void
    {
        Schema::table('forward_orders', function (Blueprint $table) {
            $table->dropIndex('idx_fo_trace_id');
            $table->dropColumn('trace_id');
        });
    }
};