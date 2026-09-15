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
        Schema::create('positions', function (Blueprint $table) {
            $table->id(); // 自增主键，符合Laravel默认主键规范
            $table->unsignedBigInteger('organization_id')->comment('所属组织ID');
            $table->unsignedBigInteger('department_id')->nullable()->comment('所属部门ID');
            $table->string('name', 50)->comment('职位名称（如“项目经理”“施工员”“财务专员”“农民工班组长”）');
            $table->text('description')->nullable()->comment('职位描述（记录职位职责、权限范围等）');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重（用于职位列表展示排序）');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('department_id')->references('id')->on('departments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
