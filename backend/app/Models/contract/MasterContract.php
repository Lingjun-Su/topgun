<?php

namespace App\Models\contract;

use App\Models\Contract\ExecutionContract;
use App\Models\Contract\MasterContractLog;
use App\Models\Organization;
use App\Services\Contract\MasterContractStateMachine; // 执行合同
use Illuminate\Database\Eloquent\Factories\HasFactory; // 状态日志
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_contracts';

    // 状态常量
    const STATUS_DRAFT = 0;

    const STATUS_PENDING = 1;

    const STATUS_REJECTED = 2;

    const STATUS_ACTIVE = 3;

    const STATUS_EXPIRED = 4;

    const STATUS_TERMINATED = 5;

    const STATUS_VOID = 6;

    protected $fillable = [
        'contract_no', 'title', 'org_a_id', 'org_b_id',
        'total_limit', 'version', 'status',
        'signed_date', 'effective_date', 'expiry_date',
        'signer_a', 'contact_a', 'signer_b', 'contact_b',
        'contact_a_phone', 'contact_b_phone',
        'summary', 'remarks',
    ];

    protected $casts = [// 输出格式
        'total_limit' => 'decimal:2',
        'signed_date' => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'version' => 'integer',
        'status' => 'integer',
    ];

    // 状态映射（用于显示）
    public static function statusMap(): array
    {
        return [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_PENDING => '审批中',
            self::STATUS_REJECTED => '驳回',
            self::STATUS_ACTIVE => '已生效',
            self::STATUS_EXPIRED => '已过期',
            self::STATUS_TERMINATED => '已终止',
            self::STATUS_VOID => '作废',
        ];
    }

    // 获取状态机实例（不依赖用户）
    public function stateMachine(): MasterContractStateMachine
    {
        return new MasterContractStateMachine($this);
    }

    /**
     * 关联甲方组织
     */
    public function organizationA()
    {
        return $this->belongsTo(Organization::class, 'org_a_id');
    }

    /**
     * 关联乙方组织
     */
    public function organizationB()
    {
        return $this->belongsTo(Organization::class, 'org_b_id');
    }

    /**
     * 关联执行合同
     */
    public function executions()
    {
        return $this->hasMany(ExecutionContract::class, 'master_id');
    }

    /**
     * 审批流程（状态的改变）
     */
    public function logs()
    {
        return $this->hasMany(MasterContractLog::class, 'master_id');
    }
}
