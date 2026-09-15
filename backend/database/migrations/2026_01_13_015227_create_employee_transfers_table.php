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
        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id(); // 自增主键，符合Laravel默认主键规范
            $table->unsignedBigInteger('employee_id')->comment('关联员工ID');
            $table->unsignedTinyInteger('transfer_type')->comment('变动类型：1=入职，2=组织调动，3=部门调动，4=升迁，5=降级，6=离职，7=其他变动');
            $table->unsignedBigInteger('old_organization_id')->nullable()->comment('变动前所属组织ID（入职时为空）');
            $table->unsignedBigInteger('old_department_id')->nullable()->comment('变动前所属部门ID（入职/无部门时为空）');
            $table->unsignedBigInteger('old_position_id')->nullable()->comment('变动前所属职位ID（入职时为空）');
            $table->unsignedBigInteger('new_organization_id')->comment('变动后所属组织ID（离职时仍记录最后所属组织）');
            $table->unsignedBigInteger('new_department_id')->nullable()->comment('变动后所属部门ID（无部门时为空）');
            $table->unsignedBigInteger('new_position_id')->nullable()->comment('变动后所属职位ID（离职时为空）');
            $table->text('reason')->nullable()->comment('变动原因（如“项目调派”“能力晋升”“个人离职”等）');
            $table->date('effective_date')->comment('变动生效日期（入职日期/调动生效日期/离职日期）');
            $table->unsignedBigInteger('operator_id')->comment('操作人ID（关联办理变动的人事人员）');
            $table->text('remark')->nullable()->comment('备注（记录变动过程中的特殊说明）');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('old_organization_id')->references('id')->on('organizations');
            $table->foreign('old_department_id')->references('id')->on('departments');
            $table->foreign('old_position_id')->references('id')->on('positions');
            $table->foreign('new_organization_id')->references('id')->on('organizations');
            $table->foreign('new_department_id')->references('id')->on('departments');
            $table->foreign('new_position_id')->references('id')->on('positions');
            $table->foreign('operator_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_transfers');
    }
};
