<?php

namespace App\Services\ThirdChannel;

use Illuminate\Support\Facades\Log;

class DataMapper
{
    /**
     * 将内部数据映射为外部API请求参数（推送方向）
     * 支持三种取值方式：
     *   1. 字段引用: "mobile" → 从 internalData 中取 mobile 字段
     *   2. 固定值: "gxhjy004" → 直接使用该字符串
     *   3. 上一步输出引用: "{step0.linkId}" → 从 internalData 中取 step0.linkId
     *
     * @param  array  $internalData   系统内部数据
     * @param  array  $requestMapping 请求参数映射表 {API参数名: 来源表达式}
     * @return array  API 请求参数
     */
    public function mapForPush(array $internalData, array $requestMapping): array
    {
        $result = [];

        foreach ($requestMapping as $targetKey => $sourceExpr) {
            // 处理占位符 {stepN.key}
            if (is_string($sourceExpr) && preg_match('/^\{(\w+)\}$/', $sourceExpr, $matches)) {
                $result[$targetKey] = $internalData[$matches[1]] ?? $sourceExpr;
            } elseif (is_string($sourceExpr) && preg_match('/^\{(\w+)\}\.(.+)$/', $sourceExpr, $matches)) {
                // 格式: {step0.code} → internalData['step0.code']
                $fullKey = $matches[1] . '.' . $matches[2];
                $result[$targetKey] = $internalData[$fullKey] ?? $sourceExpr;
            } else {
                // 直接映射到内部数据字段（找不到时当作固定值）
                $result[$targetKey] = $internalData[$sourceExpr] ?? $sourceExpr;
            }
        }

        return $result;
    }
}