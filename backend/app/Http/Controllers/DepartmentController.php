<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    /**
     * 显示部门列表（可按组织过滤）
     * GET /api/departments
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()
            ->with(['organization', 'parent', 'positions' => fn($q) => $q->withTrashed(false)])
            ->withCount('positions');

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $departments = $query->orderBy('name')->get();
        return $this->success($department,'部门列表获取成功');
    }

    /**
     * 创建部门
     * POST /api/departments
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'code'             => 'nullable|string|max:50|unique:departments,code',
            'organization_id'  => 'required|exists:organizations,id',
            'parent_id'        => 'nullable|exists:departments,id',
            'head_position_id' => 'nullable|exists:positions,id',
            'description'      => 'nullable|string',
            'is_active'        => 'boolean',
        ]);

        // 校验 parent_id 是否属于同一 organization
        if (!empty($validated['parent_id'])) {
            $parent = Department::findOrFail($validated['parent_id']);
            if ($parent->organization_id !== $validated['organization_id']) {
                throw ValidationException::withMessages([
                    'parent_id' => '上级部门必须属于同一组织。',
                ]);
            }
        }

        $data = Department::create($validated);
        return $this->success($data,'部门创建成功');
    }

    /**
     * 显示单个部门详情
     * GET /api/departments/{id}
     */
    public function show(Department $department): JsonResponse
    {
        $data->load([
            'organization',
            'parent',
            'children' => fn($q) => $q->withTrashed(false),
            'positions',
            'headPosition',
        ]);
        return $this->success($data,'取得部门详情成功');
    }

    /**
     * 更新部门
     * PUT/PATCH /api/departments/{id}
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:100',
            'code'             => 'nullable|string|max:50|unique:departments,code,' . $department->id,
            'organization_id'  => 'sometimes|exists:organizations,id',
            'parent_id'        => 'nullable|exists:departments,id',
            'head_position_id' => 'nullable|exists:positions,id',
            'description'      => 'nullable|string',
            'is_active'        => 'boolean',
        ]);

        $department->update($validated);
        return $this->success($department->fresh(['organization', 'parent']),'部门信息更新成功');
    }

    /**
     * 逻辑删除部门
     * DELETE /api/departments/{id}
     */
    public function destroy(Department $department): JsonResponse
    {
        try {
            DB::beginTransaction();

            $department->delete(); // 触发 booted deleting 校验

            DB::commit();
            return $this->success(null,'部门已删除');

        } catch (\Exception $e) {
            DB::rollBack();

            $message = $e->getMessage();

            if (str_contains($message, '存在下级') || str_contains($message, '存在岗位')) {
                return $this->error('存在关联数据，无法删除');
            }
            return $this->error('删除失败');
        }
    }
}
