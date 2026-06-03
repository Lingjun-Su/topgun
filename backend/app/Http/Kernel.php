<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\VerifyChannelSignature;

//旧版Middlestrap的注册文件，11以上放在bootstrap\app.php 中
class Kernel extends HttpKernel
{
    // 在 $routeMiddleware 数组中添加
    protected $routeMiddleware = [
        // ... 其他中间件
        // 'VerifyChannelSignature' => \App\Http\Middleware\VerifyChannelSignature::class,
    ];
}
