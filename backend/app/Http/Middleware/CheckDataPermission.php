<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 数据权限中间件
 *
 * 检查当前用户的 data_permissions 配置：
 * 1. 如果用户没有 data_permissions（管理员），放行所有请求
 * 2. 如果用户有 data_permissions.page_restrictions，检查当前请求的页面是否在允许列表中
 * 3. 自动将用户允许的 channel_ids/business_ids/product_ids 注入到请求参数中
 */
class CheckDataPermission
{
    /**
     * 不需要权限检查的 API 路径前缀
     */
    private const EXCLUDED_PATHS = [
        '/api/v1/login',
        '/api/v1/logout',
        '/api/v1/user',
        '/api/v1/channels',
        '/api/v1/businesses',
        '/api/v1/products',
        '/api/v1/users',
        '/api/v1/changePassword',
    ];

    /**
     * 页面路由名称 → 允许的 API 路径前缀（支持多个）
     * 一个页面可能对应多个 API 前缀（如列表 + 报表）
     */
    private const PAGE_ROUTE_PATHS = [
        'product-order' => [
            '/api/v1/product-orders',
            '/api/v1/reports/product-order',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 未登录或没有 data_permissions（管理员/无限制用户）→ 放行
        if (! $user || ! $user->data_permissions) {
            return $next($request);
        }

        $permissions = $user->data_permissions;
        $path = $request->path();

        // 排除登录等基础路径
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (str_starts_with('/' . $path, $excluded)) {
                return $next($request);
            }
        }

        // 检查页面权限
        if (! empty($permissions['page_restrictions'])) {
            $allowed = false;
            foreach ($permissions['page_restrictions'] as $page) {
                $prefixes = self::PAGE_ROUTE_PATHS[$page] ?? null;
                if ($prefixes) {
                    foreach ((array) $prefixes as $prefix) {
                        if (str_starts_with('/' . $path, $prefix)) {
                            $allowed = true;
                            break 2;
                        }
                    }
                }
            }
            if (! $allowed) {
                abort(403, '您没有访问该页面的权限');
            }
        }

        // 自动注入渠道过滤条件到请求参数中
        if (! empty($permissions['channel_ids'])) {
            // 仅当请求未指定 pid 或 pid 为空时才注入
            if (! $request->filled('pid')) {
                $request->merge(['pid' => $permissions['channel_ids']]);
            }
        }

        // 自动注入业务过滤条件
        if (! empty($permissions['business_ids'])) {
            if (! $request->filled('business_id')) {
                $request->merge(['business_id' => $permissions['business_ids']]);
            }
        }

        // 自动注入产品过滤条件
        if (! empty($permissions['product_ids'])) {
            if (! $request->filled('product_id')) {
                $request->merge(['product_id' => $permissions['product_ids']]);
            }
        }

        return $next($request);
    }
}