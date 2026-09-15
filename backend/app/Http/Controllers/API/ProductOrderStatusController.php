<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductOrderStatus; // 基础 Request
use App\Models\ProductOrderStatusTest;
use Illuminate\Http\Request;

class ProductOrderStatusController extends Controller
{
    /**
     * 获取订单状态轨迹
     */
    public function index(Request $request, $order_id)
    {
        $env = $request->route('env', 'prod');
        $modeProdcutOrderStatus = $env == 'test' ? ProductOrderStatusTest::class : ProductOrderStatus::class;
        if (! $order_id) {
            return $this->error('需要有订单ID');
        }

        // 2. 执行查询
        $data = $modeProdcutOrderStatus::where('order_id', $order_id)
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 10));

        return $this->success($data);
    }
}
