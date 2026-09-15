<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductOrderRequest;
use App\Models\ForwardOrder;
use App\Models\ThirdChannels;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 产品订单基础控制器
 * 严谨性：通过抽象类强制子类实现模型绑定
 * 规范性：遵循 RESTful 响应规范与标准异常处理
 */
abstract class BaseProductOrderController extends Controller
{
    /**
     * @var \Illuminate\Database\Eloquent\Model|\App\Models\ProductOrder
     */
    protected $model;

    /**
     * 构造函数由子类调用，用于注入具体的 Model (正式或测试)
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * 列表查询
     * 支持分页、关键字搜索、业务代码联动筛选
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->model->query();

        // 1. 关键字搜索 (订单号/手机号)
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                    ->orWhere('user_phone', 'like', "%{$kw}%");
            });
        }

        // 2. 联动筛选 (业务单位与产品标识)
        $query->when($request->bus_code, fn ($q, $v) => $q->where('bus_code', $v))
            ->when($request->sku_code, fn ($q, $v) => $q->where('sku_code', $v));

        // 3. 状态筛选
        $query->when($request->filled('sync_status'), fn ($q, $v) => $q->where('sync_status', $request->sync_status));

        // 4. 时间范围筛选 (针对 SQL Server 索引优化)
        if ($request->filled('date_range') && is_array($request->date_range)) {
            $query->whereBetween('created_at', [$request->date_range[0], $request->date_range[1]]);
        }

        // 分页返回
        $perPage = $request->get('per_page', 15);
        $data = $query->orderByDesc('id')->paginate($perPage);

        return $this->success($data);
    }

    /**
     * 创建订单
     */
    public function store(ProductOrderRequest $request): JsonResponse
    {
        $payload = $request->validated();

        // 严谨性：后端二次计算总额，防止前端传值错误
        $payload['total_amount'] = ($payload['price'] ?? 0) * ($payload['quantity'] ?? 0);

        $order = $this->model->create($payload);

        return $this->success($order, '创建成功');
    }

    /**
     * 详情与审计日志
     */
    public function show($id): JsonResponse
    {
        // 查找包含被逻辑删除的数据（可选，根据业务决定是否允许查看历史）
        $order = $this->model->findOrFail($id);

        // 获取审计记录 (需配合 Laravel-Auditing)
        $audits = $order->audits()->with('user')->orderByDesc('created_at')->get();

        return $this->success([
            'order' => $order,
            'audits' => $audits,
        ]);
    }

    /**
     * 更新订单
     */
    public function update(ProductOrderRequest $request, $id): JsonResponse
    {
        $order = $this->model->findOrFail($id);
        $payload = $request->validated();

        // 如果修改了单价或数量，重新计算总额
        if (isset($payload['price']) || isset($payload['quantity'])) {
            $price = $payload['price'] ?? $order->price;
            $qty = $payload['quantity'] ?? $order->quantity;
            $payload['total_amount'] = $price * $qty;
        }

        $order->update($payload);

        return $this->success($order, '更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy($id): JsonResponse
    {
        $order = $this->model->findOrFail($id);

        // 执行逻辑删除 (Eloquent 自动处理 SoftDeletes)
        $order->delete();

        return $this->success(null, '数据已移至回收站');
    }

    /**
     * 推送逻辑 (单条/批量共用基础)
     * 使用渠道的 callback_url（订单推送地址）统一推送
     * 推送成功 → sync_status = 1，失败 → sync_status = 2
     */
    protected function performPush($order)
    {
        $result = null;

        try {
            // 2. 加载渠道配置
            $channel = ThirdChannels::where('pid', $order->pid)->first();
            if (! $channel) {
                throw new \Exception("渠道不存在(pid={$order->pid})，无法推送");
            }

            // 3. 获取推送地址（与定时推送一致，使用 callback_url）
            $pushUrl = $channel->callback_url;
            if (! $pushUrl) {
                throw new \Exception('渠道未配置订单推送地址(callback_url)');
            }

            // 4. 准备推送数据（移除内部字段）
            $pushData = $order->toArray();
            $internalFields = ['id', 'sync_status', 'pushed_at', 'sync_error',
                'link_id', 'trace_id', 'product_id', 'business_id', 'channel_id',
                'organization_id', 'deleted_at', 'created_at', 'updated_at',
                'ext_json', 'internal_amount', 'settlement_id', 'settle_status'];
            foreach ($internalFields as $field) {
                unset($pushData[$field]);
            }

            // 5. 调用统一推送服务
            $pushService = app(PushService::class);
            $result = $pushService->send(
                $pushData,
                $pushUrl,
                $channel->push_success_rule,
                $channel->push_fail_rule,
                $channel->push_timeout ?? 10
            );

            if ($result['success']) {
                $order->update([
                    'sync_status' => 1,
                    'pushed_at' => now(),
                    'sync_error' => null,
                ]);

                // 同步更新 ForwardOrder 回调状态
                // 注意：必须使用模型实例 update() 以触发 array cast，避免数组被 Arr::flatten 展开导致参数错位
                $forwardOrder = ForwardOrder::where('source_order_no', $order->order_no)
                    ->where('source_pid', $order->pid)
                    ->where('callback_status', ForwardOrder::CALLBACK_PENDING)
                    ->first();
                if ($forwardOrder) {
                    $forwardOrder->update([
                        'callback_status' => ForwardOrder::CALLBACK_SUCCESS,
                        'callback_response' => $result['body'],
                    ]);
                }

                Log::channel('push')->info('手动推送成功', [
                    'order_no' => $order->order_no,
                    'pid' => $order->pid,
                    'push_url' => $pushUrl,
                    'http_status' => $result['http_status'],
                    'response' => $result['body'],
                ]);

                return true;
            }

            $errorMsg = $result['body']['msg'] ?? $result['body']['message'] ?? "HTTP {$result['http_status']}";
            throw new \Exception($errorMsg);
        } catch (\Exception $e) {
            $order->update([
                'sync_status' => 2,
                'sync_error' => $e->getMessage(),
            ]);

            // 同步更新 ForwardOrder 回调状态
            ForwardOrder::where('source_order_no', $order->order_no)
                ->where('source_pid', $order->pid)
                ->where('callback_status', ForwardOrder::CALLBACK_PENDING)
                ->update([
                    'callback_status' => ForwardOrder::CALLBACK_FAILED,
                    'callback_response' => $e->getMessage(),
                ]);

            Log::channel('push_failure')->warning('手动推送失败', [
                'order_no' => $order->order_no ?? 'N/A',
                'pid' => $order->pid ?? 'N/A',
                'push_url' => $pushUrl ?? 'N/A',
                'http_status' => $result['http_status'] ?? 0,
                'response' => $result['body'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 批量推送接口
     */
    public function batchPush(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        $orders = $this->model->whereIn('id', $ids)->get();

        $successCount = 0;
        foreach ($orders as $order) {
            if ($this->performPush($order)) {
                $successCount++;
            }
        }

        return $this->success(null, "处理完成，成功推送 {$successCount} 条");
    }
}
