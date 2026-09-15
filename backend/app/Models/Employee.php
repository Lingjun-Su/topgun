<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 员工表
class Employee extends Model
{
    use SoftDeletes;

    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'employees';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'id_card',
        'gender',
        'phone',
        'organization_id',
        'department_id',
        'position_id',
        'type',
        'entry_date',
        'leave_date',
        'bank_name',
        'bank_account',
        'tax_register_status',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => 'integer',
        'type' => 'integer',
        'tax_register_status' => 'integer',
        'entry_date' => 'date',
        'leave_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 性别常量定义
     */
    const GENDER_MALE = 1; // 男

    const GENDER_FEMALE = 2; // 女

    const GENDER_UNKNOWN = 0; // 未知

    /**
     * 员工类型常量定义
     */
    const TYPE_FORMAL = 1; // 正式员工

    const TYPE_MIGRANT_WORKER = 2; // 农民工

    const TYPE_TEMPORARY = 3; // 临时人员

    const TYPE_PROJECT_MANAGER = 4; // 项目负责人

    /**
     * 个税登记状态常量定义
     */
    const TAX_REGISTER_NO = 0; // 未登记

    const TAX_REGISTER_YES = 1; // 已登记

    /**
     * 关联组织
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    /**
     * 关联部门
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * 关联岗位
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'id');
    }

    /**
     * 关联负责的部门
     */
    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'employee_id', 'id');
    }

    /**
     * 关联调动记录
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(EmployeeTransfer::class, 'employee_id', 'id');
    }

    /**
     * 关联操作的结算记录
     */
    public function operatedSettlements(): HasMany
    {
        return $this->hasMany(ProjectSettlement::class, 'operator_id', 'id');
    }

    /**
     * 关联收款记录（经办人）
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(ProjectReceivable::class, 'employee_id', 'id');
    }

    /**
     * 关联付款记录（经办人）
     */
    public function payables(): HasMany
    {
        return $this->hasMany(ProjectPayable::class, 'employee_id', 'id');
    }

    /**
     * 关联发票记录（经办人）
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'employee_id', 'id');
    }

    /**
     * 关联薪资明细
     */
    public function salaryDetails(): HasMany
    {
        return $this->hasMany(SalaryDetail::class, 'employee_id', 'id');
    }

    /**
     * 关联薪资发放记录
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'employee_id', 'id');
    }

    /**
     * 关联个税报税记录
     */
    public function taxDeclarations(): HasMany
    {
        return $this->hasMany(TaxDeclaration::class, 'employee_id', 'id');
    }

    /**
     * 关联操作的个税报税记录
     */
    public function operatedTaxDeclarations(): HasMany
    {
        return $this->hasMany(TaxDeclaration::class, 'operator_id', 'id');
    }
}
