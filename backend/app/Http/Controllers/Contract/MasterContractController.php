<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreMasterContractRequest;
use App\Http\Requests\Contract\UpdateMasterContractRequest;
use App\Models\Contract\ExecutionContract;
use App\Models\Contract\MasterContract;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterContractController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    /**
     * 核心字段黑名单（生效后不可修改）
     */
    private const CORE_FIELDS = [
        'contract_no', 'total_limit', 'org_a_id', 'org_b_id',
        'effective_date', 'signed_date',
    ];

    /**
     * 辅助字段（任何状态下都可修改）
     */
    private const AUXILIARY_FIELDS = [
        'title', 'signer_a', 'contact_a', 'contact_a_phone',
        'signer_b', 'contact_b', 'contact_b_phone',
        'summary', 'remarks', 'expiry_date',
    ];

    public function __construct()
    {
        //
    }

    /**
     * 合同列表（带筛选）
     */
    public function index(Request $request)
    {
        $contracts = MasterContract::with(['organizationA', 'organizationB'])
            ->when($request->keyword, function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('contract_no', 'like', "%{$keyword}%")
                        ->orWhere('title', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return $this->success($contracts);
    }

    /**
     * 创建框架合同
     */
    public function store(StoreMasterContractRequest $request)
    {
        $data = $request->validated();
        $data['version'] = 1;
        $data['status'] = MasterContract::STATUS_DRAFT;

        $contract = DB::transaction(function () use ($data) {
            return MasterContract::create($data);
        });

        return $this->success($contract, '框架合同已成功创建', 201);
    }

    /**
     * 获取详情（模型绑定）
     */
    public function show(MasterContract $contract)
    {
        $contract->load(['organizationA', 'organizationB', 'executions', 'logs']);

        return $this->success($contract);
    }

    /**
     * 更新合同（仅草稿/驳回状态下可改核心字段）
     */
    public function update(UpdateMasterContractRequest $request, MasterContract $contract)
    {
        // 非草稿且非驳回状态时，禁止修改核心字段
        $this->authorize('edit', $contract);

        if (! in_array($contract->status, [MasterContract::STATUS_DRAFT, MasterContract::STATUS_REJECTED])) {
            foreach (self::CORE_FIELDS as $field) {
                if ($request->has($field) && $request->input($field) != $contract->$field) {
                    $statusText = $this->getStatusText($contract->status);

                    return $this->error("当前合同状态为 [{$statusText}]，核心内容已锁定，不可直接修改。");
                }
            }
        }

        // 根据状态决定允许修改的字段
        $allowedFields = self::AUXILIARY_FIELDS;
        if (in_array($contract->status, [MasterContract::STATUS_DRAFT, MasterContract::STATUS_REJECTED])) {
            $allowedFields = array_merge($allowedFields, self::CORE_FIELDS, ['status']);
        }

        $updateData = $request->only($allowedFields);
        $contract->update($updateData);

        return $this->success($contract, '合同修改成功');
    }

    /**
     * 逻辑删除合同（仅草稿状态）
     */
    public function destroy(MasterContract $contract)
    {
        $this->authorize('delete', $contract);

        if ($contract->status !== MasterContract::STATUS_DRAFT) {
            return $this->error('当前合同状态不是“草稿”，已产生业务效力，严禁删除。如需废止请走【结案】流程。');
        }

        // 可选：检查是否有关联执行合同
        if ($contract->executions()->exists()) {
            return $this->error('该合同下已拆分执行订单，无法删除。');
        }

        $contract->delete();

        return $this->success(null, '合同已成功移入回收站');
    }

    /**
     * 提交审核
     */
    public function submit(MasterContract $contract)
    {
        $this->authorize('submit', $contract);
        $contract->stateMachine()->execute('submit');

        return $this->success(null, '已提交审核');
    }

    /**
     * 撤回审核（仅审批中状态）
     */
    public function withdraw(MasterContract $contract)
    {
        $this->authorize('withdraw', $contract);
        $contract->stateMachine()->execute('withdraw');

        return $this->success(null, '已撤回审核');
    }

    /**
     * 通过审批
     */
    public function approve(MasterContract $contract)
    {
        $this->authorize('approve', $contract);
        $contract->stateMachine()->execute('approve');

        return $this->success(null, '合同已生效');
    }

    /**
     * 驳回审批
     */
    public function reject(MasterContract $contract)
    {
        $this->authorize('reject', $contract);
        $contract->stateMachine()->execute('reject');

        return $this->success(null, '已驳回');
    }

    /**
     * 作废合同（仅草稿/驳回状态）
     */
    public function void(MasterContract $contract)
    {
        $this->authorize('void', $contract);
        $contract->stateMachine()->execute('void');

        return $this->success(null, '合同已作废');
    }

    /**
     * 终止合同（仅已生效状态）
     */
    public function terminate(MasterContract $contract)
    {
        $this->authorize('terminate', $contract);
        $contract->stateMachine()->execute('terminate');

        return $this->success(null, '合同已终止');
    }

    /**
     * 框架合同拆分为执行合同
     */
    public function splitToExecution(Request $request)
    {
        $validated = $request->validate([
            'master_id' => 'required|exists:master_contracts,id',
            'contracts' => 'required|array|min:1',
            'contracts.*.title' => 'required|string',
            'contracts.*.total_amount' => 'required|numeric|min:0',
            'contracts.*.type' => 'required|in:REVENUE,COST',
            'contracts.*.external_no' => 'nullable|string',
        ]);

        $master = MasterContract::findOrFail($validated['master_id']);

        $this->authorize('split', $master);

        $newExecutions = DB::transaction(function () use ($master, $validated) {
            $executions = [];

            foreach ($validated['contracts'] as $item) {
                $execution = ExecutionContract::create([
                    'master_id' => $master->id,
                    'system_no' => $this->generateExecutionNumber(),
                    'external_no' => $item['external_no'] ?? '',
                    'title' => $item['title'],
                    'org_a_id' => $master->org_a_id,
                    'org_b_id' => $master->org_b_id,
                    'type' => $item['type'],
                    'total_amount' => $item['total_amount'],
                    'status' => MasterContract::STATUS_DRAFT,
                ]);

                $this->logAudit('split', "拆分生成执行合同: {$execution->system_no}", $item);

                $executions[] = $execution;
            }

            return $executions;
        });

        return $this->success($newExecutions, '合同拆分成功');
    }

    /**
     * 生成执行合同唯一编号
     */
    private function generateExecutionNumber(): string
    {
        return 'EX-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    /**
     * 记录审计日志（示例）
     */
    private function logAudit(string $action, string $message, array $payload = []): void
    {
        \Illuminate\Support\Facades\Log::channel('audit')->info($action, [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * 获取状态文本
     */
    private function getStatusText(int $status): string
    {
        return match ($status) {
            MasterContract::STATUS_DRAFT => '草稿',
            MasterContract::STATUS_PENDING => '审批中',
            MasterContract::STATUS_REJECTED => '驳回',
            MasterContract::STATUS_ACTIVE => '已生效',
            MasterContract::STATUS_EXPIRED => '已过期',
            MasterContract::STATUS_TERMINATED => '已终止',
            MasterContract::STATUS_VOID => '作废',
            default => '未知',
        };
    }
}
