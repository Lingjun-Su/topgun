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
        Schema::create('master_contract_changes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('master_id')->index();
            $table->bigInteger('user_id')->comment('操作人ID');

            // 变动明细
            $table->string('field_name')->comment('修改字段名');
            $table->text('old_value')->nullable()->comment('修改前的值');
            $table->text('new_value')->nullable()->comment('修改后的值');

            $table->text('reason')->comment('修改原因/备注');
            $table->string('evidence_file')->nullable()->comment('证明附件');

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_contract_changes');
    }
};
