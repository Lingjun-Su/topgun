<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 执行合同模型
 * 记录从框架合同中分切出的具体收入/成本业务
 */
class ExecutionContract extends Model
{
    use SoftDeletes; // 开启逻辑删除

    protected $table = 'execution_contracts';

    // 状态常量定义
    const STATUS_DRAFT = 0;      // 草稿
    const STATUS_PENDING = 1;    // 审批中
    const STATUS_EFFECTIVE = 2;  // 已生效
    const STATUS_EXPIRED = 3;    // 已过期
    const STATUS_TERMINATED = 4; // 已终止
    const STATUS_VOID = 5;       // 作废

    // 类型常量
    const TYPE_REVENUE = 'REVENUE'; // 收入类型
    const TYPE_COST = 'COST';       // 成本类型

    protected $fillable = [
        'master_contract_id', 'system_no', 'external_no', 'type',
        'amount', 'contract_date', 'status', 'description', 'org_id'
    ];

    protected $casts = [
        'amount' => 'decimal:4', // 对应 SQL Server decimal(18, 4)
        'status' => 'integer',
        'contract_date' => 'date',
    ];

    /**
     * 关联：所属框架合同
     */
    public function masterContract(): BelongsTo
    {
        return $this->belongsTo(MasterContract::class, 'master_contract_id');
    }
}
