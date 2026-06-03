<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 创建 organization_partnerships 表，用于记录组织间外部关系（如合作、挂靠、分包）。
     * - source_organization_id: 源组织（通常是内部公司，如毅和）。
     * - target_organization_id: 目标组织（外部，如分包商）。
     * - type: 关系类型，如 'cooperation' (合作), 'affiliation' (挂靠), 'subcontract' (分包), 'supply' (供应)。
     * - 支持附加属性：start_date/end_date (合同期), notes (备注), ratio (分账比例，用于唐贝项目)。
     * - 单向设计：如果需双向，可插入两条记录，但优先单向以简化。
     * - 删除前验证：用模型事件检查是否有下游数据（如结算记录）。
     * - 支持逻辑删除和审计。
     */
    public function up(): void
    {
        Schema::create('organization_partnerships', function (Blueprint $table) {
            $table->id();  // 主键
            $table->foreignId('source_organization_id')->constrained('organizations');  // 源组织 FK
            $table->foreignId('target_organization_id')->constrained('organizations');  // 目标组织 FK
            $table->enum('type', ['cooperation', 'affiliation', 'subcontract', 'supply', 'other']);  // 关系类型
            $table->date('start_date')->nullable();  // 关系开始日期
            $table->date('end_date')->nullable();  // 关系结束日期
            $table->decimal('ratio', 5, 2)->nullable();  // 分账比例（如0.3 表示30%）
            $table->text('notes')->nullable();  // 备注，如合同详情
            $table->softDeletes();  // 逻辑删除
            $table->timestamps();

            // 唯一约束：防止重复关系
            $table->unique(['source_organization_id', 'target_organization_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_partnerships');
    }
};
