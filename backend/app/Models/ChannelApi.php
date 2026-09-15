<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ChannelApi extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable; // 开启变动追踪记录
    protected $table = 'channels_api';//数据库
    protected $fillable = ['name', 'pid', 'key','parameter_1','parameter_2','parameter_3','parameter_4', 'status'];

    // 审计配置：这些字段变动时会自动记录到 audits 表
    protected $auditInclude = [
        'name',
        'pid',
        'key',
        'parameter_1',
        'parameter_2',
        'parameter_3',
        'parameter_4',
        'status',
    ];
}
