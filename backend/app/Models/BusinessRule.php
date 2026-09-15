<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessRule extends Model
{
    use SoftDeletes;

    protected $table = 'business_rules';

    protected $fillable = [
        'channel_id', 'business_id', 'product_id',
        'name', 'conditions', 'actions',
        'priority', 'status', 'description',
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'business_id' => 'integer',
        'product_id' => 'integer',
        'conditions' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'status' => 'integer',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ThirdChannels::class, 'channel_id', 'id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id', 'id');
    }
}