<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forward_stops', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->string('source_pid')->nullable()->index();
            $table->string('source_order_no')->nullable();
            $table->string('mobile')->nullable();
            $table->string('step')->nullable();                                // getCode | submit
            $table->string('condition_id')->nullable();                        // 命中的条件实例ID
            $table->string('condition_type')->nullable();                      // 命中的条件类型
            $table->string('rule_message')->nullable();                        // 提示文案
            $table->text('request_data')->nullable();                          // 请求数据留痕(JSON)
            $table->timestamps();

            $table->index(['source_pid', 'step', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_stops');
    }
};