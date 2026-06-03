<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProductOrderStatusTest extends Model
{
    protected $table = 'product_order_status_tests'; // 明确指定表名

    protected $fillable = [
        'pid',
        'order_id',
        'order_no',
        'old_status',
        'new_status',
        'price',
        'total_amount',
        'operator'
    ];
}
