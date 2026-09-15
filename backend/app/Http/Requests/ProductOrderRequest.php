<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Services\ThirdChannel\InboundMapper;

class ProductOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 入站映射：新增（收单）路径在规则校验前，将外部方原始字段
     * 按渠道 receive_mapping.order 翻译为 B 内部参考模型字段并 merge 回请求。
     * 未配置映射时原样透传，兼容既有"直接内部字段协议"。
     */
    protected function prepareForValidation(): void
    {
        // 更新操作走标准字段协议，不做入站映射
        if ($this->route('order_no') !== null) {
            return;
        }

        $channel = $this->get('_authenticated_channel');
        if (! $channel) {
            return;
        }

        $mapping = $channel->receive_mapping['order'] ?? null;
        if (empty($mapping)) {
            return;
        }

        $raw = $this->all();
        $result = app(InboundMapper::class)->mapForReceive($raw, $mapping);

        if (! empty($result['mapped'])) {
            $this->merge($result['mapped']);
        }

        // 原始 payload 留痕：供 store() 写入 product_orders.ext_json
        if (! empty($result['raw'])) {
            $this->merge(['_inbound_raw' => $result['raw']]);
        }
    }

    /**
     * 注意：这是正式和测试共用的 Request
     */
    public function rules(): array
    {
        // 1. 获取基础表名
        $tableName = $this->route('env') === 'test' ? 'product_order_tests' : 'product_orders';

        // 2. 判断当前是否为更新操作
        // 假设你的路由定义是 Route::post('update/{order_no}', ...)
        $isUpdate = $this->route('order_no') !== null;

        // 3. 更新操作：仅需 order_no 和 order_status，其他字段可选
        if ($isUpdate) {
            // 更新时：pid 已由 X-PID 头和 channel.auth 中间件验证，无需在 body 中必填
            // 其余字段均非必填，仅校验格式（如果传了的话）
            $rules = [
                'pid' => 'nullable|string',
                'order_no' => 'required|string|max:100',
                'order_status' => 'required|integer|in:0,1,2,3',
                'user_phone' => 'nullable|string|max:16',
                'bus_code' => 'nullable|string|max:50',
                'sku_code' => 'nullable|string|max:50',
                'price' => 'nullable|numeric|min:0',
                'quantity' => 'nullable|integer|min:1',
                'total_amount' => 'nullable|numeric|min:0',
                'internal_amount' => 'nullable|numeric|min:0',
                'type' => 'nullable|string|max:10',
                'order_time' => 'nullable|date_format:Y-m-d H:i:s',
                'user_nick' => 'nullable|string|max:50',
                'user_status' => 'nullable|integer',
                'settle_status' => 'nullable|integer',
                'settleement_id' => 'nullable|integer',
                'province_code' => 'nullable|string|max:10',
                'city_code' => 'nullable|string|max:10',
            ];
        } else {
            // 新增时：所有必填字段严格校验
            $rules = [
                'pid' => 'required|string',
                'user_phone' => 'required|string|max:16',
                'bus_code' => 'required|string|max:50',
                'sku_code' => 'required|string|max:50',
                'price' => 'required|numeric|min:0',
                'quantity' => 'required|integer|min:1',
                'total_amount' => 'required|numeric|min:0',
                'internal_amount' => 'nullable|numeric|min:0',
                'type' => 'nullable|string|max:10',
                'order_time' => 'required|date_format:Y-m-d H:i:s',
                'user_nick' => 'nullable|string|max:50',
                'user_status' => 'nullable|integer',
                'settle_status' => 'nullable|integer',
                'settleement_id' => 'nullable|integer',
                'province_code' => 'nullable|string|max:10',
                'city_code' => 'nullable|string|max:10',
            ];

            // 执行严格的 PID 下唯一性校验
            $rules['order_no'] = [
                'required',
                'string',
                'max:100',
                Rule::unique($tableName)->where(function ($query) {
                    return $query->where('pid', $this->pid)
                        ->whereNull('deleted_at');
                }),
            ];
            $rules['order_status'] = 'nullable|integer|in:0,1,2,3';
        }

        return $rules;
    }

    /**
     * 自定义验证失败的返回格式，确保第三方调用时收到的是标准 JSON
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors()->first(),
        ], 422));
    }

    /**
     * 字段别名
     */
    public function attributes(): array
    {
        return [
            'order_no' => '订单号',
            'user_phone' => '用户手机',
            'bus_code' => '业务代码',
            'sku_code' => '产品代码',
            'price' => '单价',
            'total_amount' => '总金额',
            'quantity' => '数量',
            'order_status' => '订单状态',
            'type' => '类型',
            'order_time' => '订单时间',
        ];
    }
}
