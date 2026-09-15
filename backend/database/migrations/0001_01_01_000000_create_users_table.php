<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // 手机号作为主要登录凭据：设置为唯一索引
            // 在 SQL Server 中建议指定长度，11位手机号用 char(11) 效率最高
            $table->char('phone', 11)->unique();

            // 邮箱设为可选（nullable）
            $table->string('email')->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->timestamp('last_login_at')->nullable(); // 最后登录时间
            $table->timestamp('deleted_at')->nullable(); // 逻辑删除
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        // 插入初始用户
        DB::table('users')->insert([
            'name' => 'Admin',
            'phone' => '18688414383',
            'email' => 'admin@example.com',
            'password' => bcrypt('a123456'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'name' => '黄骞',
            'phone' => '13609038214',
            'email' => 'huangqian@yihetx.net',
            'password' => bcrypt('a123456'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
