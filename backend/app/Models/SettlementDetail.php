<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// 结算明细
class SettlementDetail extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'settlement_details';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_settlement_id',
        'organization_id',
        'type',
        'amount',
        'invoice_amount',
        'invoice_date',
        'paid_amount',
        'unpaid_amount',
        'status',
        'remark',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'invoice_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'unpaid_amount' => 'decimal:2',
        'status' => 'integer',
        'invoice_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联结算主表
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ProjectSettlement::class, 'project_settlement_id', 'id');
    }

    /**
     * 关联参与方组织
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    /**
     * 关联收款记录
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(ProjectReceivable::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联付款记录
     */
    public function payables(): HasMany
    {
        return $this->hasMany(ProjectPayable::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联发票记录
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联薪资明细
     */
    public function salaryDetails(): HasMany
    {
        return $this->hasMany(SalaryDetail::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联个税报税记录
     */
    public function taxDeclarations(): HasMany
    {
        return $this->hasMany(TaxDeclaration::class, 'settlement_detail_id', 'id');
    }
}
