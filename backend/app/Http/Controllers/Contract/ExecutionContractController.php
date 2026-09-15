<?php

namespace App\Http\Controllers\contract;

use App\Enums\ContractStatus as ExecutionContractStatus;
use App\Http\Controllers\Controller;
use App\Models\ExecutionContract;
use App\Services\Contract\ExecutionContractService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 执行合同核心业务控制器
 *
 * 遵从 RESTful 规范，集成双层状态监控响应与严格的权限审计双重拦截
 */
class ExecutionContractController extends Controller
{
    use ApiResponse; // 注入统一的 ApiResponse 插件（success, partial, error）[cite: 1]

    protected ExecutionContractService $contractService;

    /**
     * 构造函数注入业务服务层 (Service)
     */
    public function __construct(ExecutionContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * 1. 列表查询 (支持 SQL Server 分页与条件筛选)
     * GET /api/execution-contracts
     */
    public function index(Request $request)
    {
        // 验证查询参数
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ExecutionContractStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        // 业务下沉至 Service 层执行多表关联与 T-SQL 优化查询
        $data = $this->contractService->getPaginatedList($validated, $perPage);

        return $this->success($data, '数据查询成功'); // 返回标准 200 成功结构[cite: 1]
    }

    /**
     * 2. 新增执行合同
     * POST /api/execution-contracts
     */
    public function store(Request $request)
    {
        // 严格的表单参数校验
        $validated = $request->validate([
            'contract_no' => ['required', 'string', 'max:50', 'unique:execution_contracts,contract_no'],
            'title' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'party_a' => ['required', 'string', 'max:100'],
            'party_b' => ['required', 'string', 'max:100'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'description' => ['nullable', 'string'],
        ]);

        // 新增操作的身份鉴权 (调用 Policy)
        $this->authorize('create', ExecutionContract::class);

        // 默认初始状态为草稿
        $validated['status'] = ExecutionContractStatus::DRAFT;
        $validated['creator_id'] = $request->user()->id;

        // 执行保存
        $contract = $this->contractService->createContract($validated);

        return $this->success($contract, '执行合同创建成功');
    }

    /**
     * 3. 详情查看
     * GET /api/execution-contracts/{id}
     */
    public function show(int $id)
    {
        // findOrFail 在数据不存在或已被软删除时会自动抛出 ModelNotFoundException (404)
        $contract = ExecutionContract::findOrFail($id);

        // 鉴权：检查当前登录用户是否有查看此合同的权限
        $this->authorize('view', $contract);

        return $this->success($contract, '详情获取成功');
    }

    /**
     * 4. 更新修改
     * PUT /api/execution-contracts/{id}
     */
    public function update(Request $request, int $id)
    {
        $contract = ExecutionContract::findOrFail($id);

        // 联合拦截：先通过 Policy 校验身份与状态（第一道防线 canPerform('update') 将内嵌在 Policy 中）
        $this->authorize('update', $contract);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'party_a' => ['required', 'string', 'max:100'],
            'party_b' => ['required', 'string', 'max:100'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'description' => ['nullable', 'string'],
        ]);

        $updatedContract = $this->contractService->updateContract($contract, $validated);

        return $this->success($updatedContract, '合同数据更新成功');
    }

    /**
     * 5. 流程状态动作：提交审核
     * POST /api/execution-contracts/{id}/submit
     */
    public function submit(Request $request, int $id)
    {
        $contract = ExecutionContract::findOrFail($id);

        // 双重防线拦截（Policy 内部会优先调用 $contract->canPerform('submit')）
        $this->authorize('submit', $contract);

        // 变更状态至“审批中”，该写操作会被 audits 审计表完美记录
        $result = $this->contractService->changeStatus($contract, ExecutionContractStatus::APPROVING, '提交合同进入审批流程');

        return $this->success($result, '合同提交审核成功');
    }

    /**
     * 6. 流程状态动作：审批通过
     * POST /api/execution-contracts/{id}/approve
     */
    public function approve(Request $request, int $id)
    {
        $contract = ExecutionContract::findOrFail($id);

        // 权限拦截：当前登录人必须是审核人，且状态处于“审批中”
        $this->authorize('approve', $contract);

        $comment = $request->input('comment', '审批通过');

        // 使用数据库事务确保流转和业务日志的一致性
        $result = DB::transaction(function () use ($contract, $comment) {
            return $this->contractService->changeStatus($contract, ExecutionContractStatus::EFFECTIVE, $comment);
        });

        return $this->success($result, '合同审批通过，已正式生效');
    }

    /**
     * 7. 流程状态动作：审批驳回
     * POST /api/execution-contracts/{id}/reject
     */
    public function reject(Request $request, int $id)
    {
        $contract = ExecutionContract::findOrFail($id);

        $this->authorize('reject', $contract);

        $request->validate(['comment' => ['required', 'string', 'max:500']]);
        $comment = $request->input('comment');

        // 驳回后合同状态回滚到“已驳回”，允许用户重新修改提交
        $result = $this->contractService->changeStatus($contract, ExecutionContractStatus::REJECTED, $comment);

        return $this->success($result, '合同已被驳回');
    }

    /**
     * 8. 逻辑删除 (Soft Delete)
     * DELETE /api/execution-contracts/{id}
     */
    public function destroy(int $id)
    {
        $contract = ExecutionContract::findOrFail($id);

        // 校验：只有特定身份（如创建者）且在“草稿/驳回”状态下才能执行删除
        $this->authorize('delete', $contract);

        // 调用 Eloquent 的 delete() 方法。
        // 由于 Model 中 use 了 SoftDeletes 并且集成了 OwenIt\Auditing[cite: 1]，
        // 框架会自动执行 UPDATE 语句将 deleted_at 设为当前时间，并在 audits 表中留下严谨的审计凭证[cite: 1]。
        $this->contractService->deleteContract($contract);

        return $this->success([], '合同已安全移入回收站');
    }
}
