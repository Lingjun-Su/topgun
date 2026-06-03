<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();

            // 多态关联：指向被审批的业务实体
            $table->morphs('logable');

            // 谁执行的操作
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // 执行了什么动作：submit(提交), approve(同意), reject(驳回), fallback(退回)
            $table->string('action')->index();

            // 审批意见/备注
            $table->text('comment')->nullable();

            // 扩展：如果审批时上传了附件（如发票扫描件确认），可以预留
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
