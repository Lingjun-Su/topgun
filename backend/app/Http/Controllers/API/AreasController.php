<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreasRequest;
use App\Models\Areas;
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
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('parent_code')) {
            $query->where('parent_code', $request->parent_code);
        }

        $list = $query->orderBy('code', 'asc')->paginate($request->get('per_page', 100));

        return $this->success($list);
    }

    /**
     * 新增区域数据
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'level' => 'required|integer|min:0|max:3',
            'parent_code' => 'nullable|string|max:50',
        ]);
        $data = Areas::create($validated);

        return $this->success($data, '创建成功');
    }

    /**
     * 详情
     */
    public function show($id): JsonResponse
    {
        $data = Areas::findOrFail($id);

        return $this->success($data);
    }

    /**
     * 修改
     */
    public function update(Request $request, $id): JsonResponse
    {
        $data = Areas::findOrFail($id);
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:100',
            'level' => 'sometimes|required|integer|min:0|max:3',
            'parent_code' => 'nullable|string|max:50',
        ]);
        $data->update($validated);

        return $this->success($data, '更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy($id): JsonResponse
    {
        $data = Areas::findOrFail($id);
        $data->delete();

        return $this->success($data, '删除成功');
    }
}
