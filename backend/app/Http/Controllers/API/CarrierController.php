<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarrierRequest;
use App\Models\Carrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    /**
     * 列表查询（支持分页与关键词搜索）
     */
    public function index(Request $request): JsonResponse
    {
        $query = Carrier::query();

        if ($request->filled('keyword')) {
            $query->where('name', 'like', "%{$request->keyword}%")
                ->orWhere('code', 'like', "%{$request->keyword}%");
        }

        $data = $query->orderBy('id', 'asc')->paginate($request->get('per_page', 15));

        return $this->success($data);
    }

    /**
     * 新增运营商
     */
    public function store(CarrierRequest $request): JsonResponse
    {
        $data = Carrier::create($request->validated());

        return $this->success($data, '创建成功');
    }

    /**
     * 详情
     */
    public function show($id): JsonResponse
    {
        $data = Carrier::findOrFail($id);

        return $this->success($data);
    }

    /**
     * 修改
     */
    public function update(CarrierRequest $request, $id): JsonResponse
    {
        $data = Carrier::findOrFail($id);
        $data->update($request->validated());

        return $this->success($data, '更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy($id): JsonResponse
    {
        $data = Carrier::findOrFail($id);
        $data->delete(); // 触发 Model 中的 deleting 钩子

        return $this->success($data, '删除成功');
    }
}
