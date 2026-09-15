<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business', function (Blueprint $table) {

            // 1. 主键
            $table->id();
            $table->tinyInteger('status')->default(1)->comment('1:启用, 0:禁用');

            // 2. 关联字段 (对应你数据字典中的组织架构)
            $table->unsignedBigInteger('org_id')->comment('归属组织ID');
            $table->unsignedBigInteger('carrier_id')->comment('所属运营商ID');

            // 3. 核心业务字段
            $table->string('code', 50)->comment('编号'); // 重复的约束不写在这里，而在 uk_business_org_code
            $table->string('name', 200)->comment('业务名称');

            $table->string('short_name', 20)->comment('简称');
            $table->text('contents')->nullable()->comment('描述');
            $table->string('contact_person', 50)->nullable()->comment('联系人');
            $table->string('contact_phone', 20)->nullable()->comment('联系电话');
            $table->text('address')->nullable()->comment('办公地址');

            // 4. 数据审计字段 (规范化设计)
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('最后修改人ID');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('删除操作人ID');

            // 5. 时间戳与逻辑删除
            // Laravel 默认 timestamps 使用 datetime，SQL Server 2019 会自动映射为 datetime2
            $table->timestamps();
            $table->softDeletes()->comment('逻辑删除时间'); // 关键：生成 deleted_at 字段

            $table->index('org_id');
        });
        // 这保证了同一个组织下 code 不重复，但不同组织可以有相同的 code
        // 含义：仅当产品未被删除时，business_id + sku_code 必须唯一
        DB::statement('
            CREATE UNIQUE INDEX uk_business_org_code
            ON business(org_id, code)
            WHERE deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business');
    }
};
