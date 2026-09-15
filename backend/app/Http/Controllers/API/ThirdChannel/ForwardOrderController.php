<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardOrderJob;
use App\Models\ForwardOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ForwardOrderController extends Controller
{
    /**
     * 中转记录列表（分页）
     * GET /api/v1/forward-orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = ForwardOrder::orderBy('id', 'desc');

        // 按中转状态筛选
        if ($request->filled('forward_status')) {
            $query->where('forward_status', $request->input('forward_status'));
        }

        // 按来源单号筛选
        if ($request->filled('source_order_no')) {
            $query->where('source_order_no', 'like', '%' . $request->input('source_order_no') . '%');
        }

        // 按来源渠道 PID 筛选
        if ($request->filled('source_pid')) {
            $query->where('source_pid', $request->input('source_pid'));
        }

        // 按手机号筛选
        if ($request->filled('mobile')) {
            $query->where('mobile', $request->input('mobile'));
        }

        // 按当前步骤筛选
        if ($request->filled('current_step')) {
            $query->where('current_step', $request->input('current_step'));
        }

        $data = $query->paginate($request->get('per_page', 10));

        return $this->success($data);
    }

    /**
     * 中转记录详情
     * GET /api/v1/forward-orders/{id}
     */
    public function show($id): JsonResponse
    {
        $order = ForwardOrder::find($id);

        if (! $order) {
            return $this->error('记录不存在', 400);
        }

        return $this->success($order);
    }

    /**
     * 手动重试单条中转记录
     * POST /api/v1/forward-orders/{id}/retry
     */
    public function retry($id): JsonResponse
    {
        $order = ForwardOrder::find($id);

        if (! $order) {
            return $this->error('记录不存在', 400);
        }

        // 重试失败的步骤（从 current_step 恢复）
        $stepIndex = max(0, $order->current_step ?? 0);
        ForwardOrderJob::dispatch($order->id, $stepIndex);

        Log::channel('daily')->info('管理员手动重试数据中转', [
            'forward_order_id' => $order->id,
            'source_order_no' => $order->source_order_no,
            'step_index' => $stepIndex,
            'operator_id' => auth()->id(),
        ]);

        return $this->success([], '已重新加入中转队列');
    }

    /**
     * 批量重试中转记录
     * POST /api/v1/forward-orders/batch-retry
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return $this->error('未选择任何数据', 400);
        }

        $orders = ForwardOrder::whereIn('id', $ids)->get();

        if ($orders->isEmpty()) {
            return $this->error('未找到有效记录', 400);
        }

        $successCount = 0;
        foreach ($orders as $order) {
            $stepIndex = max(0, $order->current_step ?? 0);
            ForwardOrderJob::dispatch($order->id, $stepIndex);
            $successCount++;
        }

        Log::channel('daily')->info('管理员批量重试数据中转', [
            'ids' => $ids,
            'count' => $successCount,
            'operator_id' => auth()->id(),
        ]);

        return $this->success(['success_count' => $successCount], "批量处理完成，成功 {$successCount} 条");
    }
}