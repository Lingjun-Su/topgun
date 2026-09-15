
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
        Schema::create('project_bids', function (Blueprint $table) {
            $table->id(); // 自增主键，原bid_id调整为id
            $table->string('no', 30)->unique()->comment('投标编号（如BID-2026-001）');
            $table->string('project_name', 100)->comment('投标项目名称（引用projects表名称，对应Excel“订单名称”）');
            $table->unsignedBigInteger('organization_id')->nullable()->comment('招标单位（甲方）ID');
            $table->decimal('amount', 18, 2)->comment('投标报价金额');
            $table->unsignedTinyInteger('result')->default(0)->comment('中标结果：0=待定，1=中标，2=未中标');
            $table->decimal('winning_amount', 18, 2)->nullable()->comment('中标金额（合同金额）');
            $table->unsignedBigInteger('employee_id')->nullable()->comment('创建人ID');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_bids');
    }
};
