<?php

namespace App\Models\contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use User;

/**
 * 合同变更记录模型
 * 记录非核心字段（如备注、联系人等）的微调痕迹，作为审计证据
 */
class MasterContractChange extends Model
{
    // 审计类表不使用 SoftDeletes，必须永久保存
    protected $table = 'master_contract_changes';

    protected $fillable = [
        'master_contract_id', 'field_name', 'old_value',
        'new_value', 'change_reason', 'operator_id',
    ];

    /**
     * 关联：操作人（对应员工或用户表）
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function masterContract(): BelongsTo
    {
        return $this->belongsTo(MasterContract::class, 'master_contract_id');
    }
}
