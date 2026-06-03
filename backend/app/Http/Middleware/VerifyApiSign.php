<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerifyApiSign
{
    private $secretKey = 'C32F4D61DAA524195BE17B43B3750922'; // 从文档获取的key

    public function handle(Request $request, Closure $next)
    {
        try {
            $sign = $request->header('sign');

            if (!$sign) {
                return response()->json([
                    'code' => 400,
                    'msg' => '签名不能为空'
                ], 400);
            }

            // 获取所有请求参数（排除sign本身）
            $params = $request->all();

            // 按照字典序排序参数
            ksort($params);

            // 构建参数字符串
            $signString = '';
            foreach ($params as $key => $value) {
                if ($value !== '' && !is_null($value)) {
                    $signString .= $key . '=' . $value . '&';
                }
            }

            // 去除末尾的&，并拼接key
            $signString = rtrim($signString, '&');
            $signString .= '&key=' . $this->secretKey;

            // 生成签名
            $generatedSign = strtoupper(md5($signString));

            // 验证签名
            if ($generatedSign !== $sign) {
                Log::warning('API签名验证失败', [
                    'received_sign' => $sign,
                    'generated_sign' => $generatedSign,
                    'sign_string' => $signString,
                    'params' => $params
                ]);

                return response()->json([
                    'code' => 401,
                    'msg' => '签名验证失败'
                ], 401);
            }

            return $next($request);

        } catch (\Exception $e) {
            Log::error('签名验证异常: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'msg' => '服务器内部错误'
            ], 500);
        }
    }
}
