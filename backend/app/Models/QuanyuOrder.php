<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class QuanyuOrder extends Model
{
    use SoftDeletes; // 开启逻辑删除

    protected $table = 'quanyu_orders';

    protected $fillable = [
        'mobile', 'pid', 'bus_code', 'sku_code', 'order_no',
        'create_time', 'type', 'platform', 'pack', 'url',
        'ip', 'sms_time', 'code','order_status',
        'price','total_amount','quantity','cancel_sync_status','cancel_sync_error',
        'sync_status','sync_error', 'pushed_at',
        'organization_id'
    ];

    /**
     * 自动审计：在保存时记录操作人
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(order_product::class, 'type', 'id');
    }
}
