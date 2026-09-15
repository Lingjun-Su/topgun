<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncOrderRequest; // 建议在Request里写校验逻辑
use App\Models\TianxuanOrder;
use App\Jobs\PushToXinquanyu;
use App\Services\TianxuanOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TianxuanOrdersController extends Controller
{
    /**
     * 接收并同步订购数据
     */
    public function receiveData(Request $request)
    {
        // 1. 严格签名校验 (保证来路安全)
        $appKey ='C32F4D61DAA524195BE17B43B3750922';// config('services.partner.key');
        if (!TianxuanOrderService::verifySign($request->all(), $appKey)) {
            return response()->json(['code' => 1, 'msg' => '签名错误']);
        }

        // 2. 存入 SQL Server (带审计与逻辑删除)
        // 假设这里使用 Eloquent，逻辑删除已在 Model 中配置
        $order = TianxuanOrder::create($request->all());

        // 3. 异步推送给另一家公司
        // 按照对方要求的 pid, bus_code 等覆盖或透传数据
        $pushData = array_merge($request->all(), [
            'pid'      => '186',
            'bus_code' => '96339749',
            'type'     => '1'
        ]);

        PushToXinquanyu::dispatch($pushData, '/api_v2/ThirdChannel/syncUserData');

        return response()->json(['code' => 0, 'msg' => '同步成功', 'data' => []]);
    }

    /**
     * 获取待同步的订单列表
     * GET /api_v2/ThirdChannel/pendingOrders
     */
    public function getPendingOrders()
    {
        // 只获取 sync_status 为 0 (待处理) 且未被逻辑删除的数据
        $orders = TianxuanOrder::where('deleted_at', NULL)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['code' => 0, 'data' => $orders]);
    }


    /**
     * 退订
     * POST /api_v2/ThirdChannel/syncUnsubUserData
     */
    public function cancelOrder(Request $request):JsonResponse
    {
        $id=$request->input('id');
        $order =TianxuanOrder::find($id);
        if (!$order) {
            return response()->json(['code' => 1, 'msg' => '订单不存在']);
        }
    }

    /**
     * 取消推送任务 (业务拦截法)
     * POST /api_v2/ThirdChannel/cancelOrder
     */
    public function cancelOrderSync(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $order = TianxuanOrder::find($id);

        if (!$order) {
            return response()->json(['code' => 1, 'msg' => '订单不存在']);
        }

        // 将状态改为 3 (已取消)，这样 Job 在执行时通过前置检查发现状态不对就会直接终止
        $order->sync_status = 3;
        $order->save();

        // 记录审计日志
        Log::channel('daily')->info("管理员取消了订单同步任务", ['id' => $id, 'order_no' => $order->order_no]);

        return response()->json(['code' => 0, 'msg' => '任务已成功取消']);
    }

    /**
     * 获取所有订单数据（带分页）
     * GET /api_v2/ThirdChannel/allOrders
     */
    public function getAllOrders(): JsonResponse
    {
        // 包含逻辑删除的数据（可选），并按创建时间倒序
        $orders = TianxuanOrder::withTrashed()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json(['code' => 0, 'data' => $orders]);
    }

    /**
     * 手动触发重试/推送测试
     * POST /api_v2/ThirdChannel/retryPush
     */
    public function retryPush(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $order = TianxuanOrder::findOrFail($id);

        // 记录审计日志：谁发起了手动推送
        Log::channel('daily')->info("管理员手动触发了推送测试", [
            'order_no' => $order->order_no,
            'operator_id' => auth()->id() // 如果有登录系统
        ]);

        // 重新分发 Job (使用当前数据库中的最新数据)
        // 强制修改状态为待同步，以便 Job 能通过前置逻辑检查
        $order->update(['sync_status' => 0]);

        // 推送给下家
        $pushData = $order->toArray();
        // 注意：这里需要确保 pushData 包含对方要求的 pid, bus_code 等固定值
        $pushData['pid'] = '186';
        $pushData['bus_code'] = '96339749';

        PushToXinquanyu::dispatch($pushData, '/api_v2/ThirdChannel/syncUserData');

        return response()->json(['code' => 0, 'msg' => '推送任务已重新加入队列']);
    }

    /**
     * 调试专用：直接转发数据到下家公司
     */
    public function directPushTest(Request $request)
    {

        $payload = $request->input('payload');
        $sign = $request->input('sign');

        // 记录审计日志：谁发起了调试推送
        Log::channel('daily')->info("管理员手动发起调试推送", ['payload' => $payload]);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['sign' => $sign])
                ->post('http://cladmintest.xinquanyu.top/api_v2/ThirdChannel/syncUserData', $payload);

            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => '中转推送失败: ' . $e->getMessage()
            ]);
        }
    }
}
