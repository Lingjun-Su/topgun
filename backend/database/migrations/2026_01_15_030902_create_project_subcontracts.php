
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
        Schema::create('project_subcontracts', function (Blueprint $table) {
            $table->id(); // 自增主键，原subcontract_id调整为id
            $table->string('no', 30)->unique()->comment('分包合同编号');
            $table->unsignedBigInteger('project_id')->comment('关联项目ID');
            $table->unsignedBigInteger('contractor_organization_id')->comment('总包组织ID（如中邮建）');
            $table->unsignedBigInteger('subcontractor_organization_id')->comment('分包组织ID（如毅和、浩锋、施工队）');
            $table->string('province_code', 6)->nullable()->comment('省份编码（符合行政区划编码规则）');
            $table->string('city_code', 6)->nullable()->comment('城市编码（符合行政区划编码规则）');
            $table->string('district_code', 6)->nullable()->comment('区县编码（符合行政区划编码规则）');
            $table->string('street_code', 6)->nullable()->comment('街道编码（符合行政区划编码规则）');
            $table->decimal('amount', 18, 2)->comment('分包合同金额');
            $table->decimal('ratio', 5, 2)->comment('分包比例（对应Excel“分包比例”，如93.90=93.9%）');
            $table->text('scope')->comment('分包工程范围');
            $table->date('sign_date')->comment('合同签订日期');
            $table->date('start_date')->comment('计划开工日期');
            $table->date('end_date')->comment('计划竣工日期');
            $table->unsignedTinyInteger('status')->default(1)->comment('合同状态（关联dictionaries表）');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('contractor_organization_id')->references('id')->on('organizations');
            $table->foreign('subcontractor_organization_id')->references('id')->on('organizations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_subcontracts');
    }
};
