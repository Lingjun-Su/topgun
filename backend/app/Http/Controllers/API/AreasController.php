<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Areas;
use App\Http\Requests\AreasRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreasController extends Controller
{
    /**
     * 列表查询（支持分页与关键词搜索）
     */
    public function index(AreasRequest $request): JsonResponse
    {
        $query = Areas::query();

        if ($request->filled('keyword')) {
            $query->where('name', 'like', "%{$request->keyword}%")
                  ->orWhere('code', 'like', "%{$request->keyword}%");
        }
        if($request->filled('level')){
            $query->where('level',$request->level);
        }

        if($request->filled('parent_code')){
            $query->where('parent_code',$request->parent_code);
        }

        $list = $query->orderBy('code', 'asc')->paginate($request->get('per_page', 100));

        return $this->success($list);
    }

    /**
     * 新增运营商
     */
    public function store(CarrierRequest $request): JsonResponse
    {
        $data = Carrier::create($request->validated());
        return $this->success($data,'创建成功');
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
        return $this->success($data,'更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy($id): JsonResponse
    {
        $data = Carrier::findOrFail($id);
        $data->delete(); // 触发 Model 中的 deleting 钩子
        return $this->success($data,'删除成功');
    }
}
