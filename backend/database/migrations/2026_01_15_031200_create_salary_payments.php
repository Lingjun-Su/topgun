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
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id(); // 自增主键
            $table->string('no', 30)->unique()->comment('发放单号（格式示例：SAL-PAY-2026-001）');
            $table->unsignedBigInteger('salary_detail_id')->comment('关联薪资明细ID');
            $table->unsignedBigInteger('employee_id')->comment('关联员工ID');
            $table->decimal('amount', 18, 2)->comment('实际发放金额（应与salary_details表的net_pay一致）');
            $table->date('payment_date')->comment('发放日期（记录薪资实际到账日期）');
            $table->unsignedTinyInteger('payment_method')->comment('付款方式（关联dictionaries表的payment_method类型）');
            $table->unsignedBigInteger('bank_account_id')->comment('付款银行账户ID');
            $table->string('employee_bank_account', 30)->comment('员工收款银行账户（冗余存储，便于对账核查）');
            $table->unsignedTinyInteger('status')->default(0)->comment('发放状态：0=待发放，1=已发放，2=部分发放，3=发放失败，4=作废');
            $table->string('payment_voucher', 255)->nullable()->comment('发放凭证附件（存储银行转账回单等附件路径）');
            $table->text('remark')->nullable()->comment('备注（如“12月农民工工资发放”“补发11月绩效工资”等）');
            $table->unsignedBigInteger('operator_id')->comment('经办人ID');
            $table->timestamps();

            // 外键关联
            $table->foreign('salary_detail_id')->references('id')->on('salary_details');
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('bank_account_id')->references('id')->on('organization_banks');
            $table->foreign('operator_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
