<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Exceptions\MaintenanceModeException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * 统一 API 错误响应格式化
 */
class ApiExceptionHandler
{
    /**
     * 错误码映射
     */
    protected array $errorCodes = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHORIZED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'INTERNAL_ERROR',
        503 => 'SERVICE_UNAVAILABLE',
    ];

    /**
     * 处理异常并返回统一格式
     */
    public function handle(Throwable $exception): \Illuminate\Http\JsonResponse
    {
        // API 请求才返回 JSON
        if (! $this->isApiRequest()) {
            throw $exception;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->handleValidation($exception),
            $exception instanceof AuthenticationException => $this->handleAuthentication($exception),
            $exception instanceof ModelNotFoundException => $this->handleModelNotFound($exception),
            $exception instanceof NotFoundHttpException => $this->handleNotFound($exception),
            $exception instanceof MaintenanceModeException => $this->handleMaintenance($exception),
            $exception instanceof HttpException => $this->handleHttp($exception),
            default => $this->handleGeneric($exception),
        };
    }

    /**
     * 判断是否为 API 请求
     */
    protected function isApiRequest(): bool
    {
        $request = request();

        return $request->expectsJson() || $request->is('api/*') || $request->is('v1/*') || $request->is('v2/*');
    }

    /**
     * 处理验证异常
     */
    protected function handleValidation(ValidationException $exception): \Illuminate\Http\JsonResponse
    {
        \Illuminate\Support\Facades\Log::warning('API验证失败', [
            'url' => request()->fullUrl(),
            'errors' => $exception->errors(),
            'input' => request()->except(['key', 'password', 'sign_key']),
        ]);

        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => '数据验证失败',
            'errors' => $exception->errors(),
        ], 422);
    }

    /**
     * 处理认证异常
     */
    protected function handleAuthentication(AuthenticationException $exception): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => 401,
            'status' => 'error',
            'message' => $exception->getMessage() ?: '未认证，请先登录',
        ], 401);
    }

    /**
     * 处理模型未找到
     */
    protected function handleModelNotFound(ModelNotFoundException $exception): \Illuminate\Http\JsonResponse
    {
        $model = class_basename($exception->getModel());

        return response()->json([
            'code' => 404,
            'status' => 'error',
            'message' => "{$model} 未找到",
        ], 404);
    }

    /**
     * 处理路由未找到
     */
    protected function handleNotFound(NotFoundHttpException $exception): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => 404,
            'status' => 'error',
            'message' => '请求的资源不存在',
        ], 404);
    }

    /**
     * 处理维护模式
     */
    protected function handleMaintenance(MaintenanceModeException $exception): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => 503,
            'status' => 'error',
            'message' => '服务维护中，请稍后再试',
            'retry_after' => $exception->getRetryAfter(),
        ], 503);
    }

    /**
     * 处理 HTTP 异常
     */
    protected function handleHttp(HttpException $exception): \Illuminate\Http\JsonResponse
    {
        $statusCode = $exception->getStatusCode();

        return response()->json([
            'code' => $statusCode,
            'status' => 'error',
            'message' => $exception->getMessage() ?: $this->getDefaultMessage($statusCode),
        ], $statusCode);
    }

    /**
     * 处理通用异常
     */
    protected function handleGeneric(Throwable $exception): \Illuminate\Http\JsonResponse
    {
        $statusCode = 500;

        // 生产环境下隐藏详细错误
        if (config('app.debug')) {
            return response()->json([
                'code' => $statusCode,
                'status' => 'error',
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ], $statusCode);
        }

        return response()->json([
            'code' => $statusCode,
            'status' => 'error',
            'message' => '服务器内部错误',
        ], $statusCode);
    }

    /**
     * 获取默认错误消息
     */
    protected function getDefaultMessage(int $statusCode): string
    {
        return $this->errorCodes[$statusCode] ?? '服务器错误';
    }
}
