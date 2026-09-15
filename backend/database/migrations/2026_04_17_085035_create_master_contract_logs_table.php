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
        Schema::create('master_contract_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('master_id')->index();
            $table->bigInteger('user_id')->comment('操作人');

            // 对应合同状态状态: 0草稿 1审批中 2驳回 3已生效 4已过期 5已终止 6作废
            $table->string('action_type', 50)->comment('动作');
            $table->tinyInteger('from_status')->comment('原状态'); //
            $table->tinyInteger('to_status')->comment('新状态');

            $table->text('remark')->nullable()->comment('操作评语/驳回理由');

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_contract_logs');
    }
};
