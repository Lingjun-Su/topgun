<?php
namespace App\Policies;

use App\Models\Contract\ExecutionContract;
use App\Models\User;

class ExecutionContractPolicy
{
    /**
     * 通用权限：只有创建人或管理员可以编辑草稿/驳回状态的合同内容
     */
    public function edit(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('edit')) {
            return false;
        }
        return true;
        // return $user->id === $contract->created_by || $user->hasRole('admin');
    }

    /**
     * 提交审核：仅创建人，且状态允许提交
     */
    public function submit(User $user, ExecutionContract $contract): bool
    {

        if (!$contract->stateMachine()->isActionAllowedByState('submit')) {
            return false;
        }
        return true;
        // return $user->id === $contract->created_by;
    }

    /**
     * 撤回：仅创建人，且状态为审批中
     */
    public function withdraw(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('withdraw')) {
            return false;
        }
        return true;
        // return $user->id === $contract->created_by;
    }

    /**
     * 通过审批：需要 reviewer 或 admin 角色，且状态为审批中
     */
    public function approve(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('approve')) {
            return false;
        }
        return true;
        // return $user->hasRole('reviewer') || $user->hasRole('admin');
    }

    /**
     * 驳回审批：需要 reviewer 或 admin 角色，且状态为审批中
     */
    public function reject(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('reject')) {
            return false;
        }
        return true;
        // return $user->hasRole('reviewer') || $user->hasRole('admin');
    }

    /**
     * 作废：创建人或管理员，且状态为草稿或驳回
     */
    public function void(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('void')) {
            return false;
        }
        return true;
        // return $user->id === $contract->created_by || $user->hasRole('admin');
    }

    /**
     * 终止：创建人或管理员，且状态为已生效
     */
    public function terminate(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('terminate')) {
            return false;
        }
        return true;
        // return $user->id === $contract->created_by || $user->hasRole('admin');
    }

    /**
     * 添加补充条款：创建人或管理员，且状态为已生效
     */
    public function addClause(User $user, ExecutionContract $contract): bool
    {
        if (!$contract->stateMachine()->isActionAllowedByState('add_clause')) {
            return false;
        }
        return true;
        // return $user->id === $contract->created_by || $user->hasRole('admin');
    }

    /**
     * 查看合同：所有登录用户均可查看（可根据业务细化）
     */
    public function view(User $user, ExecutionContract $contract): bool
    {
        return true;
    }
}
