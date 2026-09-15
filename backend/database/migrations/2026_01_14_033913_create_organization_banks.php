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
        Schema::create('organization_banks', function (Blueprint $table) {
            $table->id(); // 自增主键
            $table->unsignedBigInteger('organization_id')->comment('所属组织ID');
            $table->string('bank_name', 100)->nullable()->comment('开户银行名称');
            $table->string('bank_account', 30)->nullable()->comment('银行账号');
            $table->string('account_name', 50)->nullable()->comment('账户名称（与组织名称一致）');
            $table->unsignedTinyInteger('is_default')->default(0)->comment('是否默认账户：1=是，0=否');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            // 外键关联
            $table->foreign('organization_id')->references('id')->on('organizations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_banks');
    }
};
