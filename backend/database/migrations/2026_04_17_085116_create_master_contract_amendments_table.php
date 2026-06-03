<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('master_id')->index()->comment('框架合同ID');
            $table->string('amendment_no')->unique()->comment('补充协议编号');
            $table->string('title')->comment('协议标题');

            // 变更核心
            $table->decimal('amount_delta', 18, 4)->default(0)->comment('金额增量(可正可负)');
            $table->date('new_expiry_date')->nullable()->comment('调整后的有效期');
            $table->date('effective_date')->comment('变更生效日期');

            $table->text('change_content')->comment('变更摘要');
            $table->string('file_path')->nullable()->comment('补充协议扫描件路径');

            $table->tinyInteger('status')->default(0)->comment('状态');
            $table->bigInteger('created_by')->comment('创建人ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_contract_amendments');
    }
};
