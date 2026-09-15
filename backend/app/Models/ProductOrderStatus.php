<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrderStatus extends Model
{
    protected $table = 'product_order_statuses'; // 明确指定表名

    protected $fillable = [
        'pid',
        'order_id',
        'order_no',
        'old_status',
        'new_status',
        'price',
        'total_amount',
        'operator',
    ];
}
