<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//发票
class Invoice extends Model
{
    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'invoices';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no',
        'project_id',
        'settlement_detail_id',
        'invoice_type',
        'issue_organization_id',
        'receive_organization_id',
        'amount',
        'tax_amount',
        'tax_rate',
        'invoice_date',
        'invoice_code',
        'invoice_number',
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
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'invoice_type' => 'integer',
        'status' => 'integer',
        'invoice_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 发票类型常量定义
     */
    const INVOICE_TYPE_VAT_SPECIAL = 1; // 增值税专用发票
    const INVOICE_TYPE_VAT_ORDINARY = 2; // 增值税普通发票
    const INVOICE_TYPE_OTHER = 3; // 其他发票

    /**
     * 发票状态常量定义
     */
    const STATUS_UNISSUED = 0; // 未开具
    const STATUS_ISSUED = 1; // 已开具
    const STATUS_REVOKED = 2; // 已作废
    const STATUS_RED_INVOICE = 3; // 红字发票

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
     * 关联结算明细
     *
     * @return BelongsTo
     */
    public function settlementDetail(): BelongsTo
    {
        return $this->belongsTo(SettlementDetail::class, 'settlement_detail_id', 'id');
    }

    /**
     * 关联开票方组织
     *
     * @return BelongsTo
     */
    public function issueOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'issue_organization_id', 'id');
    }

    /**
     * 关联收票方组织
     *
     * @return BelongsTo
     */
    public function receiveOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'receive_organization_id', 'id');
    }

    /**
     * 关联经办人
     *
     * @return BelongsTo
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
