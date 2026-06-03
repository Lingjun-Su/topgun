<?php

namespace App\Http\Controllers\Api\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncOrderRequest; // 建议在Request里写校验逻辑
use App\Jobs\PushToXinquanyu;
use App\Services\QuanyuOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

use App\Models\QuanyuOrder;
use App\Models\Organization;

use App\Services\ThirdChannel\QuanyuService;

class QuanyuOrdersController extends Controller
{

    /**
     * 获取待同步的订单列表
     * GET /api_v2/ThirdChannel/pendingOrders
     */
    public function getPendingOrders()
    {
        // 只获取 sync_status 为 0 (待处理) 且未被逻辑删除的数据
        return QuanyuOrder::where('deleted_at', NULL)
            ->orderBy('created_at', 'desc')
            ->get();

        // return response()->json(['code' => 0, 'data' => $orders]);
    }


    /**
     * 退订
     * POST /api_v2/ThirdChannel/syncUnsubUserData
     */
    public function cancelOrder(Request $request,$id,QuanyuService $quanyuServer):JsonResponse
    {
        // $id=$request->input('id');
        $order =QuanyuOrder::find($id);
        if (!$order) {
            return response()->json(['code' => 1, 'msg' => '订单不存在'.$id]);
        }
        $p = $quanyuServer->pushToTianxuan($order,'cancel');//推送
        return response()->json(['code'=>0,'msg'=>'同步成功','push'=>$p]);
    }

    /**
     * 取消推送任务 (业务拦截法)
     * POST /api_v2/ThirdChannel/cancelOrder
     */
    public function cancelOrderSync(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $order = QuanyuOrder::find($id);

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
     * 获取订单列表 (带分页和筛选)
     * * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {

        $data = QuanyuOrder::with(['organization','product:id,name,organization_id', 'product.organization:id,name,short_name'])->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'code' => 0,
            'msg'  => 'success',
            'test'=>'test',
            'data' => $data
        ]);

        // 1. 初始化查询构造器 (会自动应用 SoftDeletes 逻辑删除过滤)
        $query = QuanyuOrder::query();

        // 2. 严谨筛选：手机号模糊查询
        if ($request->filled('mobile')) {
            $query->where('mobile', 'like', '%' . $request->mobile . '%');
        }

        // 3. 严谨筛选：同步状态 (注意 0 值的判断)
        if ($request->has('status') && $request->status !== null && $request->status !== '') {
            $query->where('sync_status', $request->status);
        }

        // 4. 排序逻辑：默认按 ID 倒序
        $sortBy = $request->get('sortBy', 'id');
        $descending = $request->get('descending', 'true') === 'true' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $descending);

        // 5. 分页处理
        // SQL Server 2019 在 Laravel 底层会自动转换成 OFFSET FETCH 子句
        $limit = $request->get('limit', 15);
        $orders = $query->paginate($limit);

        // 6. 按照严谨的格式返回数据
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $orders->items(), // 当前页数据
            'total' => $orders->total(), // 总记录数，供前端 Quasar 分页器使用
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage()
        ]);
    }

    /**
     * 手动触发重试/推送测试
     * POST /api_v2/ThirdChannel/retryPush
     */
    public function retryPush(Request $request,$id,QuanyuService $tianxuan)
    {
        // $id = $request->input('id');
        $order = QuanyuOrder::find($id);
        // 记录审计日志：谁发起了手动推送
        Log::channel('daily')->info("管理员手动触发了推送测试", [
            'order_no' => $order->order_no,
            'operator_id' => auth()->id() // 如果有登录系统
        ]);

        // 复用 Service 逻辑
        // $this->orderService->syncToDownstream($order);
        return $tianxuan->pushToTianxuan($order);
    }

    /**
     * 批量重试同步
     */
    public function batchRetry(Request $request,$id,QuanyuService $tianxuan): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['code' => 1, 'message' => '未选择任何数据']);
        }

        $orders = QuanyuOrder::whereIn('id', $ids)->where('sync_status', '!=', 1)->get();

        $successCount = 0;
        foreach ($orders as $order) {
            // 调用之前写好的 Service 逻辑
            $tianxuan->pushToTianxuan($order);
            if ($order->sync_status === 1) {
                $successCount++;
            }
        }

        return response()->json([
            'code' => 0,
            'message' => "批量处理完成，成功 {$successCount} 条",
            'data' => ['success_count' => $successCount]
        ]);
    }


}
