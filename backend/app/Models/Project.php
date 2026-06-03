<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// 项目
class Project extends Model
{
    use SoftDeletes;

    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'projects';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'order_no',
        'name',
        'province_code',
        'city_code',
        'district_code',
        'street_code',
        'project_bid_id',
        'organization_id',
        'employee_id',
        'region',
        'amount',
        'general_contract_settlement_amount',
        'subcontract_ratio',
        'status',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'general_contract_settlement_amount' => 'decimal:2',
        'subcontract_ratio' => 'decimal:2',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 关联上级项目（自关联）
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 关联下级项目（自关联）
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 关联投标记录
     *
     * @return BelongsTo
     */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(ProjectBid::class, 'project_bid_id', 'id');
    }

    /**
     * 关联总包组织
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    /**
     * 关联项目负责人
     *
     * @return BelongsTo
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    /**
     * 关联分包合同
     *
     * @return HasMany
     */
    public function subcontracts(): HasMany
    {
        return $this->hasMany(ProjectSubcontract::class, 'project_id', 'id');
    }

    /**
     * 关联结算主表
     *
     * @return HasMany
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(ProjectSettlement::class, 'project_id', 'id');
    }

    /**
     * 关联收款记录
     *
     * @return HasMany
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(ProjectReceivable::class, 'project_id', 'id');
    }

    /**
     * 关联付款记录
     *
     * @return HasMany
     */
    public function payables(): HasMany
    {
        return $this->hasMany(ProjectPayable::class, 'project_id', 'id');
    }

    /**
     * 关联发票记录
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'project_id', 'id');
    }
}
