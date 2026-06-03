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
        Schema::create('project_payables', function (Blueprint $table) {
            $table->id(); // 自增主键，原payable_id调整为id
            $table->string('no', 30)->unique()->comment('付款单号（如PAY-2026-001）');
            $table->unsignedBigInteger('project_id')->comment('关联项目ID');
            $table->unsignedBigInteger('settlement_detail_id')->nullable()->comment('关联结算明细ID');
            $table->unsignedBigInteger('organization_id')->comment('收款方组织ID（如分包组织、施工队等）');
            $table->decimal('amount', 18, 2)->comment('付款金额');
            $table->date('payable_date')->comment('付款日期（实际付款日期）');
            $table->unsignedTinyInteger('payment_method')->comment('付款方式（关联dictionaries表的payment_method类型）');
            $table->unsignedBigInteger('bank_account_id')->comment('付款银行账户ID');
            $table->unsignedTinyInteger('status')->default(0)->comment('付款状态（关联dictionaries表的payable_status类型）');
            $table->text('remark')->nullable()->comment('备注（如“农民工工资款”“分包进度款”等）');
            $table->unsignedBigInteger('employee_id')->comment('经办人ID');
            $table->timestamps();

            // 外键关联
            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('settlement_detail_id')->references('id')->on('settlement_details');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('bank_account_id')->references('id')->on('organization_banks');
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_payables');
    }
};
