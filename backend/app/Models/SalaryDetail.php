<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 薪资明细
class SalaryDetail extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'salary_details';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'settlement_detail_id',
        'project_id',
        'salary_month',
        'base_salary',
        'overtime_salary',
        'allowance',
        'deduction',
        'pre_tax_amount',
        'tax_amount',
        'after_tax_amount',
        'status',
        'remark',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_salary' => 'decimal:2',
        'overtime_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'pre_tax_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'after_tax_amount' => 'decimal:2',
        'status' => 'integer',
        'salary_month' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 薪资状态常量定义
     */
    const STATUS_UNCALCULATED = 0; // 未核算
    const STATUS_CALCULATED = 1; // 已核算
    const STATUS_CONFIRMED = 2; // 已确认
    const STATUS_CANCELED = 3; // 已取消

    /**
     * 关联员工
     *
     * @return BelongsTo
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    /**
     * 关联结算明细
     *
     * @return BelongsTo
     */
    public function settlementDetail(): BelongsTo
    {
        return $this->belongsTo(SettlementDetail::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联项目
     *
     * @return BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * 关联薪资发放记录
     *
     * @return BelongsTo
     */
    public function salaryPayment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'salary_detail_id', 'id');
    }

    /**
     * 关联个税报税记录
     *
     * @return BelongsTo
     */
    public function taxDeclaration(): BelongsTo
    {
        return $this->belongsTo(TaxDeclaration::class, 'salary_detail_id', 'id');
    }
}
