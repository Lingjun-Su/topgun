<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    /**
     * 创建员工 + 初始任职记录
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:50',
            'employee_no'           => 'required|string|max:30|unique:employees',
            'id_card'               => 'nullable|string|max:20|unique:employees',
            'mobile'                => 'nullable|string|max:20',
            'entry_date'            => 'nullable|date',
            // 初始任职记录（可选）
            'assignments'           => 'array',
            'assignments.*.position_id'    => 'required|exists:positions,id',
            'assignments.*.organization_id'=> 'required|exists:organizations,id',
            'assignments.*.is_primary'     => 'boolean',
            'assignments.*.start_date'     => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $employee = Employee::create($validated);

            // 创建任职记录
            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $assign) {
                    EmployeeAssignment::create([
                        'employee_id'     => $employee->id,
                        'position_id'     => $assign['position_id'],
                        'organization_id' => $assign['organization_id'],
                        'department_id'   => $assign['department_id'] ?? null,
                        'start_date'      => $assign['start_date'],
                        'is_primary'      => $assign['is_primary'] ?? true,
                        'employment_type' => $assign['employment_type'] ?? 'full_time',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'data'    => $employee->load('assignments.position'),
                'message' => '员工创建成功',
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '创建失败：' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 离职 / 结束任职（不直接删除员工）
     * PATCH /api/employees/{id}/leave
     */
    public function leave(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'leave_date' => 'required|date',
            'reason'     => 'nullable|string',
        ]);

        $employee->update([
            'leave_date' => $validated['leave_date'],
            'is_active'  => false,
        ]);

        // 结束所有未结束的任职记录
        $employee->assignments()
            ->whereNull('end_date')
            ->update([
                'end_date' => $validated['leave_date'],
            ]);

        return response()->json([
            'message' => '员工已标记离职，所有任职记录已结束',
        ]);
    }

    // destroy 方法类似 OrganizationController，捕获模型异常
}
