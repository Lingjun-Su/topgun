<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//银行账户表
class OrganizationBank extends Model
{
    use SoftDeletes;

    protected $table = 'organization_banks';

    protected $fillable = [
        'organization_id', 'bank_name', 'bank_account',
        'account_name', 'is_default', 'status', 'remark'
    ];
    protected $casts = [
        'id'         => 'integer',
        'parent_id'  => 'integer',
        'status'     => 'integer',
        'is_default' => 'integer',
        // 如果有浮点数也可以转成 float
    ];
}
