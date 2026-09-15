<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelProducts extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', // 产品ID
        'channel_id', // 渠道ID
        'status', // 状态
        'remark', // 备注
    ];

    // 白名单
    protected $casts = [
        'id' => 'integer', // 返回数字，而不是字符串
        'status' => 'integer', // 返回数字，而不是字符串
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // 如果有浮点数也可以转成 float
    ];
    //

    public function products(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id');
    }
}
