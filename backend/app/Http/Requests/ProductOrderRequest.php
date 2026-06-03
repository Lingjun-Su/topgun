<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

        $rules = [
            'pid'          => 'required|string',//渠道ID
            'user_phone'   => 'required|string|max:16',//用户手机
            'bus_code'     => 'required|string|max:50',//业务代码
            'sku_code'     => 'required|string|max:50',//产品代码
            'price'        => 'required|numeric|min:0',//单价
            'quantity'     => 'required|integer|min:1',//数量
            'total_amount' => 'required|numeric|min:0',//小计
            'internal_amount' => 'nullable|numeric|min:0',//内部小计
            'type'         => 'nullable|string|max:10',//类型
            'order_time'   => 'required|date_format:Y-m-d H:i:s',//订购时间
            'user_nick'    => 'nullable|string|max:50',//用户昵称
            'user_status'  => 'nullable|integer',//用户状态
            'settle_status'=> 'nullable|integer',//结算状态
            'settleement_id'=>'nullable|integer',//结算ID
            'province_code'=> 'nullable|string|max:10',//省代码，如44
            'city_code'    => 'nullable|string|max:10',//市代码，如4401

        ];

        // 3. 针对 order_no 的特殊处理
        if ($isUpdate) {
            // 更新时：order_no 必须存在，但不需要 unique 校验（因为我们就是根据它来找的）
            $rules['order_no'] = 'required|string|max:100';
            $rules['order_status'] = 'required|integer|in:0,1,2,3'; // 更新时状态必填
        } else {
            // 新增时：执行严格的 PID 下唯一性校验
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
            'code'    => 422,
            'message' => $validator->errors()->first(),
        ], 422));
    }

    /**
     * 字段别名
     */
    public function attributes(): array
    {
        return [
            'order_no'   => '订单号',
            'user_phone' => '用户手机',
            'bus_code'   => '业务代码',
            'sku_code'   => '产品代码',
            'price'      => '单价',
            'total_amount' => '总金额',
            'quantity'   => '数量',
            'order_status' => '订单状态',
            'type'       => '类型',
            'order_time'=>'订单时间',
        ];
    }
}
