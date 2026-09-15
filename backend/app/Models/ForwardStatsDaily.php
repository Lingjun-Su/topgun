<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForwardStatsDaily extends Model
{
    protected $table = 'forward_stats_daily';

    protected $fillable = [
        'stat_date', 'target_pid', 'step', 'is_success', 'product_id', 'error_code', 'sub_error_code', 'count',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'step' => 'integer',
        'is_success' => 'integer',
        'count' => 'integer',
    ];
}