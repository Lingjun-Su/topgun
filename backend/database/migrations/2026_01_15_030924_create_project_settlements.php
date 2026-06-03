
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
        Schema::create('project_settlements', function (Blueprint $table) {
            $table->id(); // 自增主键，原settlement_id调整为id
            $table->string('no', 30)->unique()->comment('结算单编号（如SETTLE-2026-001）');
            $table->unsignedBigInteger('project_id')->comment('关联项目ID');
            $table->unsignedBigInteger('project_subcontract_id')->nullable()->comment('关联分包合同ID（无分包时为空）');
            $table->date('settlement_date')->comment('结算日期（对应Excel“日期”字段）');
            $table->decimal('total_amount', 18, 2)->comment('结算总金额（所有参与方结算明细金额合计）');
            $table->unsignedTinyInteger('status')->default(1)->comment('结算状态（关联dictionaries表的settlement_status类型）');
            $table->text('remark')->nullable()->comment('备注（记录结算整体说明）');
            $table->unsignedBigInteger('operator_id')->comment('操作人ID（关联办理结算的人员）');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('project_subcontract_id')->references('id')->on('project_subcontracts');
            $table->foreign('operator_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_settlements');
    }
};
