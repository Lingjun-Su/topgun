<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 项目分包表
class ProjectSubcontract extends Model
{
    use SoftDeletes;

    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'project_subcontracts';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no',
        'project_id',
        'contractor_organization_id',
        'subcontractor_organization_id',
        'province_code',
        'city_code',
        'district_code',
        'street_code',
        'amount',
        'ratio',
        'scope',
        'sign_date',
        'start_date',
        'end_date',
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
        'ratio' => 'decimal:2',
        'status' => 'integer',
        'sign_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
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
     * 关联总包组织
     */
    public function contractorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'contractor_organization_id', 'id');
    }

    /**
     * 关联分包组织
     */
    public function subcontractorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'subcontractor_organization_id', 'id');
    }

    /**
     * 关联结算主表
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(ProjectSettlement::class, 'project_subcontract_id', 'id');
    }
}
