<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 产品/物料模型
 * 严谨性：精确数值转换、SKU唯一性逻辑、审计关联
 */
class Products extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'sku_code',//
        'name',//名称
        'contents',//描述
        'business_id',//业务ID
        'unit',//计量单位
        'base_price',//单价
        'pay_model',//付费方 式
        'specification',//规格
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    // 严谨的类型转换
    protected $casts = [
        'id'            => 'integer',
        'org_id'        => 'integer',
        'business_id'   => 'integer',
        'pay_model'     => 'integer',
        'status'     => 'integer',
        // SQL Server 的 decimal 在 Laravel 中通过 float 或 decimal:4 确保精度
        'base_price' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 关联：所属业务 business_id
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id','id');
    }

    //向下关联省份，一个产品可能对应多个省份
    public function productProvince()
    {
        // 这种写法可以直接获取省份代码列表
        return $this->hasMany(ProductProvince::class, 'product_id','id');
    }

    /**
     * 关联：审计日志
     */
    public function audits()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * 严谨性：获取格式化价格
     * 示例：$product->formatted_price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '¥' . number_format($this->base_price, 2);
    }
}
