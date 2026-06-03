<?php

namespace App\Services\Contract;

use App\Models\Contract\ExecutionContract;
use App\Enums\ExecutionContractStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * 执行合同核心业务服务层
 *
 * 承载所有涉及 SQL Server 2019 的复杂查询、数据持久化、事务流转及状态机控制
 */
class ExecutionContractService
{
    /**
     * 1. 列表分页查询 (针对 SQL Server 2019 优化的多条件筛选)
     *
     * @param array $filters 过滤条件
     * @param int $perPage 每页条数
     * @return LengthAwarePaginator
     */
    public function getPaginatedList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        // 1. 显式 SELECT 字段，并【必须包含外键 org_a_id】
        $query = ExecutionContract::select([
            'id',
            'system_no',
            'title',
            'total_amount',
            'status',
            'created_at',
            'org_a_id',          // ← 预加载 organizationA 所需的外键
            'org_b_id',          // ← 预加载 organizationB 所需的外键
            'master_id',//框架合同ID
        ]);

        // 2. 预加载关联（使用闭包可限制关联查询的字段）
        $query->with(['organizationA' => function ($q) {
            // 仅选择关联表中必要的列，例如 id, name
            $q->select('id', 'name');
        },'organizationB'=>function($q){
            $q->select('id','name');
        },'masterContract'=>function($q){
            $q->select('id','contract_no');
        }]);

        // 模糊查询
        if (!empty($filters['keyword'])) {
            $keyword = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('system_no', 'like', $keyword)
                ->orWhere('title', 'like', $keyword);
            });
        }

        // 精确状态筛选
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 分页（原 orderBy 不变）
        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * 2. 创建执行合同
     *
     * @param array $data 验证通过的合同数据
     * @return ExecutionContract
     */
    public function createContract(array $data): ExecutionContract
    {
        // 强隔离写操作，确保单条插入的绝对原子性
        return DB::transaction(function () use ($data) {
            // create 操作会自动触发 OwenIt\Auditing 记录至 audits 表[cite: 1]
            return ExecutionContract::create($data);
        });
    }

    /**
     * 3. 更新执行合同
     *
     * @param ExecutionContract $contract 现有合同实例
     * @param array $data 更新数据
     * @return ExecutionContract
     */
    public function updateContract(ExecutionContract $contract, array $data): ExecutionContract
    {
        return DB::transaction(function () use ($contract, $data) {
            // update 操作会自动对比 Dirty 属性，并在 audits 表中记录变更前后的快照[cite: 1]
            $contract->update($data);
            return $contract->refresh(); // 返回刷新后的最新实体
        });
    }

    /**
     * 4. 核心工作流：变更合同状态（原子切换与状态历史留痕）
     *
     * @param ExecutionContract $contract 合同实例
     * @param ExecutionContractStatus $targetStatus 目标流转状态
     * @param string $comment 审批/操作批注
     * @return ExecutionContract
     * @throws InvalidArgumentException
     */
    public function changeStatus(
        ExecutionContract $contract,
        ExecutionContractStatus $targetStatus,
        string $comment = ''
    ): ExecutionContract {

        return DB::transaction(function () use ($contract, $targetStatus, $comment) {

            // 备份旧状态用于日志或额外审计
            // $oldStatusLabel = $contract->status->label();[cite: 1]

            // 1. 赋予新状态（自动触发 Eloquent Casting 序列化为整型入库）
            $contract->status = $targetStatus;
            $contract->save(); // 该写操作会被 audits 表自动捕获[cite: 1]

            // 2. 严谨性扩展：在企业级应用中，通常建议建立独立的流水表（如 contract_status_logs）
            // 记录每次状态流转的人员、时间、批注，用于复杂的历史时间轴追溯。
            DB::table('execution_contract_logs')->insert([
                'contract_id'   => $contract->id,
                'operator_id'   => Auth::id() ?? 0,
                'old_status'    => $contract->getOriginal('status'),
                'new_status'    => $targetStatus->value,
                'comment'       => $comment ?: "合同状态从 [{$oldStatusLabel}] 变更为 [{$targetStatus->label()}]",
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            return $contract->refresh();
        });
    }

    /**
     * 5. 安全逻辑删除 (Soft Delete)
     *
     * @param ExecutionContract $contract 合同实例
     * @return bool|null
     */
    public function deleteContract(ExecutionContract $contract): ?bool
    {
        return DB::transaction(function () use ($contract) {
            // 资深全栈提示：此处只调用底层的 delete()。
            // 由于 Model 类中引入了 Illuminate\Database\Eloquent\SoftDeletes Trait，
            // 它不会执行 "DELETE FROM" 物理清除，而是执行 "UPDATE ... SET deleted_at = ?"。
            // 同时，由于挂载了审计插件，audits 表会清晰记录“谁在何时将该数据逻辑删除”[cite: 1]。
            return $contract->delete();
        });
    }
}
