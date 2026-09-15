<?php

namespace App\Http\Middleware;

use App\Models\ThirdChannels;
use App\Services\ThirdChannel\SignService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VerifyChannelSignature
{
    protected SignService $signService;

    public function __construct(SignService $signService)
    {
        $this->signService = $signService;
    }

    public function handle(Request $request, Closure $next)
    {
        // 1. 获取 header 参数
        $pid = $request->header('X-PID');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (! $pid || ! $timestamp || ! $signature || ! $nonce) {
            return response()->json(['code' => 400, 'message' => '标头缺失'], 400);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['code' => 401, 'message' => '时间戳过期'], 401);
        }

        // 2. 判断环境（从路由参数或路径中获取）
        $isTestEnv = $request->route('env') === 'test' || str_contains($request->path(), 'test');
        $expectedStatus = $isTestEnv ? 2 : 1;
        $envSuffix = $isTestEnv ? 'test' : 'prod';
        $cacheKey = "channel_auth:{$pid}:{$envSuffix}";

        // 先从缓存取
        $channel = Cache::get($cacheKey);

        // 检查缓存数据是否有效（存在且 status 匹配当前环境）
        if ($channel && $channel->status != $expectedStatus) {
            // status 不匹配，说明数据库状态已变更，删除旧缓存并重新获取
            Cache::forget($cacheKey);
            $channel = null;
        }

        if (! $channel) {
            // 缓存缺失或无效，从数据库查询
            $channel = ThirdChannels::where('pid', $pid)
                ->where('status', $expectedStatus)
                ->first();

            if ($channel) {
                // 预处理白名单并存入缓存
                $channel->allowed_ips_array = $channel->ip_whitelist
                    ? preg_split('/[\s,]+/', trim($channel->ip_whitelist))
                    : [];
                Cache::put($cacheKey, $channel, 3600);
            }
        }

        if (! $channel) {
            return response()->json(['code' => 403, 'message' => '错误的PID'], 403);
        }

        // 3. IP 白名单校验
        if ($channel->is_ip_restricted) {
            $allowedIps = $channel->allowed_ips_array ?? [];
            if (! in_array($request->ip(), $allowedIps)) {
                return response()->json(['code' => 403, 'message' => 'IP禁止访问'], 403);
            }
        }

        // 4. 签名校验（使用动态签名算法）
        $rawContent = $request->getContent();
        $algorithm = $channel->sign_algorithm ?? 'sha256';
        if (! in_array($algorithm, SignService::SUPPORTED_ALGORITHMS)) {
            $algorithm = 'sha256';
        }

        if (! $this->signService->verify($signature, $rawContent, $channel->key, $timestamp, $nonce, $algorithm)) {
            return response()->json(['code' => 403, 'message' => '签名错误'], 403);
        }

        // 5. 将渠道信息存入请求，供后续控制器使用
        $request->merge(['_authenticated_channel' => $channel, '_is_test_env' => $isTestEnv]);

        return $next($request);
    }
}
