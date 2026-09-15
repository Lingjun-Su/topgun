<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Base\BaseProductOrderController;
use App\Models\ProductOrderTest;

class ProductOrderTestController extends BaseProductOrderController
{
    public function __construct()
    {
        parent::__construct(new ProductOrderTest());
    }
}
