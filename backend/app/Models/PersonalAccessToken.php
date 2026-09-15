<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    // 只保留逻辑删除，移除 Auditable 相关接口和 Trait
    use SoftDeletes;

    protected $table = 'personal_access_tokens';

    // SQL Server 2019 严谨转换
    protected $casts = [
        'id' => 'integer',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 逻辑删除字段
    protected $dates = ['deleted_at'];
}
