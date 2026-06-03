<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class OrderSyncLog extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable; // 开启审计

    protected $table = 'order_sync_logs';

    // 字段格式转换
    protected $casts = [
        'ext_json' => 'array',
        'unsub_time' => 'datetime',
    ];

    /**
     * 审计配置：定义哪些字段的变动需要记录
     */
    public function transformAudit(array $data): array
    {
        // 如果想在审计日志中加入自定义标签，可以在这里处理
        return $data;
    }
}
