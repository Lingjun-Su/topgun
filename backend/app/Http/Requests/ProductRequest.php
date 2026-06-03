<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * 确定用户是否有权发出此请求。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 获取应用于请求的验证规则。
     * * 场景：更新产品 (ID: 1)
     * URL: http://127.0.0.1:8000/api/v1/products/1
     */
    public function rules(): array
    {
        // 严谨：从路由中获取 ID 参数（对应 URL 末尾的 1）
        // 如果路由定义是 Route::apiResource('products', ...)，参数名通常是 'product'
        $productId = $this->route('product') ?? $this->route('id');
// error_log("product here");
        return [
            // 基础信息验证
            'business_id'   => 'required|integer|exists:business,id',
            'name'          => 'required|string|max:200',

            // SKU 唯一性核心逻辑
            'sku_code'      => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku_code')
                    ->where(function ($query) {
                        // 1. 严谨：只在当前业务单位内校验唯一性
                        return $query->where('business_id', $this->business_id)
                                     // 2. 严谨：忽略已经逻辑删除的旧数据
                                     ->whereNull('deleted_at');
                    })
                    // 3. 严谨：排除掉当前正在修改的这行数据（ID: 1）
                    ->ignore($productId),
            ],

            // 价格与数值验证
            'base_price'    => 'required|numeric|min:0',
            'unit'          => 'required|string|max:20',
            'pricing_model' => 'nullable|string',
            'pay_model'     => 'nullable|integer',
            'status'        => 'nullable|numeric|in:0,1',

            // 备注等其他字段
            'contents'      => 'nullable|string|max:1000',
            'specification' => 'nullable|string|max:500',

            // 审计字段（前端传入时校验）
            'updated_by'    => 'nullable|integer',
        ];
    }

    /**
     * 定制错误消息（可选）
     */
    public function messages(): array
    {
        return [
            'sku_code.unique' => '该业务单位下已存在相同的 SKU 编码。',
        ];
    }
}
