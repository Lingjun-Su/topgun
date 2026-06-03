<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ThirdChannels extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable; // 开启变动追踪记录
    protected $table = 'third_channels';//数据库

    // 严谨起见，定义常量防止硬编码错误
    const METHOD_RECEIVE = 'receive';
    const METHOD_SEND = 'send';

    //白名单
    protected $fillable = [
        'name', 'pid', 'key','is_ip_restricted','ip_whitelist',
        'parameter_name1',
        'parameter_name2',
        'parameter_name3',
        'parameter_name4',
        'parameter_name5',
        'parameter_value1',
        'parameter_value2',
        'parameter_value3',
        'parameter_value4',
        'parameter_value5',
        'organization_id',
        'method',
        'status'];

    // 审计配置：这些字段变动时会自动记录到 audits 表
    protected $auditInclude = [
        'name',
        'pid',
        'key',
        'method',
        'status',
        'ip_whitelist',//白名单
        'is_ip_restricted',//强制白名单
        'parameter_name1',
        'parameter_value1',
        'parameter_name2',
        'parameter_value2',
        'parameter_name3',
        'parameter_value3',
        'parameter_name4',
        'parameter_value4',
        'parameter_name5',
        'parameter_value5',
    ];

     protected $casts = [
        'id'            => 'integer',
        'status'        => 'integer',//状态
        'organization_id' => 'integer',//状态
        'is_ip_restricted' => 'boolean', // 强制转换为布尔类型
     ];
    /**
     * 关联到组织架构表
     * 严谨起见，明确指定关联键：organization_id 对应 organizations 表的 id
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    // 定义一对多关系：一个渠道有多个产品配置
    public function channelProducts()
    {
        return $this->hasMany(ChannelProducts::class, 'channel_id', 'id');
    }


}
