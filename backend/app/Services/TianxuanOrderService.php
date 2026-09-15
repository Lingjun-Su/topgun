<?php

namespace App\Services;

class TianxuanOrderService
{
    /**
     * 生成签名 (严格按照文档描述的5步法)
     */
    public static function generateSign(array $params, string $key): string
    {
        // 1. 过滤掉 sign 字段本身，按字典序排序参数名
        ksort($params);

        // 2. & 3. 格式化参数并拼上 Key
        $queryString = http_build_query($params).'&key='.$key;

        // http_build_query 默认会对特殊字符进行 urlencode
        // 如果文档要求原始字符拼接，请使用 urldecode($queryString)
        $rawString = urldecode($queryString);

        // 4. & 5. MD5加密并转大写
        return strtoupper(md5($rawString));
    }

    /**
     * 校验签名
     */
    public static function verifySign(array $params, string $key): bool
    {
        if (! isset($params['sign'])) {
            return false;
        }
        $sign = $params['sign'];
        unset($params['sign']);

        return self::generateSign($params, $key) === $sign;
    }
}
