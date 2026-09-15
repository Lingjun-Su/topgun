<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 补充协议模型
 * 涉及合同总额、有效期等核心法律条款的变更
 */
class MasterContractAmendment extends Model
{
    use SoftDeletes;

    protected $table = 'master_contract_amendments';

    const STATUS_DRAFT = 0;
    const STATUS_PENDING = 1;
    const STATUS_EFFECTIVE = 2;
    const STATUS_VOID = 5;

    protected $fillable = [
        'master_contract_id', 'amendment_no', 'delta_amount',
        'original_amount', 'new_amount', 'effective_date', 'status'
    ];

    protected $casts = [
        'delta_amount' => 'decimal:4', // 增减量（可正可负）
        'original_amount' => 'decimal:4',
        'new_amount' => 'decimal:4',
        'status' => 'integer',
    ];

    /**
     * 关联：所属主合同
     */
    public function masterContract(): BelongsTo
    {
        return $this->belongsTo(MasterContract::class, 'master_contract_id');
    }
}
