<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use App\Models\QuanyuOrder;
use App\Models\ForwardOrder;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraceController extends Controller
{
    use ApiResponse;

    /**
     * 按 trace_id 查询全链路追踪信息
     * GET /api/v1/trace/{traceId}
     */
    public function show(string $traceId): JsonResponse
    {
        if (empty($traceId) || strlen($traceId) < 8) {
            return $this->error('无效的 trace_id', 400);
        }

        // 1. 查询正式订单
        $prodOrder = ProductOrder::where('trace_id', $traceId)->first();

        // 2. 查询测试订单
        $testOrder = null;
        if (class_exists(ProductOrderTest::class)) {
            $testOrder = ProductOrderTest::where('trace_id', $traceId)->first();
        }

        // 3. 查询全域订单中转记录
        $quanyuOrders = QuanyuOrder::where('order_no', $prodOrder?->order_no ?? $testOrder?->order_no)
            ->orWhere('trace_id', $traceId)
            ->get();

        // 4. 查询数据中转记录
        $forwardOrders = ForwardOrder::where('source_order_no', $prodOrder?->order_no ?? $testOrder?->order_no)
            ->orWhere('trace_id', $traceId)
            ->get();

        // 5. 构建全链路追踪结果
        $trace = [
            'trace_id' => $traceId,
            'product_order' => $prodOrder,
            'test_order' => $testOrder,
            'quanyu_orders' => $quanyuOrders,
            'forward_orders' => $forwardOrders,
            'links' => $this->buildLinks($prodOrder, $quanyuOrders, $forwardOrders),
        ];

        return $this->success($trace);
    }

    /**
     * 构建链路节点
     */
    protected function buildLinks($order, $quanyuOrders, $forwardOrders): array
    {
        $links = [];

        if ($order) {
            $links[] = [
                'node' => '接收层',
                'type' => 'order_received',
                'order_no' => $order->order_no,
                'status' => $order->sync_status,
                'time' => $order->created_at,
            ];
        }

        foreach ($quanyuOrders as $qo) {
            $links[] = [
                'node' => '推送层',
                'type' => 'push_to_quanyu',
                'order_no' => $qo->order_no,
                'status' => $qo->sync_status,
                'time' => $qo->created_at,
            ];
        }

        foreach ($forwardOrders as $fo) {
            $links[] = [
                'node' => '中转层',
                'type' => 'data_forward',
                'order_no' => $fo->source_order_no,
                'source_pid' => $fo->source_pid,
                'target_pid' => $fo->target_pid,
                'forward_status' => $fo->forward_status,
                'callback_status' => $fo->callback_status,
                'time' => $fo->created_at,
            ];
        }

        return $links;
    }
}