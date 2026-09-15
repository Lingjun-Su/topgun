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
        Schema::create('settlement_details', function (Blueprint $table) {
            $table->id(); // 自增主键，原detail_id调整为id
            $table->unsignedBigInteger('project_settlement_id')->comment('关联结算主表ID');
            $table->unsignedBigInteger('organization_id')->comment('参与方组织ID（如总包、分包、施工队等）');
            $table->string('type', 20)->comment('参与方类型（对应Excel“毅和”“施工队”“总包93.9%”等）');
            $table->decimal('amount', 18, 2)->comment('结算金额（对应Excel“毅和利润”“施工队结算金额”等）');
            $table->decimal('invoice_amount', 18, 2)->default(0)->comment('开票金额（对应Excel“毅和开票金额”等，未开票时为0）');
            $table->date('invoice_date')->nullable()->comment('发票日期（对应Excel“发票日期”字段，已开票时填写）');
            $table->decimal('paid_amount', 18, 2)->default(0)->comment('已付款金额（自动汇总对应已付款总额）');
            $table->decimal('unpaid_amount', 18, 2)->default(0)->comment('未付款金额（自动计算：amount - paid_amount）');
            $table->unsignedTinyInteger('status')->default(1)->comment('结算明细状态（关联dictionaries表的settlement_status类型）');
            $table->text('remark')->nullable()->comment('备注（如“含农民工工资XXX元”“扣质保金XXX元”等）');
            $table->timestamps();

            // 外键关联
            $table->foreign('project_settlement_id')->references('id')->on('project_settlements');
            $table->foreign('organization_id')->references('id')->on('organizations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_details');
    }
};
