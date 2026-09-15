<?php

namespace App\Http\Controllers\API;

use App\Exports\ProductOrderExport;
use App\Http\Controllers\Base\BaseProductOrderController;
use App\Http\Requests\ProductOrderRequest;
use App\Models\ProductOrder;
use App\Repositories\ProductOrder\ProductOrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 产品订单控制器
 * 使用 Repository 模式解耦测试/生产环境代码
 */
class ProductOrderController extends BaseProductOrderController
{
    protected ProductOrderRepository $repository;

    /**
     * 构造函数：注入模型供 BaseProductOrderController 的 performPush/batchPush 使用
     */
    public function __construct()
    {
        parent::__construct(new ProductOrder());
    }

    /**
     * 根据环境获取对应的 Repository 实例
     */
    protected function resolveRepository(Request $request): ProductOrderRepository
    {
        $env = $request->route('env', 'prod');

        return new ProductOrderRepository($env);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $repository = $this->resolveRepository($request);

        $filters = $request->only(['business_id', 'product_id', 'order_no', 'user_phone', 'pid', 'order_status', 'sync_status', 'start_date', 'end_date']);
        $filters['per_page'] = $request->get('per_page', 15);

        $data = $repository->index($filters);

        return $this->success($data);
    }

    /**
     * 导出当前筛选条件下所有订单到 Excel
     */
    public function export(Request $request)
    {
        $repository = $this->resolveRepository($request);

        $filters = $request->only(['business_id', 'product_id', 'order_no', 'user_phone', 'pid', 'order_status', 'sync_status', 'start_date', 'end_date']);

        $filename = 'product-orders-' . date('YmdHis') . '.xlsx';

        return Excel::download(new ProductOrderExport($filters, $repository->getEnvironment()), $filename);
    }

    /**
     * 订单状态统计：按当前筛选条件统计 link_id/code 分布
     */
    public function statusStats(Request $request)
    {
        $repository = $this->resolveRepository($request);

        $filters = $request->only(['business_id', 'product_id', 'order_no', 'user_phone', 'pid', 'order_status', 'sync_status', 'start_date', 'end_date']);

        $stats = $repository->statusStats($filters);

        return $this->success($stats);
    }
}
