<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 税务报表
class TaxDeclaration extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'tax_declarations';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no',
        'employee_id',
        'salary_detail_id',
        'settlement_detail_id',
        'declaration_month',
        'taxable_amount',
        'tax_amount',
        'declaration_date',
        'status',
        'declaration_no',
        'remark',
        'operator_id',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'status' => 'integer',
        'declaration_month' => 'date',
        'declaration_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 报税状态常量定义
     */
    const STATUS_UNDECLARED = 0; // 未报税

    const STATUS_DECLARED = 1; // 已报税

    const STATUS_FAILED = 2; // 报税失败

    const STATUS_REVOKED = 3; // 已撤销

    /**
     * 关联员工
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    /**
     * 关联薪资明细
     */
    public function salaryDetail(): BelongsTo
    {
        return $this->belongsTo(SalaryDetail::class, 'salary_detail_id', 'id');
    }

    /**
     * 关联结算明细
     */
    public function settlementDetail(): BelongsTo
    {
        return $this->belongsTo(SettlementDetail::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联操作人
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id', 'id');
    }
}
