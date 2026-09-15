<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * API 请求日志中间件
 * 记录每次请求的完整信息，用于追溯和排查问题
 */
class LogApiRequest
{
    /**
     * 需要脱敏的字段
     */
    const SENSITIVE_FIELDS = ['key', 'password', 'secret', 'token', 'sign', 'signature', 'X-Signature'];

    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // 脱敏处理：替换敏感字段的值
        $headers = $request->headers->all();
        foreach (self::SENSITIVE_FIELDS as $field) {
            $lowerField = strtolower($field);
            foreach ($headers as $key => &$value) {
                if (strtolower($key) === $lowerField) {
                    $value = ['***'];
                }
            }
        }

        $body = $request->except(array_merge(self::SENSITIVE_FIELDS, ['X-Signature']));

        Log::channel('api_requests')->info('API Request', [
            'method' => $request->method(),
            'uri' => $request->fullUrl(),
            'headers' => $headers,
            'body' => $body,
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'pid' => $request->header('X-PID'),
        ]);

        return $response;
    }
}