<?php

use App\Jobs\AlertJob;
use App\Jobs\OrderProcessJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每分钟扫描待处理订单（sync_status=0），自动派发推送
Schedule::job(new OrderProcessJob, 'high')->everyMinute()->withoutOverlapping(5);

// 每30分钟扫描失败记录（sync_status=2），发送告警通知
Schedule::job(new AlertJob, 'medium')->everyThirtyMinutes()->withoutOverlapping(10);

// 每分钟扫描待回调订单，推送回调结果给上家(A)
Schedule::command('callback:push-pending')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// 每天凌晨1点聚合前一天请求验证码/提交订单的错误码统计
Schedule::command('stats:forward-daily')
    ->dailyAt('01:00')
    ->withoutOverlapping(10)
    ->runInBackground();
