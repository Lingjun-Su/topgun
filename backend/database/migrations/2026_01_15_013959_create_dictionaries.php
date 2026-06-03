
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
        Schema::create('dictionaries', function (Blueprint $table) {
            $table->id(); // 自增主键，原dict_id调整为id
            $table->string('type', 50)->comment('字典类型编码（新增：settlement_status=结算状态、invoice_status=发票状态）');
            $table->string('code', 50)->comment('字典项编码（如invoice_status的“paid”=已开票、“unpaid”=未开票）');
            $table->string('label', 50)->comment('字典项名称（如“已开票”“未开票”“结算中”“已完成”）');
            $table->unsignedBigInteger('sort')->default(0)->comment('排序权重');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dictionaries');
    }
};
