<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 项目收款表
class ProjectReceivable extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'project_receivables';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no',
        'project_id',
        'settlement_detail_id',
        'organization_id',
        'amount',
        'receivable_date',
        'payment_method',
        'bank_account_id',
        'status',
        'remark',
        'employee_id',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => 'integer',
        'status' => 'integer',
        'receivable_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联项目
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * 关联结算明细
     */
    public function settlementDetail(): BelongsTo
    {
        return $this->belongsTo(SettlementDetail::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联付款方组织
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    /**
     * 关联收款银行账户
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBank::class, 'bank_account_id', 'id');
    }

    /**
     * 关联经办人
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
