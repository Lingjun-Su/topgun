<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Models\QuanyuOrder;
use App\Services\ThirdChannel\QuanyuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuanyuOrdersController extends Controller
{
    /**
     * 获取待同步的订单列表
     * GET /api_v2/ThirdChannel/pendingOrders
     */
    public function getPendingOrders()
    {
        return QuanyuOrder::where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 退订
     * POST /api_v2/ThirdChannel/syncUnsubUserData
     */
    public function cancelOrder(Request $request, $id, QuanyuService $quanyuServer): JsonResponse
    {
        $order = QuanyuOrder::find($id);
        if (! $order) {
            return response()->json(['code' => 1, 'msg' => '订单不存在'.$id]);
        }
        $p = $quanyuServer->pushToTianxuan($order, 'cancel');

        return response()->json(['code' => 0, 'msg' => '同步成功', 'push' => $p]);
    }

    /**
     * 取消推送任务 (业务拦截法)
     * POST /api_v2/ThirdChannel/cancelOrder
     */
    public function cancelOrderSync(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $order = QuanyuOrder::find($id);

        if (! $order) {
            return response()->json(['code' => 1, 'msg' => '订单不存在']);
        }

        $order->sync_status = 3;
        $order->save();

        Log::channel('daily')->info('管理员取消了订单同步任务', ['id' => $id, 'order_no' => $order->order_no]);

        return response()->json(['code' => 0, 'msg' => '任务已成功取消']);
    }

    /**
     * 获取订单列表 (带分页和筛选)
     */
    public function index(Request $request): JsonResponse
    {
        $query = QuanyuOrder::with(['organization', 'product:id,name,organization_id', 'product.organization:id,name,short_name']);

        // 按渠道 PID 筛选
        if ($request->filled('pid')) {
            $query->where('pid', $request->input('pid'));
        }

        // 按订单号模糊搜索
        if ($request->filled('order_no')) {
            $query->where('order_no', 'like', '%' . $request->input('order_no') . '%');
        }

        // 按同步状态筛选
        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->input('sync_status'));
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'code' => 0,
            'msg' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * 手动触发重试/推送测试
     * POST /api_v2/ThirdChannel/retryPush
     */
    public function retryPush(Request $request, $id, QuanyuService $tianxuan): JsonResponse
    {
        $order = QuanyuOrder::find($id);
        if (! $order) {
            return response()->json(['code' => 1, 'message' => '订单不存在']);
        }

        Log::channel('daily')->info('管理员手动触发了推送测试', [
            'order_no' => $order->order_no,
            'operator_id' => auth()->id(),
        ]);

        $result = $tianxuan->pushToTianxuan($order);

        return response()->json([
            'code' => $result['code'] ?? 0,
            'message' => $result['msg'] ?? '操作完成',
            'data' => $result,
        ]);
    }

    /**
     * 批量重试同步
     */
    public function batchRetry(Request $request, QuanyuService $tianxuan): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['code' => 1, 'message' => '未选择任何数据']);
        }

        $orders = QuanyuOrder::whereIn('id', $ids)->where('sync_status', '!=', 1)->get();

        $successCount = 0;
        foreach ($orders as $order) {
            $tianxuan->pushToTianxuan($order);
            if ($order->sync_status === 1) {
                $successCount++;
            }
        }

        return response()->json([
            'code' => 0,
            'message' => "批量处理完成，成功 {$successCount} 条",
            'data' => ['success_count' => $successCount],
        ]);
    }
}
