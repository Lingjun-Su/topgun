<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 执行迁移：为 users 表扩展角色、部门及逻辑删除审计字段
     * 完美适配 SQL Server 2019 语法规范
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. 核心权限字段：角色标识 (例如: super_admin, finance_manager, auditor)
            // 使用 nvarchar 确保对中文或特殊字符的兼容，加索引提升多表关联或权限鉴定时查询效率
            $table->bigInteger('role_id')
                  ->after('password')
                  ->nullable()
                  ->index()
                  ->comment('用户核心角色标识：用于前端及后端中间件权限校验');
            $table->bigInteger('employee_id')
                  ->after('role_id')
                  ->nullable()
                  ->index()
                  ->comment('用户员工ID');

            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->comment('逻辑删除时间戳：非空代表已被软删除');
            }
        });

        // 4. SQL Server 2019 专用的字段描述持久化（通过存储过程注入系统表，方便第三方工具查阅数据字典）
        if (config('database.default') === 'sqlsrv') {
            $this->addSqlServerComment('users', 'role_id', '用户核心角色标识');
        }
    }

    /**
     * 回滚迁移：在非生产环境下安全移除字段
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 移除建立的索引与字段
            $table->dropIndex(['role_id']);
            $table->dropColumn('role_id');
        });
    }

    /**
     * 辅助方法：为 SQL Server 数据库系统表注入字段注释
     */
    private function addSqlServerComment(string $table, string $column, string $comment): void
    {
        DB::statement("
            EXEC sp_addextendedproperty
            @name = N'MS_Description', @value = N'{$comment}',
            @level0type = N'Schema', @level0name = N'dbo',
            @level1type = N'Table',  @level1name = N'{$table}',
            @level2type = N'Column', @level2name = N'{$column}'
        ");
    }
};
