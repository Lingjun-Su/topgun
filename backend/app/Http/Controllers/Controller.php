<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;

abstract class Controller
{
    // 统一标准返回
    use ApiResponse;
}
