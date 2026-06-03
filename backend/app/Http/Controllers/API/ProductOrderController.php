<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use Illuminate\Http\Request;
use App\Http\Requests\ProductOrderRequest;
/**
 * 产品表
 */
class ProductOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $env)
    {
        // 1. 动态选择模型
        $modelClass = ($env === 'test') ? ProductOrderTest::class : ProductOrder::class;

        // 2. 构建查询
        $query = $modelClass::query()
            ->with('ThirdChannel')->with('products')->with('business')
            // 过滤业务 ID (对应前端的 filterModel.bus_id)
            ->when($request->filled('business_id'), function ($q) use ($request) {
                return $q->where('business_id', $request->input('business_id'));
            })
            // 过滤产品标识 (对应前端的 filterModel.sku_code)
            ->when($request->filled('product_id'), function ($q) use ($request) {
                return $q->where('product_id', $request->input('product_id'));
            })
            // 过滤订单号
            ->when($request->filled('order_no'), function ($q) use ($request) {
                return $q->where('order_no', 'like', '%' . $request->input('order_no') . '%');
            })
            // 过滤手机号
            ->when($request->filled('user_phone'), function ($q) use ($request) {
                return $q->where('user_phone', $request->input('user_phone'));
            })
            ->orderBy('id', 'desc');

        // 3. 执行分页
        // 注意：前端传入的是 per_page，默认值设为 15 以匹配前端 pagination 定义
        $data = $query->paginate($request->get('per_page', 15));

        return $this->success($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductOrderRequest $request)
    {
        $product = order_product::create($request->validated());
        return $this->success($product,"订单创建成功");
    }

    /**
     * Display the specified resource.
     */
    public function show($id,$env)
    {
        $modelClass = ($env === 'test') ? ProductOrderTest::class : ProductOrder::class;
       $data = $modelClass::findOrFail($id);
        return $this->success($data);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(OrderProductRequest $request, order_product $order_product)
    {

        $product = $order_product->update($request->validated());
        return $this->success($product,'更新成功');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(order_product $order_product)
    {
        //
    }
}
