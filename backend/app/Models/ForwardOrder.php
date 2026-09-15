<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 数据中转记录模型
 * 记录A公司→本系统→移动的完整数据中转链路
 */
class ForwardOrder extends Model
{
    use SoftDeletes;

    protected $table = 'forward_orders';

    protected $fillable = [
        'trace_id',
        'source_channel_id', 'target_channel_id',
        'source_order_no', 'target_order_no',
        'source_pid', 'target_pid',
        'source_data', 'transformed_data', 'target_response',
        'forward_status', 'forward_error', 'error_code', 'product_id',
        'callback_url', 'callback_status', 'callback_response',
        'retry_count', 'organization_id',
        'current_step', 'step_data', 'step_responses',
        'mobile', 'verify_attempts',
    ];

    protected $casts = [
        'source_channel_id' => 'integer',
        'target_channel_id' => 'integer',
        'forward_status' => 'integer',
        'callback_status' => 'integer',
        'retry_count' => 'integer',
        'organization_id' => 'integer',
        'source_data' => 'array',
        'transformed_data' => 'array',
        'target_response' => 'array',
        'callback_response' => 'array',
        'current_step' => 'integer',
        'step_data' => 'array',
        'step_responses' => 'array',
        'verify_attempts' => 'integer',
    ];

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 2;
    const STATUS_VERIFYING = 4; // 待验证（验证码已发送，等待提交验证码）

    const CALLBACK_PENDING = 0;
    const CALLBACK_SUCCESS = 1;
    const CALLBACK_FAILED = 2;

    public function sourceChannel(): BelongsTo
    {
        return $this->belongsTo(ThirdChannels::class, 'source_channel_id', 'id');
    }

    public function targetChannel(): BelongsTo
    {
        return $this->belongsTo(ThirdChannels::class, 'target_channel_id', 'id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    /**
     * 按手机号查询待验证的中转记录
     */
    public function scopePendingVerify($query, string $mobile, string $sourcePid)
    {
        return $query->where('mobile', $mobile)
            ->where('source_pid', $sourcePid)
            ->where('forward_status', self::STATUS_VERIFYING);
    }
}