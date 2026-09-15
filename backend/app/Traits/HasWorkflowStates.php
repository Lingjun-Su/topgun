<?php

namespace App\Traits;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * 工作流状态机通用 Trait
 * 凡是需要“状态-动作”管控的 Eloquent Model 直接 use 即可
 */
trait HasWorkflowStates
{
    /**
     * 自动将数据库的 status 字段转换为强类型 Enum
     * Laravel 12 推荐在 casts() 方法中定义
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'status' => ContractStatus::class,
        ]);
    }

    /**
     * 校验当前模型实例是否允许执行特定动作
     *
     * @param  string  $action  动作标识 (如: 'submit', 'approve')
     */
    public function canPerform(string $action): bool
    {
        // $this->status 已被自动转换为 ContractStatus 枚举实例
        return in_array($action, $this->status->allowableActions());
    }

    /**
     * 动态追加属性：允许的动作列表
     * 方便通过 API 直接输出给前端 Vue3 的 useWorkflow 钩子
     */
    protected function permittedActions(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status ? $this->status->allowableActions() : []
        );
    }
}
