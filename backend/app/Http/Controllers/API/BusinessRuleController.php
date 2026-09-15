<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusinessRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 业务规则管理控制器
 * CRUD 操作 business_rules 规则配置
 */
class BusinessRuleController extends Controller
{
    /**
     * 获取规则列表（分页）
     */
    public function index(Request $request): JsonResponse
    {
        $query = BusinessRule::query()
            ->with(['channel', 'business', 'product']);

        // 按渠道筛选
        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        // 按业务筛选
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->input('business_id'));
        }

        // 按产品筛选
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rules = $query->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->success($rules);
    }

    /**
     * 获取单条规则详情
     */
    public function show(int $id): JsonResponse
    {
        $rule = BusinessRule::with(['channel', 'business', 'product'])->find($id);

        if (! $rule) {
            return $this->error('规则不存在', 404);
        }

        return $this->success($rule);
    }

    /**
     * 新增规则
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'channel_id' => 'nullable|integer',
            'business_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'conditions' => 'nullable|array',
            'actions' => 'required|array',
            'priority' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $rule = BusinessRule::create($data);

        return $this->success($rule, '规则创建成功');
    }

    /**
     * 更新规则
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rule = BusinessRule::find($id);

        if (! $rule) {
            return $this->error('规则不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'channel_id' => 'nullable|integer',
            'business_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'conditions' => 'nullable|array',
            'actions' => 'sometimes|array',
            'priority' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $rule->update($data);

        return $this->success($rule, '规则更新成功');
    }

    /**
     * 删除规则
     */
    public function destroy(int $id): JsonResponse
    {
        $rule = BusinessRule::find($id);

        if (! $rule) {
            return $this->error('规则不存在', 404);
        }

        $rule->delete();

        return $this->success(null, '规则删除成功');
    }
}