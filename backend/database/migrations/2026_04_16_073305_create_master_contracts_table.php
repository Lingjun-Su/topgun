<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 执行迁移：创建框架合同表
     */
    public function up(): void
    {
        Schema::create('master_contracts', function (Blueprint $table) {
            // 基础信息
            $table->id();
            $table->string('contract_no', 50)->unique()->comment('合同编号');
            $table->string('title', 200)->comment('合同名称');

            // 主体信息 (关联组织架构表)
            $table->foreignId('org_a_id')->constrained('organizations')->comment('甲方组织ID');
            $table->foreignId('org_b_id')->constrained('organizations')->comment('乙方组织ID');

            // 关键金额与版本
            $table->decimal('total_limit', 18, 4)->default(0)->comment('合同总额度');
            $table->integer('version')->default(1)->comment('当前版本号');
            $table->tinyInteger('status')->default(0)->comment('状态: 0草稿 1审批中 2驳回 3已生效 4已过期 5已终止 6作废');

            // 日期管理
            $table->date('signed_date')->comment('签订日期');
            $table->date('effective_date')->comment('生效日期');
            $table->date('expiry_date')->comment('到期日期');

            // 人员信息
            $table->string('signer_a', 50)->nullable()->comment('甲方签订人');
            $table->string('contact_a', 50)->nullable()->comment('甲方联系人');
            $table->string('contact_a_phone', 50)->nullable()->comment('甲方联系人电话');
            $table->string('signer_b', 50)->nullable()->comment('乙方签订人');
            $table->string('contact_b', 50)->nullable()->comment('乙方联系人');
            $table->string('contact_b_phone', 50)->nullable()->comment('乙方联系人电话');
            // $table->foreignId('owner_id')->constrained('employees')->comment('系统内部负责人');

            // 内容与备注
            $table->text('summary')->nullable()->comment('合同内容简介');
            $table->text('remarks')->nullable()->comment('备注');

            // 审计与逻辑删除规范
            $table->timestamps();
            $table->softDeletes(); // 实现逻辑删除

            // 索引优化
            $table->index('contract_no');
            $table->index('expiry_date');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('master_contracts');
    }
};
