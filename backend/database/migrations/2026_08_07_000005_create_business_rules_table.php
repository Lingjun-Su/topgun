<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->nullable()->comment('关联渠道ID（null=通用规则）');
            $table->unsignedBigInteger('business_id')->nullable()->comment('关联业务ID（null=通用规则）');
            $table->unsignedBigInteger('product_id')->nullable()->comment('关联产品ID（null=通用规则）');
            $table->string('name', 100)->comment('规则名称');
            $table->text('conditions')->nullable()->comment('规则条件（JSON格式）');
            $table->text('actions')->comment('规则动作（JSON格式）');
            $table->integer('priority')->default(0)->comment('优先级（数字越大优先级越高）');
            $table->tinyInteger('status')->default(1)->comment('状态：0禁用 1启用');
            $table->string('description', 500)->nullable()->comment('规则描述');
            $table->timestamps();
            $table->softDeletes();

            $table->index('channel_id');
            $table->index('business_id');
            $table->index('product_id');
            $table->index('priority');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rules');
    }
};