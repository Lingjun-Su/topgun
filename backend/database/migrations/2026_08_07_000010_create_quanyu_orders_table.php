<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quanyu_orders')) {
            Schema::create('quanyu_orders', function (Blueprint $table) {
                $table->id();
                $table->string('mobile', 20)->nullable()->comment('手机号');
                $table->string('pid', 50)->nullable()->comment('渠道PID');
                $table->string('bus_code', 50)->nullable()->comment('业务编码');
                $table->string('sku_code', 50)->nullable()->comment('产品编码');
                $table->string('order_no', 100)->unique()->comment('订单号');
                $table->string('create_time', 30)->nullable()->comment('创建时间');
                $table->string('type', 50)->nullable()->comment('类型');
                $table->string('platform', 50)->nullable()->comment('平台');
                $table->string('pack', 100)->nullable()->comment('套餐');
                $table->string('url', 500)->nullable()->comment('URL');
                $table->string('ip', 50)->nullable()->comment('IP地址');
                $table->string('sms_time', 30)->nullable()->comment('短信时间');
                $table->string('code', 50)->nullable()->comment('验证码');
                $table->string('order_status', 20)->nullable()->comment('订单状态');
                $table->decimal('price', 10, 2)->nullable()->comment('单价');
                $table->decimal('total_amount', 10, 2)->nullable()->comment('总金额');
                $table->integer('quantity')->nullable()->comment('数量');
                $table->tinyInteger('cancel_sync_status')->default(0)->comment('取消同步状态');
                $table->text('cancel_sync_error')->nullable()->comment('取消同步错误信息');
                $table->tinyInteger('sync_status')->default(0)->comment('同步状态：0待同步 1成功 2失败 -1取消');
                $table->text('sync_error')->nullable()->comment('同步错误信息');
                $table->text('push_response')->nullable()->comment('推送返回的完整响应信息(JSON)');
                $table->timestamp('push_response_at')->nullable()->comment('推送返回时间');
                $table->timestamp('pushed_at')->nullable()->comment('推送时间');
                $table->unsignedBigInteger('organization_id')->nullable()->comment('所属组织');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('pid');
                $table->index('sync_status');
                $table->index('order_no');
            });
        }
    }

    public function down(): void
    {
        // 不删除表，避免数据丢失
    }
};