<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('execution_contracts', function (Blueprint $table) {
            $table->id();
            // 关联字段
            $table->bigInteger('master_id')->index()->comment('关联框架合同ID');
            $table->bigInteger('project_id')->index()->nullable()->comment('关联项目ID');
            $table->bigInteger('parent_id')->index()->nullable()->comment('关联父级订单ID(总包单)');

            // 编号与标题
            $table->string('system_no')->unique()->comment('系统唯一编号');
            $table->string('external_no')->index()->comment('外部业务单号(可重复)');
            $table->string('title')->comment('标题');
            $table->text('remarks')->nullable()->comment('详细内容/规格');

            // 主体与类型
            $table->bigInteger('org_a_id')->comment('甲方主体ID');
            $table->bigInteger('org_b_id')->comment('乙方主体ID');
            $table->string('type')->comment('REVENUE-收入型, COST-成本型');
            $table->string('region')->nullable()->comment('业务地区');

            // 金额与比例
            $table->decimal('total_amount', 18, 4)->default(0)->comment('合同总额');
            $table->decimal('ratio', 5, 2)->default(0)->comment('占比(%)');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('税率(%)');

            // 状态：0草稿 1审批中 2已生效 3已过期 4已终止 5作废
            $table->tinyInteger('status')->default(0)->comment('状态');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down() { Schema::dropIfExists('execution_contracts'); }
};
