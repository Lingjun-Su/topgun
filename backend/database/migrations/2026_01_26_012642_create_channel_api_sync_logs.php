<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_api_sync_logs', function (Blueprint $table) {
            $table->id();

            // 1. 租户隔离：关联渠道 ID
            $table->unsignedBigInteger('channel_api_id')->index()->comment('对应channels_api表ID');

            // 3. 标识字段 (根据文档：bus_code, sku_code等)
            $table->string('pid', 50)->comment('原始报文中的pid字符串');
            $table->string('key', 128)->comment('签名用的Key');
            $table->string('parameter_1', 128)->nullalbe()->comment('参数1');
            $table->string('parameter_2', 128)->nullalbe()->comment('参数2');
            $table->string('parameter_3', 128)->nullalbe()->comment('参数3');
            $table->string('parameter_4', 128)->nullalbe()->comment('参数4');

            // 4. 灵活扩展字段：核心！
            // SQL Server 2019 支持 JSON，Laravel 会自动处理 array 转换
            $table->text('ext_json')->nullable()->comment('存储不同公司差异化的参数(JSON)');

            // 5. 原始报文审计：非常有必要，出问题时对账用
            $table->text('raw_request_body')->nullable()->comment('全量原始请求JSON');

            // 6. 审计与逻辑删除
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_api_sync_logs');
    }
};
