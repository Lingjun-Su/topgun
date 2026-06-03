<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 薪资支付
class SalaryPayment extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'salary_payments';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no',
        'employee_id',
        'salary_detail_id',
        'organization_bank_id',
        'payment_amount',
        'payment_date',
        'payment_method',
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
        'payment_amount' => 'decimal:2',
        'payment_method' => 'integer',
        'status' => 'integer',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 发放状态常量定义
     */
    const STATUS_UNPAID = 0; // 未发放
    const STATUS_PAID = 1; // 已发放
    const STATUS_FAILED = 2; // 发放失败
    const STATUS_REFUNDED = 3; // 已退款

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
     * 关联薪资明细
     *
     * @return BelongsTo
     */
    public function salaryDetail(): BelongsTo
    {
        return $this->belongsTo(SalaryDetail::class, 'salary_detail_id', 'id');
    }

    /**
     * 关联发放银行账户
     *
     * @return BelongsTo
     */
    public function organizationBank(): BelongsTo
    {
        return $this->belongsTo(OrganizationBank::class, 'organization_bank_id', 'id');
    }

    /**
     * 关联操作人
     *
     * @return BelongsTo
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id', 'id');
    }
}
