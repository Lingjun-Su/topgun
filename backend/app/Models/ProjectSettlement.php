<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 项目结算表
class ProjectSettlement extends Model
{
    use SoftDeletes;

    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'project_settlements';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no',
        'project_id',
        'project_subcontract_id',
        'settlement_date',
        'total_amount',
        'status',
        'remark',
        'operator_id',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'status' => 'integer',
        'settlement_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 关联项目
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * 关联分包合同
     */
    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(ProjectSubcontract::class, 'project_subcontract_id', 'id');
    }

    /**
     * 关联操作人
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id', 'id');
    }

    /**
     * 关联结算明细
     */
    public function details(): HasMany
    {
        return $this->hasMany(SettlementDetail::class, 'project_settlement_id', 'id');
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
