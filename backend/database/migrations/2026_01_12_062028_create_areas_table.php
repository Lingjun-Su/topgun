<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // SQL Server中需指定表名（避免大小写问题），字段类型适配
        Schema::create('areas', function (Blueprint $table) {
            $table->bigIncrements('id'); // 自增主键
            $table->string('name', 50)->comment('区域名称');
            $table->string('ext_name', 50)->comment('全称');
            $table->bigInteger('parent_id')->default(0)->comment('父级ID');
            $table->tinyInteger('level')->comment('层级：1=省，2=市，3=区');
            $table->string('code', 20)->nullable()->comment('行政区划代码');
            $table->string('parent_code', 20)->nullable()->comment('行政区划代码');
            $table->string('pinyin_prefix', 20)->nullable()->comment('拼音第一个字母');
            $table->string('pinyin', 100)->nullable()->comment('拼音');

            $table->timestamps(); // created_at/updated_at

            // 索引优化（查询更快）
            $table->index('parent_id');
            $table->index('level');
        });

        // 给表添加注释（SQL Server特有）
        if (config('database.default') === 'sqlsrv') {
            DB::statement("EXEC sp_addextendedproperty
                @name = N'MS_Description', @value = N'省市区数据表',
                @level0type = N'SCHEMA', @level0name = N'dbo',
                @level1type = N'TABLE', @level1name = N'areas'");
        }
    }

    public function down()
    {
        Schema::dropIfExists('areas');
    }
};
