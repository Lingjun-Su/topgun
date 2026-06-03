<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dictionary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'code',
        'label',
        'sort',
        'status'
    ];

    // 强制转换数据类型，方便前端直接使用布尔或整数
    protected $casts = [
        'status' => 'integer',
        'sort'   => 'integer',
    ];
}
