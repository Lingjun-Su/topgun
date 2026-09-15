<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SQL Server 的 unique 索引不允许出现多个 NULL 值，
     * 因此需要将 email 的唯一索引改为过滤索引（仅对非空值生效）。
     */
    public function up(): void
    {
        // 1. 删除原有的唯一索引（SQL Server 自动命名，但 Laravel 默认使用 users_email_unique）
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        // 2. 创建过滤唯一索引：只对非 NULL 的 email 唯一约束
        DB::statement('CREATE UNIQUE INDEX [users_email_unique] ON [users]([email]) WHERE [email] IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. 删除过滤索引
        DB::statement('DROP INDEX [users_email_unique] ON [users]');

        // 2. 恢复普通唯一索引
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->change();
        });
    }
};