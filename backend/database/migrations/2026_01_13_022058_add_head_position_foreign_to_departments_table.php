<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 解决 departments <-> positions 的循环外键问题
     * 在 positions 表创建完成后，才添加 departments.head_position_id 的外键
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // 先确保列存在（如果之前迁移已创建）
            // 如果你之前没创建列，这里也可以加 $table->foreignId('head_position_id')->nullable();

            $table->foreign('head_position_id')
                ->references('id')
                ->on('positions')
                ->onDelete('set null')   // 部门负责人岗位删除时，置空
                ->name('departments_head_position_id_foreign');  // 显式命名，便于后期 drop
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // $table->dropForeign('departments_head_position_id_foreign');
            // 如果需要，也可 dropColumn('head_position_id') 但一般不建议
        });
    }
};
