<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ThirdChannels extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes; // 开启变动追踪记录

    protected $table = 'third_channels'; // 数据库

    // 严谨起见，定义常量防止硬编码错误
    const METHOD_RECEIVE = 'receive';

    const METHOD_SEND = 'send';

    // 三角色模型（ADR-002）：与 method 正交，禁止用 method 反推 role
    const ROLE_UNSET = 'unset';

    const ROLE_SUPPLIER_A = 'supplier_a'; // 上游供应商 A

    const ROLE_CHANNEL_C = 'channel_c'; // 下游推广 C

    // 白名单
    protected $fillable = [
        'name', 'pid', 'key', 'is_ip_restricted', 'ip_whitelist',
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
        'role',
        'status',
        // 配置驱动扩展字段
        'push_base_url',
        'push_endpoint',
        'push_timeout',
        'sign_algorithm',
        'sign_key',
        'callback_url',
        'service_class',
        'ext_config',
        'push_success_rule',
        'push_fail_rule',
        'push_steps',
        // 接收上家信息配置
        'receive_endpoint',
        'receive_auth_mode',
        'auto_forward',
        'forward_target_pid',
        // 向下家推送信息配置
        'push_notify_url',
        'push_max_retries',
        'push_retry_delay',
        // 定时回调推送
        'auto_callback_enabled',
        'auto_callback_cron',
        'auto_callback_time_start',
        'auto_callback_time_end',
        // 回调接收配置
        'callback_config',
        'receive_mapping',
        // 停止条件配置
        'stop_rules',
    ];

    // 审计配置：这些字段变动时会自动记录到 audits 表
    protected $auditInclude = [
        'name',
        'pid',
        'key',
        'method',
        'role',
        'status',
        'ip_whitelist', // 白名单
        'is_ip_restricted', // 强制白名单
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
        // 配置驱动扩展字段
        'push_base_url',
        'push_endpoint',
        'push_timeout',
        'sign_algorithm',
        'sign_key',
        'callback_url',
        'service_class',
        'ext_config',
        'push_success_rule',
        'push_fail_rule',
        'push_steps',
        // 接收上家信息配置
        'receive_endpoint',
        'receive_auth_mode',
        'auto_forward',
        'forward_target_pid',
        // 向下家推送信息配置
        'push_notify_url',
        'push_max_retries',
        'push_retry_delay',
        // 定时回调推送
        'auto_callback_enabled',
        'auto_callback_cron',
        'auto_callback_time_start',
        'auto_callback_time_end',
        // 回调接收配置
        'callback_config',
        'receive_mapping',
        // 停止条件配置
        'stop_rules',
    ];

    protected $casts = [
        'id' => 'integer',
        'status' => 'integer', // 状态
        'organization_id' => 'integer', // 状态
        'is_ip_restricted' => 'boolean', // 强制转换为布尔类型
        'push_timeout' => 'integer',
        'ext_config' => 'array', // JSON 自动解码为数组
        'push_success_rule' => 'array', // JSON 自动解码为数组
        'push_fail_rule' => 'array', // JSON 自动解码为数组
        'push_steps' => 'array', // JSON 自动解码为数组
        'receive_endpoint' => 'string',
        'receive_auth_mode' => 'string',
        'auto_forward' => 'boolean', // 自动转发开关
        'forward_target_pid' => 'string',
        'push_notify_url' => 'string',
        'push_max_retries' => 'integer',
        'push_retry_delay' => 'integer',
        'auto_callback_enabled' => 'boolean',
        'auto_callback_cron' => 'string',
        'auto_callback_time_start' => 'string',
        'auto_callback_time_end' => 'string',
        'callback_config' => 'array',
        'receive_mapping' => 'array',
        'stop_rules' => 'array',
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
