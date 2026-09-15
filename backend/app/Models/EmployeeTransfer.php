<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// 员工岗位变动
class EmployeeTransfer extends Model
{
    use SoftDeletes;

    /**
     * 关联的数据表
     *
     * @var string
     */
    protected $table = 'employee_transfers';

    /**
     * 允许批量赋值的字段
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'transfer_type',
        'old_organization_id',
        'old_department_id',
        'old_position_id',
        'new_organization_id',
        'new_department_id',
        'new_position_id',
        'reason',
        'effective_date',
        'operator_id',
        'remark',
    ];

    /**
     * 字段类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transfer_type' => 'integer',
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 变动类型常量定义
     */
    const TRANSFER_TYPE_ENTRY = 1; // 入职

    const TRANSFER_TYPE_ORGANIZATION = 2; // 组织调动

    const TRANSFER_TYPE_DEPARTMENT = 3; // 部门调动

    const TRANSFER_TYPE_PROMOTION = 4; // 升迁

    const TRANSFER_TYPE_DEMOTION = 5; // 降级

    const TRANSFER_TYPE_LEAVE = 6; // 离职

    const TRANSFER_TYPE_OTHER = 7; // 其他变动

    /**
     * 关联员工
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    /**
     * 关联变动前组织
     */
    public function oldOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'old_organization_id', 'id');
    }

    /**
     * 关联变动前部门
     */
    public function oldDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'old_department_id', 'id');
    }

    /**
     * 关联变动前岗位
     */
    public function oldPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'old_position_id', 'id');
    }

    /**
     * 关联变动后组织
     */
    public function newOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'new_organization_id', 'id');
    }

    /**
     * 关联变动后部门
     */
    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'new_department_id', 'id');
    }

    /**
     * 关联变动后岗位
     */
    public function newPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'new_position_id', 'id');
    }

    /**
     * 关联操作人
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id', 'id');
    }
}
