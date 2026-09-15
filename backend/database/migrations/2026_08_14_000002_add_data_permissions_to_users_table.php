<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * data_permissions 存储用户的页面和数据访问权限，格式为 JSON：
     * {
     *   "page_restrictions": ["product-order"],       // 允许访问的页面路由名称
     *   "channel_ids": [1, 2, 3],                     // 允许查看的渠道 ID
     *   "business_ids": [],                            // 允许查看的业务 ID
     *   "product_ids": []                              // 允许查看的产品 ID
     * }
     * null 表示无限制（管理员）。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('data_permissions')->nullable()->after('role_id')
                ->comment('数据权限配置（JSON）：页面限制、渠道/业务/产品过滤');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('data_permissions');
        });
    }
};