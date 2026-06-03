
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
        Schema::create('projects', function (Blueprint $table) {
            $table->id(); // 自增主键，原project_id调整为id
            $table->unsignedBigInteger('parent_id')->nullable()->comment('上级项目ID，主项目为null');
            $table->string('order_no', 30)->unique()->comment('订单编号（对应Excel“订单编号”，如ORDER-2026-001）');
            $table->string('name', 100)->comment('项目名称（订单名称）');
            $table->string('province_code', 6)->nullable()->comment('省份编码（符合行政区划编码规则）');
            $table->string('city_code', 6)->nullable()->comment('城市编码（符合行政区划编码规则）');
            $table->string('district_code', 6)->nullable()->comment('区县编码（符合行政区划编码规则）');
            $table->string('street_code', 6)->nullable()->comment('街道编码（符合行政区划编码规则）');
            $table->unsignedBigInteger('project_bid_id')->nullable()->comment('关联投标记录ID');
            $table->unsignedBigInteger('organization_id')->comment('总包组织ID（如中邮建）');
            $table->unsignedBigInteger('employee_id')->comment('项目负责人ID');
            $table->string('region', 50)->nullable()->comment('区域（对应Excel“区域”字段）');
            $table->decimal('amount', 18, 2)->comment('项目合同金额');
            $table->decimal('general_contract_settlement_amount', 18, 2)->comment('总包结算金额（对应Excel“总包结算金额”）');
            $table->decimal('subcontract_ratio', 5, 2)->comment('分包比例（如93.90=93.9%，对应Excel“分包比例”字段）');
            $table->unsignedTinyInteger('status')->default(1)->comment('项目状态（关联dictionaries表）');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('project_bid_id')->references('id')->on('project_bids');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
