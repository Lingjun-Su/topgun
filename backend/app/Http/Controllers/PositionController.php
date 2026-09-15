<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PositionController extends Controller
{
    /**
     * 岗位列表（可按部门或组织过滤）
     * GET /api/positions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query()
            ->with(['department.organization', 'organization'])
            ->withCount('assignments');

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        }

        $positions = $query->orderBy('name')->get();

        return $this->success($positions);
    }

    /**
     * 创建岗位
     * POST /api/positions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:positions,code',
            'department_id' => 'nullable|exists:departments,id',
            'organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // 至少有一个归属（部门或组织）
        if (empty($validated['department_id']) && empty($validated['organization_id'])) {
            return $this->error('岗位必须至少隶属于一个部门或组织');
        }

        $position = Position::create($validated);

        return $this->success($position->load(['department', 'organization']));
    }

    /**
     * 显示单个岗位
     * GET /api/positions/{id}
     */
    public function show(Position $position): JsonResponse
    {
        $position->load([
            'department.organization',
            'organization',
            'assignments.employee' => fn ($q) => $q->withTrashed(false),
        ]);

        return $this->success($position);
    }

    /**
     * 更新岗位
     * PUT/PATCH /api/positions/{id}
     */
    public function update(Request $request, Position $position): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'nullable|string|max:50|unique:positions,code,'.$position->id,
            'department_id' => 'nullable|exists:departments,id',
            'organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $position->update($validated);

        return $this->success($position->fresh(['department', 'organization']));
    }

    /**
     * 逻辑删除岗位
     * DELETE /api/positions/{id}
     */
    public function destroy(Position $position): JsonResponse
    {
        try {
            DB::beginTransaction();

            $position->delete(); // 触发 booted deleting

            DB::commit();

            return $this->success(null, '删除成功');

        } catch (\Exception $e) {
            DB::rollBack();

            $message = $e->getMessage();

            if (str_contains($message, '存在员工任职记录')) {
                return $this->error('存在关联任职，无法删除');
            }

            return $this->error('删除失败');
        }
    }
}
