<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Areas extends Model
{
    // use SoftDeletes; // 开启逻辑删除 (SQL Server 会检查 deleted_at)
    // use \OwenIt\Auditing\Auditable; // 开启数据审计，记录变动到 audits 表

    protected $table = 'areas';

    // 审计记录将包含所有字段的变更
    // protected $auditInclude = ['code','name', 'short_name', 'level', 'parent_code'];
}
