<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Channel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckChannelSign
{
    /**
     * 处理第三方通道签名验证
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. 获取 Header 中的签名
        $sign = $request->header('sign');
        $pid = $request->input('pid');

        if (!$sign || !$pid) {
            return response()->json(['code' => 1, 'msg' => '缺少签名参数或渠道ID', 'data' => []]);
        }

        // 2. 从数据库获取渠道配置 (缓存建议后期加)
        $channel = Channel::where('pid_value', $pid)->where('status', 1)->first();
        if (!$channel) {
            return response()->json(['code' => 1, 'msg' => '渠道不存在或已禁用', 'data' => []]);
        }

        // 3. 准备签名校验数据
        $params = $request->except(['sign']); // 排除掉 sign 字段本身

        // 4. 生成本地签名
        $localSign = $this->generateSign($params, $channel->app_key);

        // 5. 比对
        if (strtoupper($sign) !== $localSign) {
            return response()->json(['code' => 1, 'msg' => '签名验证失败', 'data' => []]);
        }

        // 将 channel 信息存入 request 方便后续使用
        $request->attributes->set('channel_info', $channel);

        return $next($request);
    }

    /**
     * 签名算法实现
     */
    private function generateSign(array $params, string $key): string
    {
        // 1. 按字典序排序参数 (ksort)
        ksort($params);

        // 2. 格式化参数为 query string
        // 注意：Laravel 的 http_build_query 可能会处理布尔值，这里需确保一致性
        $string = http_build_query($params);

        // http_build_query 默认会对特殊字符进行 urlencode
        // 如果文档要求是原始字符拼接，需用 urldecode 回来
        $string = urldecode($string);

        // 3. 拼接 Key
        $string .= "&key=" . $key;

        // 4. MD5 加密并转化成大写
        return strtoupper(md5($string));
    }
}
