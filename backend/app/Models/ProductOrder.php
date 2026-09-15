<?php

namespace App\Models;

use App\Models\Base\BaseProductOrder;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * 产品订单模型
 *
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
