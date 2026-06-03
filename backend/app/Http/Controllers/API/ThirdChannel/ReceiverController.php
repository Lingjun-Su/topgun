<?php

namespace App\Http\Controllers\Api\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductOrderRequest;
use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use App\Jobs\AsyncOrderAuditJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ReceiverController extends Controller
{
    /**
     * 新增订单
     */
    public function store(ProductOrderRequest $request)
    {
        // 1. 从中间件获取已验证的渠道信息
        $channel = $request->get('_authenticated_channel');
        $isTestEnv = $request->get('_is_test_env');

        if (!$channel) {
            return $this->error('未通过身份验证', 401);
        }

        // 2. 获取已验证的业务数据
        $data = $request->validated();

        // 3. 业务链路校验（产品授权、SKU、业务码）
        $product = $this->verifyProductAccess($channel, $data);
        if (!$product) {
            return $this->error('未经授权的产品或业务', 422);
        }

        // 4. 选择正式表或测试表
        $modelClass = $isTestEnv ? ProductOrderTest::class : ProductOrder::class;

        try {
            $quantity = (int)($data['quantity'] ?? 1);
            $internalAmount = $product->base_price * $quantity;

            $order = $modelClass::create(array_merge($data, [
                'pid'             => $channel->pid,
                'sync_status'     => 0,
                'ip'              => $request->ip(),
                'order_time'      => $data['order_time'] ?? now(),
                'internal_amount' => $internalAmount,
                'product_id'      => $product->product_id,
                'business_id'     => $product->business_id,
                'channel_id'      => $product->channel_id,
                'organization_id' => $channel->organization_id,
            ]));

            // 异步审计
            $this->dispatchAsyncAudit($order, $channel, $isTestEnv, 'created');

            return $this->success($order, '订单录入成功');
        } catch (QueryException $e) {
            // 唯一约束冲突（订单号重复）
            if (in_array($e->getCode(), ['23000', '2601', '2627'])) {
                return $this->error('重复的订单号', 409);
            }
            throw $e;
        }
    }

    /**
     * 更新订单状态
     */
    public function update(ProductOrderRequest $request)
    {
        // 1. 从中间件获取已验证的渠道信息
        $channel = $request->get('_authenticated_channel');
        $isTestEnv = $request->get('_is_test_env');

        if (!$channel) {
            return $this->error('未通过身份验证', 401);
        }

        // 2. 获取已验证的业务数据
        $data = $request->validated();

        // 3. 选择表模型
        $modelClass = $isTestEnv ? ProductOrderTest::class : ProductOrder::class;
        $statusTable = $isTestEnv ? 'product_order_status_tests' : 'product_order_statuses';

        // 4. 查询订单（不加锁）
        $order = $modelClass::where('order_no', $data['order_no'])
            ->where('pid', $channel->pid)
            ->first();

        if (!$order) {
            return $this->error('未找到对应订单', 404);
        }

        $newStatus = isset($data['order_status']) ? (int)$data['order_status'] : $order->order_status;

        if ($order->order_status !== $newStatus) {
            $oldStatus = $order->order_status;

            // 更新主表
            $order->order_status = $newStatus;
            $order->save();

            // 准备状态流水数据
            $statusData = [
                'table'        => $statusTable,
                'order_id'     => $order->id,
                'pid'          => $channel->pid,
                'order_no'     => $order->order_no,
                'old_status'   => $oldStatus,
                'new_status'   => $newStatus,
                'price'        => $order->price,
                'total_amount' => $order->total_amount,
                'operator'     => 'API_CALLBACK',
                'created_at'   => now(),
            ];

            // 异步记录状态变更
            $this->dispatchAsyncAudit($order, $channel, $isTestEnv, 'status_updated', $statusData);

            return $this->success($order, '修改成功');
        }

        return $this->success(null, '状态没有改变');
    }

    /**
     * 业务链路校验：验证渠道是否有权销售该 SKU 和业务码
     *
     * @param \App\Models\ThirdChannels $channel
     * @param array $data
     * @return object|null
     */
    private function verifyProductAccess($channel, array $data)
    {
        $cacheKey = "auth_chain:{$channel->id}:{$data['sku_code']}:{$data['bus_code']}";

        $product = Cache::remember($cacheKey, 3600, function () use ($channel, $data) {
            return DB::table('channel_products')
                ->join('products', 'channel_products.product_id', '=', 'products.id')
                ->join('business', 'products.business_id', '=', 'business.id')
                ->where('channel_products.channel_id', $channel->id)
                ->where('products.sku_code', $data['sku_code'])
                ->where('products.status', 1)
                ->where('business.code', $data['bus_code'])
                ->first(['channel_id', 'product_id', 'business_id', 'base_price']);
        });

        return $product;
    }

    /**
     * 派发异步审计任务
     *
     * @param mixed $model
     * @param \App\Models\ThirdChannels $channel
     * @param bool $isTestEnv
     * @param string $event
     * @param array $statusData
     * @return void
     */
    protected function dispatchAsyncAudit($model, $channel, $isTestEnv, $event, $statusData = [])
    {
        $auditData = [
            'model_type' => get_class($model),
            'model_id'   => $model->id,
            'event'      => $event,
            'new_values' => json_encode($model->getAttributes(), JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'tags'       => "env:" . ($isTestEnv ? 'test' : 'prod') . "|pid:{$channel->pid}",
            'created_at' => now(),
        ];

        AsyncOrderAuditJob::dispatch($auditData, $statusData)->onQueue('low');
    }
}
