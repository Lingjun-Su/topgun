<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallbackLog extends Model
{
    protected $table = 'callback_logs';

    protected $fillable = [
        'channel_pid',
        'callback_type',
        'request_method',
        'request_url',
        'request_params',
        'request_headers',
        'raw_body',
        'status',
        'processed_at',
        'process_result',
        'process_error',
        'forward_order_id',
        'product_order_id',
    ];

    protected $casts = [
        'request_params' => 'array',
        'request_headers' => 'array',
        'process_result' => 'array',
        'status' => 'integer',
        'forward_order_id' => 'integer',
        'product_order_id' => 'integer',
    ];

    const STATUS_PENDING = 0;
    const STATUS_PROCESSED = 1;
    const STATUS_FAILED = 2;
}