<?php

namespace App\Enums;

/**
 * 合同状态枚举
 * 使用 PHP 8.4 强类型枚举，统一管理状态值与可执行动作
 */
enum ContractStatus: int
{
    case DRAFT = 0;      // 草稿
    case APPROVING = 1;  // 审批中
    case REJECTED = 2;   // 已驳回
    case EFFECTIVE = 3;  // 已生效
    case EXPIRED = 4;    // 已过期
    case TERMINATED = 5; // 已终止
    case VOIDED = 6;     // 已作废

    /**
     * 获取当前状态允许执行的动作矩阵
     * 统一收拢“状态->动作”的业务规则
     *
     * @return array
     */
    public function allowableActions(): array
    {
        return match($this) {
            self::DRAFT      => ['edit', 'submit', 'void', 'view'],
            self::APPROVING  => ['withdraw', 'approve', 'reject', 'view'],
            self::REJECTED   => ['edit', 'submit', 'void', 'view'],
            self::EFFECTIVE  => ['terminate', 'view'],
            self::EXPIRED,
            self::TERMINATED,
            self::VOIDED     => ['view'],
        };
    }

    /**
     * 获取状态的中文字符串描述（用于日志或非动态前端展示）
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT      => '草稿',
            self::APPROVING  => '审批中',
            self::REJECTED   => '已驳回',
            self::EFFECTIVE  => '已生效',
            self::EXPIRED    => '已过期',
            self::TERMINATED => '已终止',
            self::VOIDED     => '已作废',
        };
    }
}
