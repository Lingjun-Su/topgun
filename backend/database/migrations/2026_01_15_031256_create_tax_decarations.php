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
        Schema::create('tax_declarations', function (Blueprint $table) {
            $table->id(); // 自增主键

            $table->unsignedBigInteger('employee_id')->comment('关联员工ID');
            $table->unsignedBigInteger('settlement_detail_id')->nullable()->comment('关联结算明细ID（确保农民工工资与结算明细一致）');
            $table->string('tax_month', 6)->comment('报税月份（格式：YYYYMM，如202612）');
            $table->decimal('taxable_income', 18, 2)->comment('应纳税所得额');
            $table->decimal('tax_amount', 18, 2)->comment('应纳税额');
            $table->decimal('paid_tax_amount', 18, 2)->comment('已纳税额');
            $table->date('declaration_date')->comment('报税日期');
            $table->string('declaration_no', 50)->nullable()->comment('报税单号（税务系统申报单号）');
            $table->unsignedTinyInteger('status')->default(0)->comment('报税状态：0=待报税，1=已报税，2=报税失败，3=已作废');
            $table->text('remark')->nullable()->comment('备注（记录报税相关说明）');
            $table->unsignedBigInteger('operator_id')->comment('经办人ID');
            $table->timestamps();

            // 外键关联
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('settlement_detail_id')->references('id')->on('settlement_details');
            $table->foreign('operator_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_declarations');
    }
};
