<?php
// app/Services/ContractStateMachine.php

namespace App\Services\Contract;

use App\Models\Contract\MasterContract;
use InvalidArgumentException;

class MasterContractStateMachine
{
    protected MasterContract $contract;

    // 允许的状态转换 [当前状态 => [允许的目标状 态]]
    protected array $transitions = [
        MasterContract::STATUS_DRAFT     => [MasterContract::STATUS_PENDING, MasterContract::STATUS_VOID],
        MasterContract::STATUS_PENDING   => [MasterContract::STATUS_DRAFT, MasterContract::STATUS_REJECTED, MasterContract::STATUS_ACTIVE],
        MasterContract::STATUS_REJECTED  => [MasterContract::STATUS_PENDING, MasterContract::STATUS_VOID],
        MasterContract::STATUS_ACTIVE    => [MasterContract::STATUS_TERMINATED, MasterContract::STATUS_EXPIRED],
        MasterContract::STATUS_EXPIRED   => [MasterContract::STATUS_TERMINATED], // 可选
        MasterContract::STATUS_TERMINATED=> [],
        MasterContract::STATUS_VOID      => [],
    ];

    // 各状态下允许的动作（用于快速判断）
    protected array $allowedActionsByState = [
        MasterContract::STATUS_DRAFT     => ['edit', 'submit', 'void'],
        MasterContract::STATUS_PENDING   => ['withdraw', 'approve', 'reject'],
        MasterContract::STATUS_REJECTED  => ['edit', 'submit', 'void'],
        MasterContract::STATUS_ACTIVE    => ['add_clause', 'terminate'],
        MasterContract::STATUS_EXPIRED   => [],
        MasterContract::STATUS_TERMINATED=> [],
        MasterContract::STATUS_VOID      => [],
    ];

    public function __construct(MasterContract $contract)
    {
        $this->contract = $contract;
    }

    /**
     * 检查当前状态是否允许某个动作（不考虑用户权限）
     */
    public function isActionAllowedByState(string $action): bool
    {
        $allowed = $this->allowedActionsByState[$this->contract->status] ?? [];
        return in_array($action, $allowed);
    }

    /**
     * 检查是否能转换到目标状态（仅状态规则）
     */
    public function canTransitionTo(int $targetStatus): bool
    {
        $allowed = $this->transitions[$this->contract->status] ?? [];
        return in_array($targetStatus, $allowed);
    }

    /**
     * 执行状态转换（返回新状态，不保存模型）
     * @throws InvalidArgumentException
     */
    public function transitionTo(int $targetStatus): int
    {
        if (!$this->canTransitionTo($targetStatus)) {
            throw new InvalidArgumentException(
                sprintf('不可从状态 %d 转换到 %d', $this->contract->status, $targetStatus)
            );
        }
        return $targetStatus;
    }

    // ----- 各动作的标准状态转换逻辑 -----

    public function submit(): int
    {
        return $this->transitionTo(MasterContract::STATUS_PENDING);
    }

    public function withdraw(): int
    {
        return $this->transitionTo(MasterContract::STATUS_DRAFT);
    }

    public function approve(): int
    {
        return $this->transitionTo(MasterContract::STATUS_ACTIVE);
    }

    public function reject(): int
    {
        return $this->transitionTo(MasterContract::STATUS_REJECTED);
    }

    public function void(): int
    {
        return $this->transitionTo(MasterContract::STATUS_VOID);
    }

    public function terminate(): int
    {
        return $this->transitionTo(MasterContract::STATUS_TERMINATED);
    }

    // add_clause 不改变状态，所以直接返回当前状态
    public function addClause(): int
    {
        return $this->contract->status; // 无状态变更
    }
}
