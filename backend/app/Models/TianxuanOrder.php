<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class TianxuanOrder extends Model
{
    use SoftDeletes; // 开启逻辑删除

    protected $table = 'tianxuan_orders';

    protected $fillable = [
        'mobile', 'pid', 'bus_code', 'sku_code', 'order_no',
        'create_time', 'type', 'platform', 'pack', 'url',
        'ip', 'sms_time', 'code'
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
}
