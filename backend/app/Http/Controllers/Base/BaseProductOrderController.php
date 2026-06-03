<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

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
        $query->when($request->bus_code, fn($q, $v) => $q->where('bus_code', $v))
              ->when($request->sku_code, fn($q, $v) => $q->where('sku_code', $v));

        // 3. 状态筛选
        $query->when($request->filled('sync_status'), fn($q, $v) => $q->where('sync_status', $request->sync_status));

        // 4. 时间范围筛选 (针对 SQL Server 索引优化)
        if ($request->filled('date_range') && is_array($request->date_range)) {
            $query->whereBetween('created_at', [$request->date_range[0], $request->date_range[1]]);
        }

        // 分页返回
        $perPage = $request->get('per_page', 15);
        $data = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'code' => 200,
            'data' => $data,
            'msg'  => 'success'
        ]);
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

        return response()->json([
            'code' => 201,
            'data' => $order,
            'msg'  => '创建成功'
        ], 201);
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

        return response()->json([
            'code' => 200,
            'data' => $order,
            'audits' => $audits,
            'msg' => 'success'
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

        return response()->json([
            'code' => 200,
            'data' => $order,
            'msg'  => '更新成功'
        ]);
    }

    /**
     * 逻辑删除
     */
    public function destroy($id): JsonResponse
    {
        $order = $this->model->findOrFail($id);

        // 执行逻辑删除 (Eloquent 自动处理 SoftDeletes)
        $order->delete();

        return response()->json([
            'code' => 200,
            'msg'  => '数据已移至回收站'
        ]);
    }

    /**
     * 推送逻辑 (单条/批量共用基础)
     * 这里定义一个受保护的方法，供具体实现调用
     */
    protected function performPush($order)
    {
        // 1. 检查状态
        if ($order->sync_status === 1) return true;

        try {
            // 模拟向上家 API 推送
            // $response = Http::post('upstream-url', $order->toArray());

            // 2. 更新状态
            $order->update([
                'sync_status' => 1,
                'pushed_at'   => now(),
                'sync_error'  => null
            ]);
            return true;
        } catch (\Exception $e) {
            $order->update([
                'sync_status' => 2,
                'sync_error'  => $e->getMessage()
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
            if ($this->performPush($order)) $successCount++;
        }

        return response()->json([
            'code' => 200,
            'msg'  => "处理完成，成功推送 {$successCount} 条"
        ]);
    }
}
