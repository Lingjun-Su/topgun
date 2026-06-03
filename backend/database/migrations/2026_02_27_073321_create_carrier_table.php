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
        Schema::create('carrier', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('status')->default(1)->comment('1:启用, 0:禁用');

            $table->string('name',100)->comment('名称');
            $table->string('short_name',30)->nullable()->comment('简称');
            $table->string('code',16)->unique()->comment('人工唯一编号');

            $table->text('contents')->nullable()->comment('描述');
            $table->string('contact_person', 50)->nullable()->comment('联系人');
            $table->string('contact_phone', 20)->nullable()->comment('联系电话');
            $table->text('address')->nullable()->comment('办公地址');

            // 数据审计字段 (规范化设计)
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('最后修改人ID');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('删除操作人ID');

            $table->timestamps();
            $table->softDeletes();
        });
        // 插入初始数据
        DB::table('carrier')->insert([
            'name' => '中国电信',
            'short_name' => '电信',
            'code' => '001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('carrier')->insert([
            'name' => '中国移动',
            'short_name' => '移动',
            'code' => '002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('carrier')->insert([
            'name' => '中国联通',
            'short_name' => '联通',
            'code' => '003',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('carrier')->insert([
            'name' => '其他运营商',
            'short_name' => '其他',
            'code' => '004',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrier');
    }
};
