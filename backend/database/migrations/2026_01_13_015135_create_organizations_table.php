
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id(); // 自增主键，INT类型，对应文档id字段
            $table->unsignedInteger('parent_id')->default(0)->comment('上级组织ID，顶级组织为null');
            $table->unsignedTinyInteger('level')->comment('组织层级：1=顶级组织，2=二级组织，3=三级组织等');
            $table->string('province_code', 16)->nullable()->comment('省份编码（符合行政区划编码规则）');
            $table->string('city_code', 16)->nullable()->comment('城市编码（符合行政区划编码规则）');
            $table->string('district_code', 16)->nullable()->comment('区县编码（符合行政区划编码规则）');
            $table->string('street_code', 16)->nullable()->comment('街道编码（符合行政区划编码规则）');
            $table->string('name', 50)->comment('组织名称（如中邮建、毅和、浩锋、施工队等）');
            $table->string('short_name', 50)->comment('组织简称）');
            $table->string('social_credit_code', 18)->comment('统一社会信用代码');
            $table->unsignedTinyInteger('type')->comment('组织类型：1=甲方，2=总包，3=分包，4=施工队');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->text('address')->nullable()->comment('地址');
            $table->string('contact_person', 20)->nullable()->comment('联系人');
            $table->string('contact_phone', 12)->nullable()->comment('联系电话');
            $table->string('legal_representative', 20)->nullable()->comment('法人代表');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps(); // 对应created_at、updated_at字段，DATETIME2类型
            $table->softDeletes();
        });
        // 插入初始用户
        DB::table('organizations')->insert([
            'name' => '拓耕集团',
            'type'=>1,
            'level'=>1,
            'short_name' => '拓耕',
            'social_credit_code'=>'11',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
