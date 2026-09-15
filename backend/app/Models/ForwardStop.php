<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForwardStop extends Model
{
    protected $table = 'forward_stops';

    protected $fillable = [
        'trace_id',
        'channel_id',
        'source_pid',
        'source_order_no',
        'mobile',
        'step',
        'condition_id',
        'condition_type',
        'rule_message',
        'request_data',
    ];

    protected $casts = [
        'id' => 'integer',
        'channel_id' => 'integer',
        'request_data' => 'array',
    ];
}