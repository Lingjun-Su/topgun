<?php

namespace App\Traits;

/**
 * Code,含义,场景
 * 字段名,类型,描述
 * code,Integer,业务状态码（核心判断依据）。
 * status,String,"状态分类：success (成功), partial (部分成功), error (失败)。"
 * message,String,描述信息。用于 UI 界面直接弹窗展示给用户。
 * data,Object/Array,业务数据主体。error 时通常为 null。
 *
 * Code,方法名,场景描述,前端处理建议
 *
 * 完成成功
 * 200,success(),完全成功。code仅为200,无需再输入。查询数据返回、单条记录更新/删除。,绿色通知 (Positive)
 *
 * 部分成功
 * 206,partial(),部分成功。批量操作中，部分数据由于业务逻辑失败（如库存不足）。,黄色警告 (Warning) + 结果看板
 *
 * 失败返回
 * 其他
 */

// app/Traits/ApiResponse.php

namespace App\Traits;

trait ApiResponse
{
    /**
     * 1. 完全成功 (All Success) - Code 200
     */
    protected function success($data = [], $message = '操作成功')
    {
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    /**
     * 2. 部分成功 (Partial Success) - Code 206
     * 严谨建议：206 是 HTTP 标准中 Partial Content 的逻辑延伸
     */
    protected function partial($successData = [], $failData = [], $message = '部分操作成功')
    {
        return response()->json([
            'code' => 206,
            'status' => 'partial',
            'message' => $message,
            'data' => [
                'success_items' => $successData,
                'fail_items' => $failData,
                'counts' => [
                    'success' => count($successData),
                    'fail' => count($failData),
                ],
            ],
        ], 200);
    }

    /**
     * 3. 完全失败 (All Failure) - Code 400 或自定义
     */
    protected function error($message = '操作失败', $code = 400, $data = null)
    {
        return response()->json([
            'code' => $code,
            'status' => 'error',
            'message' => $message,
            'data' => $data,
        ], 200);
    }
}
