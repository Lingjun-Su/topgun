<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EmployeeAssignmentController extends Controller
{
    /**
     * 任职记录列表（可按员工、岗位、组织过滤）
     * GET /api/employee-assignments
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeAssignment::query()
            ->with([
                'employee',
                'position',
                'organization',
                'department',
            ])
            ->orderByDesc('start_date');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // 默认只显示未结束的（或历史全部）
        if (!$request->boolean('show_history', false)) {
            $query->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now()->startOfDay());
            });
        }

        $assignments = $query->get();
        return $this->success($assignments,'任职记录获取成功');
    }

    /**
     * 为员工新增/调整任职记录
     * POST /api/employee-assignments
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'position_id'     => 'required|exists:positions,id',
            'organization_id' => 'required|exists:organizations,id',
            'department_id'   => 'nullable|exists:departments,id',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'is_primary'      => 'boolean',
            'employment_type' => 'in:full_time,part_time,outsourced,temporary',
            'salary_ratio'    => 'nullable|numeric|between:0,1',
            'remarks'         => 'nullable|string',
        ]);

        // 校验岗位是否属于该组织（或其部门）
        $position = \App\Models\Position::findOrFail($validated['position_id']);
        if ($position->organization_id && $position->organization_id != $validated['organization_id']) {
            if (!$position->department || $position->department->organization_id != $validated['organization_id']) {
                return response()->json([
                    'message' => '岗位不属于指定的组织',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // 如果设置为主要职位，可先把该员工其他主要职位改为非主要（视业务决定是否需要）
        if ($validated['is_primary'] ?? false) {
            EmployeeAssignment::where('employee_id', $validated['employee_id'])
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $assignment = EmployeeAssignment::create($validated);
        return $this->success($assignment->load(['employee', 'position', 'organization']),'任职记录创建成功');
    }

    /**
     * 更新任职记录（例如结束日期、调岗备注等）
     * PUT/PATCH /api/employee-assignments/{id}
     */
    public function update(Request $request, EmployeeAssignment $employeeAssignment): JsonResponse
    {
        $validated = $request->validate([
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'is_primary'      => 'boolean',
            'employment_type' => 'in:full_time,part_time,outsourced,temporary',
            'salary_ratio'    => 'nullable|numeric|between:0,1',
            'remarks'         => 'nullable|string',
        ]);

        $employeeAssignment->update($validated);
        return $this->success($employeeAssignment->fresh(),'任职记录更新成功');
    }

    /**
     * 逻辑删除任职记录（一般用于清理错误录入）
     * DELETE /api/employee-assignments/{id}
     * 注意：模型 booted() 可选择禁止删除历史记录
     */
    public function destroy(EmployeeAssignment $employeeAssignment): JsonResponse
    {
        try {
            $employeeAssignment->delete();
            return $this->success(null,'任职记录已经删除');

        } catch (\Exception $e) {
            return $this->error('删除失败');
        }
    }
}
