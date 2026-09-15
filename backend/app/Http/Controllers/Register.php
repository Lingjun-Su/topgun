<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class Register extends Controller
{
    public function register(Request $request)
    {
        // 1. 验证输入字段
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            // 2. 使用事务确保数据一致性（SQL Server 尤其推荐）
            return DB::transaction(function () use ($request) {

                // 3. 创建用户
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => $request->password,
                ]);

                // 4. 为新用户生成 Sanctum 令牌
                $token = $user->createToken('quasar-auth-token')->plainTextToken;

                // 5. 返回用户信息和令牌
                return $this->success($token, '注册成功');
            });

        } catch (\Exception $e) {
            return $this->error('注册失败,服务器内部错误');
        }
    }
}
