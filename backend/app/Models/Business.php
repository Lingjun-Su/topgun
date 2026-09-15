<?php

namespace App\Models;

use App\Models\Products as Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 业务往来单位模型
 * 严谨性：支持逻辑删除、多态审计关联、组织架构关联
 */
class Business extends Model
{
    use HasFactory, SoftDeletes;

    // 指定表名
    protected $table = 'business';

    // 严谨字段填充限制
    protected $fillable = [
        'org_id',
        'carrier_id',
        'status',
        'code',
        'name',
        'short_name',
        'contact_person',
        'contact_phone',
        'contents',
        'address',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * 一个业务单位拥有多个产品
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'business_id');
    }

    /**
     * 获取校验规则的静态方法 (供 Controller 使用)
     * 严谨处理：排除当前 ID 的唯一性检查（用于更新操作）
     */
    public static function validationRules($id = null)
    {
        return [
            'org_id' => 'required|integer',
            'code' => [
                'required',
                // 使用 Laravel Rule 类构建复杂的复合唯一校验
                \Illuminate\Validation\Rule::unique('business')
                    ->where('org_id', request('org_id'))
                    ->ignore($id),
            ],
            'name' => 'required|string|max:200',
        ];
    }

    // 属性转换：确保 SQL Server 返回的数值或日期格式正确
    protected $casts = [
        'org_id' => 'integer',
        'id' => 'integer',
        'carrier_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'status' => 'integer', // 状态
    ];

    /**
     * 关联：所属组织 (对应你上传的数据字典 - 组织架构表)
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * 关联：对应运营商
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    /**
     * 关联：审计日志 (多态关联)
     * 这样可以通过 $business->audits 获取该单位的所有变动记录
     */
    public function audits()
    {
        return $this->morphMany(AuditLog::class, 'audits');
    }

    /**
     * 作用域：按类型筛选 (CUSTOMER/SUPPLIER)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
