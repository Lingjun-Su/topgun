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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id(); // 自增主键，原invoice_id调整为id
            $table->string('no', 30)->unique()->comment('发票单号（如INVOICE-2026-001）');
            $table->unsignedBigInteger('project_id')->comment('关联项目ID');
            $table->unsignedBigInteger('settlement_detail_id')->nullable()->comment('关联结算明细ID');
            $table->unsignedTinyInteger('invoice_type')->comment('发票类型（关联dictionaries表的invoice_type类型）');
            $table->unsignedBigInteger('issue_organization_id')->comment('开票方组织ID');
            $table->unsignedBigInteger('receive_organization_id')->comment('收票方组织ID（如甲方、总包、分包组织等）');
            $table->decimal('amount', 18, 2)->comment('发票金额（价税合计）');
            $table->decimal('tax_amount', 18, 2)->comment('税额');
            $table->decimal('tax_rate', 5, 2)->comment('税率（如9.00=9%，13.00=13%）');
            $table->date('invoice_date')->comment('发票日期（发票票面开具日期）');
            $table->string('invoice_code', 20)->comment('发票代码（与invoice_number组合唯一）');
            $table->string('invoice_number', 20)->comment('发票号码（与invoice_code组合唯一）');
            $table->unsignedTinyInteger('status')->default(0)->comment('发票状态（关联dictionaries表的invoice_status类型）');
            $table->text('remark')->nullable()->comment('备注（如“项目进度款发票”“结算尾款发票”等）');
            $table->unsignedBigInteger('employee_id')->comment('经办人ID');
            $table->timestamps();

            // 外键关联
            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('settlement_detail_id')->references('id')->on('settlement_details');
            $table->foreign('issue_organization_id')->references('id')->on('organizations');
            $table->foreign('receive_organization_id')->references('id')->on('organizations');
            $table->foreign('employee_id')->references('id')->on('employees');
            // 组合唯一索引
            $table->unique(['invoice_code', 'invoice_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
