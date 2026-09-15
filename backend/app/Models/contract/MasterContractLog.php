<?php

namespace App\Models\contract;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 框架合同审核日志模型
 *
 * * @property int $master_id 关联的主合同ID
 * @property int $user_id 操作人ID
 * @property int $action_type 动作类型: 0-提交, 1-通过, 2-驳回, 3-终止, 4-作废
 */
class MasterContractLog extends Model
{
    // 指定表名
    protected $table = 'master_contract_logs';

    // 审计日志通常仅允许写入，禁止更新
    public $timestamps = false;

    protected $fillable = [
        'master_id',
        'user_id',
        'action_type',
        'from_status',
        'to_status',
        'remark',
    ];

    /**
     * 关联操作人
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联主合同
     */
    public function masterContract(): BelongsTo
    {
        return $this->belongsTo(MasterContract::class, 'master_id');
    }
}
