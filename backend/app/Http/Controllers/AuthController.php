<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * 用户登录并获取 Token
     */
    public function login(Request $request): JsonResponse
    {

        // Log::info('这条信息会出现在终端');

        // error_log('这是调试信息');
        // fwrite(STDERR, '这是调试信息Login' . PHP_EOL);
        // 1. 严格校验输入参数
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            // 2. 尝试进行身份验证 (底层会自动处理 Hash 比对)
            // SQL Server 环境下，确保 Users 表的 phone 字段有索引以提高性能
            if (! Auth::attempt($credentials)) {
                return $this->error('账号或密码错误', 401);
            }

            // 3. 获取用户信息
            $user = Auth::user();

            // 预防性检查：确保 $user 是我们预期的模型
            if (! $user instanceof \App\Models\User) {
                return $this->error('AuthController内部认证配置错误');
            }

            // 4. 生成新 Token
            // 注意：如果业务要求单点登录，可在此处先执行 $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            // 5. 返回规范化的 JSON 数据

            // 解析 data_permissions（JSON 字符串转数组或对象）
            $dataPermissions = $user->data_permissions
                ? (is_string($user->data_permissions) ? json_decode($user->data_permissions, true) : $user->data_permissions)
                : null;

            $data = [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'data_permissions' => $dataPermissions,
                ],
            ];

            return $this->success($data);

        } catch (\Exception $e) {
            // 记录异常日志 (SQL Server 连接异常等)
            Log::error('Login Error: '.$e->getMessage());

            return $this->error('服务器内部错误，请稍后重试');
        }
    }

    /**
     * 退出登录并作废 Token
     */
    public function logout(Request $request): JsonResponse
    {
        // 直接删除当前使用的 Token
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, '已安全退出');
    }
}
