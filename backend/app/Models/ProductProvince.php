<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductProvince extends Model
{
    use SoftDeletes;

    protected $fillable = ['id', 'product_id', 'province_code']; // 白名单

    protected $casts = [
        'id' => 'integer', // 返回数字，而不是字符串
        'product_id' => 'integer', // 产品ID
        'province_code' => 'string', // 省份code
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // 如果有浮点数也可以转成 float
    ];

    // 同时关联products表和province表
    public function areas(): BelongsTo
    {
        return $this->belongsTo(Areas::class, 'province_code', 'code');
    }
}
