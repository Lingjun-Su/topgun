<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 针对 SQL Server 2019 优化
     */
    public function up(): void
    {
        // 同时生成两张表，其中一张专门用来测试
        $tables = ['product_orders', 'product_order_tests'];

        foreach ($tables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                // --- 基础关联与标识 ---
                $table->id();
                $table->unsignedBigInteger('settlement_id')->nullable()->index()->comment('归属结算记录ID');
                $table->tinyInteger('settle_status')->default(0)->comment('结算状态: 0未结算, 1已结算');

                // --- 对接识别字段 ---
                $table->string('pid', 50)->comment('渠道ID');
                $table->string('bus_code', 50)->index()->comment('业务标识');
                $table->string('sku_code', 100)->comment('产品标识');
                $table->unsignedBigInteger('product_id')->comment('产品ID');
                $table->unsignedBigInteger('business_id')->comment('业务ID');
                $table->unsignedBigInteger('channel_id')->comment('渠道ID');
                $table->unsignedBigInteger('organization_id')->comment('渠道公司ID');

                // --- 用户信息 ---
                $table->string('user_phone', 16)->comment('会员手机');
                $table->string('user_nick', 50)->nullable()->comment('会员昵称');
                $table->string('user_type', 64)->nullable()->comment('会员类型');
                $table->timestamp('user_create')->nullable()->comment('会员开通时间');
                $table->tinyInteger('user_status')->default(0)->comment('会员状态 0:使用中, 1:停止使用');
                $table->string('user_source', 64)->nullable()->comment('用户来源');
                $table->string('user_remark', 255)->nullable()->comment('用户备注');

                // --- 社交/微信信息 ---
                $table->string('wx_nick', 64)->nullable()->comment('微信昵称');
                $table->string('wx_name', 64)->nullable()->comment('微信ID');
                $table->string('wx_unionid', 64)->nullable()->comment('unionid');

                // --- 区域信息 ---
                $table->string('province_code', 10)->nullable()->comment('所属省份code');
                $table->string('city_code', 10)->nullable()->comment('所属市code');

                // --- 订单核心信息 ---
                $table->string('order_no', 100)->comment('订单号');
                $table->timestamp('order_time')->nullable()->comment('订购时间');
                $table->tinyInteger('order_status')->default(0)->comment('订单状态 0:未付款, 1:首次订购, 2:继订中, 3:退订');

                // --- 优惠券信息 ---
                $table->string('coupon', 50)->nullable()->comment('券名');
                $table->string('coupon_code', 64)->nullable()->comment('券号');
                $table->tinyInteger('coupon_write_off')->default(0)->comment('券是否已经核销');

                // --- 投流与环境信息 ---
                $table->string('type', 10)->comment('1:平安健康业务, 2:商超');
                $table->string('platform', 50)->nullable()->comment('投放平台');
                $table->string('pack', 100)->nullable()->comment('app包名');
                $table->string('url', 500)->nullable()->comment('推广落地页地址');
                $table->ipAddress('ip')->nullable()->comment('办理提交时的ip地址');
                $table->string('sms_time', 15)->nullable()->comment('验证码下发时间');
                $table->string('code', 20)->nullable()->comment('验证码');

                // --- 财务信息 ---
                $table->decimal('price', 18, 2)->default(0.00)->comment('单价');
                $table->integer('quantity')->default(1)->comment('数量');
                $table->decimal('total_amount', 18, 2)->default(0.00)->comment('总额');
                $table->decimal('internal_amount', 18, 2)->default(0.00)->comment('根据我们的价格进行计算的总额');

                // --- 推送/同步状态 ---
                $table->tinyInteger('sync_status')->default(0)->comment('下家推送状态: 0待处理, 1成功, 2失败');
                $table->text('sync_error')->nullable()->comment('推送失败原因');
                $table->timestamp('pushed_at')->nullable()->comment('成功推送时间');

                $table->tinyInteger('cancel_sync_status')->default(0)->comment('退订推送状态: 0待处理, 1成功, 2失败');
                $table->text('cancel_sync_error')->nullable()->comment('退订推送失败原因');

                // --- 扩展字段 ---
                $table->text('ext_json')->nullable()->comment('差异化参数(JSON)');

                // --- 数据审计与逻辑删除 ---
                $table->softDeletes();
                $table->unsignedBigInteger('created_by')->nullable()->index()->comment('创建人ID');
                $table->unsignedBigInteger('updated_by')->nullable()->index()->comment('最后修改人ID');
                $table->timestamps();

                // --- 索引优化 ---
                $table->index(['user_phone', 'order_no'], 'idx_'.$tableName.'_phone_order');
                $table->index(['created_at', 'sync_status'], 'idx_'.$tableName.'_created_sync');
                $table->index('pid', 'idx_'.$tableName.'_pid');
            });

            // SQL Server 专用：复合唯一过滤索引
            // 修改点：将索引字段改为 (pid, order_no)，实现同一 PID 下订单号唯一
            DB::statement("CREATE UNIQUE INDEX uq_{$tableName}_pid_no_active ON {$tableName}(pid, order_no) WHERE deleted_at IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_orders');
        Schema::dropIfExists('product_order_tests');
    }
};
