<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('渠道名称');
            $table->string('pid', 50)->unique()->comment('对应文档中的pid');
            $table->string('key', 128)->comment('签名用的Key');
            $table->tinyInteger('organization_id')->nullable()->comment('对应组织ID');
            $table->string('method', 10)->comment('方法，接收或发送');
            $table->string('remark', 100)->nullable()->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('1:启用, 0:禁用,2:测试');

            $table->string('parameter_name1', 128)->nullable()->comment('参数1');//备用参数
            $table->string('parameter_value1', 128)->nullable()->comment('参数1');//备用参数
            $table->string('parameter_name2', 128)->nullable()->comment('参数2');//备用参数
            $table->string('parameter_value2', 128)->nullable()->comment('参数2');//备用参数
            $table->string('parameter_name3', 128)->nullable()->comment('参数3');//备用参数
            $table->string('parameter_value3', 128)->nullable()->comment('参数3');//备用参数
            $table->string('parameter_name4', 128)->nullable()->comment('参数4');//备用参数
            $table->string('parameter_value4', 128)->nullable()->comment('参数4');//备用参数
            $table->string('parameter_name5', 128)->nullable()->comment('参数5');//备用参数
            $table->string('parameter_value5', 128)->nullable()->comment('参数5');//备用参数


            // 基础审计字段
            $table->timestamps();
            $table->softDeletes(); // 逻辑删除
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->index('pid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_channels');
    }
};
