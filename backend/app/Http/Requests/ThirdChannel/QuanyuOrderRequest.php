<?php

namespace App\Http\Requests\ThirdChannel;

use App\Models\QuanyuOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuanyuOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 关键点：在验证规则执行前准备数据
     */
    protected function prepareForValidation()
    {
        // 强制初始化，防止外部伪造该字段
        $this->merge(['_internal_id' => null]);
        $order_no = $this->input('data.order_no');
        if ($order_no && is_string($order_no)) {
            $existingOrder = QuanyuOrder::where('order_no', $order_no)
                ->whereNull('deleted_at')
                ->first();

            if ($existingOrder) {
                $this->merge(['_internal_id' => $existingOrder->id]);
            }
        }
    }

    /**
     * 严格匹配您提供的参数表
     */
    public function rules(): array
    {

        // 获取刚才注入的内部 ID
        $internalId = $this->input('_internal_id');

        return [
            'data.mobile' => 'required|string|max:20',
            'data.pid' => 'required|string|max:50',
            'data.bus_code' => 'required|string|max:50',
            'data.sku_code' => 'required|string|max:100',
            'data.order_no' => ['required', 'string', Rule::unique('quanyu_orders', 'order_no')->ignore($internalId)],
            'data.create_time' => 'required|string|size:10', // 10位时间戳
            'data.type' => 'required|in:1,2',         // 1:平安健康 2:商超
            'data.order_status' => 'in:0,1',         // 订单状态 0正常 1退订
            'data.sync_status' => 'sometime|in:0,1,2',       // 同步状态 0未同步 1同步成功 2同步失败
            'data.cancel_sync_status' => 'sometime|in:0,1,2',     // 退订状态 0未同步 1同步成功 2同步失败
            'data.platform' => 'required|string',
            'data.pack' => 'required|string',
            'data.url' => 'required|string',
            'data.ip' => 'required|ip',
            'data.sms_time' => 'required|string|size:10',
            'data.code' => 'required|string|max:20',
        ];
    }

    public function messages(): array
    {
        $internalId = $this->input('_internal_id');
        $order_no = $this->filled('data.order_no');
        if ($internalId == null) {
            $internalId = 'nullf';
        }

        return [
            // 'pid.exists' => '渠道标识不存在',
            'data.order_no' => $internalId.'f订单号不能重复输入'.$order_no,
        ];
    }
}
