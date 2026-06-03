<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use App\Models\Base\BaseProductOrder;

/**
 * 产品订单模型
 * * @property int $id
 * @property string $order_no
 * @property int $created_by
 * @property int $updated_by
 */
class ProductOrder extends BaseProductOrder implements Auditable
{

    // 指定表名
    protected $table = 'product_orders';


}
