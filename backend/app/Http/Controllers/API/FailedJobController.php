<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

/**
 * 死信队列管理控制器
 * 提供查看、重试、删除失败任务的能力
 */
class FailedJobController extends Controller
{
    /**
     * 获取失败任务列表
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = (int) $request->input('page', 1);

        $allFailed = Queue::failed();

        // 手动分页
        $total = count($allFailed);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allFailed, $offset, $perPage);

        // 格式化数据
        $formatted = array_map(function ($job) {
            $payload = json_decode($job->payload, true);

            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'display_name' => $payload['displayName'] ?? 'Unknown',
                'failed_at' => $job->failed_at,
                'exception_preview' => mb_substr($job->exception, 0, 500),
            ];
        }, $items);

        return $this->success([
            'data' => $formatted,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * 重试单个失败任务
     */
    public function retry(string $id): JsonResponse
    {
        $result = Queue::retry($id);

        if ($result) {
            return $this->success(null, '任务已重新加入队列');
        }

        return $this->error('重试失败，任务不存在', 404);
    }

    /**
     * 批量重试失败任务
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return $this->error('请提供要重试的任务ID列表', 422);
        }

        $successCount = 0;
        foreach ($ids as $id) {
            if (Queue::retry((string) $id)) {
                $successCount++;
            }
        }

        return $this->success([
            'total' => count($ids),
            'success' => $successCount,
        ], "成功重试 {$successCount}/".count($ids).' 个任务');
    }

    /**
     * 删除单个失败任务
     */
    public function destroy(string $id): JsonResponse
    {
        Queue::forget($id);

        return $this->success(null, '任务已删除');
    }

    /**
     * 清空所有失败任务
     */
    public function flush(): JsonResponse
    {
        Queue::flush();

        return $this->success(null, '所有失败任务已清空');
    }
}