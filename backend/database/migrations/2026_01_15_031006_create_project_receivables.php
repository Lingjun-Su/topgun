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
        Schema::create('project_receivables', function (Blueprint $table) {
            $table->id(); // 自增主键，原receivable_id调整为id
            $table->string('no', 30)->unique()->comment('收款单号（如RECV-2026-001）');
            $table->unsignedBigInteger('project_id')->comment('关联项目ID');
            $table->unsignedBigInteger('settlement_detail_id')->nullable()->comment('关联结算明细ID');
            $table->unsignedBigInteger('organization_id')->comment('付款方组织ID（如甲方、总包组织等）');
            $table->decimal('amount', 18, 2)->comment('收款金额');
            $table->date('receivable_date')->comment('收款日期（实际到账日期）');
            $table->unsignedTinyInteger('payment_method')->comment('付款方式（关联dictionaries表的payment_method类型）');
            $table->unsignedBigInteger('bank_account_id')->comment('收款银行账户ID');
            $table->unsignedTinyInteger('status')->default(0)->comment('收款状态（关联dictionaries表的receivable_status类型）');
            $table->text('remark')->nullable()->comment('备注（如“项目进度款”“竣工结算款”等）');
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
        Schema::dropIfExists('project_receivables');
    }
};
