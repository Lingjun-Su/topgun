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
        $tables = ['product_order_statuses', 'product_order_status_tests'];

        foreach ($tables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->comment('订单ID');
                $table->string('pid', 50)->index();
                $table->string('order_no', 100)->index();
                $table->tinyInteger('old_status')->comment('变更前状态');
                $table->tinyInteger('new_status')->comment('变更后状态');
                $table->decimal('price', 18, 2)->comment('变更时的单价');
                $table->decimal('total_amount', 18, 2)->comment('变更时的总额');
                $table->string('operator', 50)->nullable()->comment('操作员/来源');
                $table->timestamps();

                // 建议增加复合索引提高查询效率
                $table->index(['pid', 'order_no']);
            });
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_statuses');
        Schema::dropIfExists('product_order_status_tests');
    }
};
