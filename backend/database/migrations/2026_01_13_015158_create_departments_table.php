
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
        Schema::create('departments', function (Blueprint $table) {
            $table->id(); // 自增主键
            $table->unsignedBigInteger('organization_id')->comment('所属组织ID');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('上级部门ID，顶级部门为null');
            $table->unsignedTinyInteger('level')->comment('部门层级：1=顶级部门，2=二级部门等');
            $table->string('name', 50)->comment('部门名称（如“工程部”“财务部”“施工一队”）');
            $table->unsignedBigInteger('employee_id')->nullable()->comment('部门负责人ID');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('organization_id')->references('id')->on('organizations');
            // $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
