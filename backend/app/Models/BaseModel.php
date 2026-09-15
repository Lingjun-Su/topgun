<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * 严谨性：所有继承此类的模型将自动具备：
 * 1. 逻辑删除 (deleted_at)
 * 2. 字段审计记录 (audits 表)
 * 3. 兼容 SQL Server 2019 时间格式
 */
abstract class BaseModel extends Model implements Auditable
{
    // 引入审计 Trait，它会自动监听 created/updated/deleted/restored 事件
    use AuditableTrait;

    // 引入逻辑删除 Trait
    use SoftDeletes;

    /**
     * SQL Server 2019 严谨时间格式配置
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 审计配置：定义哪些事件需要记录
     */
    protected $auditEvents = [
        'created',
        'updated',
        'deleted',
        'restored',
    ];

    /**
     * 严谨性：排除不需要审计的敏感或无意义字段
     */
    protected $auditExclude = [
        'updated_at',
    ];
}
