<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Models\QuanyuOrder;
use App\Models\ForwardOrder;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushStatsController extends Controller
{
    use ApiResponse;

    /**
     * 推送统计总览
     * GET /api/v1/push-stats/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 7);
        $startDate = now()->subDays($days)->startOfDay();

        // 1. 全域订单推送统计
        $quanyuStats = QuanyuOrder::where('created_at', '>=', $startDate)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN sync_status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sync_status = 1 THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN sync_status = 2 THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN sync_status = 3 THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        // 2. 数据中转统计
        $forwardStats = ForwardOrder::where('created_at', '>=', $startDate)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN forward_status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN forward_status = 1 THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN forward_status = 2 THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        // 3. 每日推送趋势（最近7天）
        $dailyTrend = QuanyuOrder::where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->selectRaw("
                FORMAT(created_at, 'yyyy-MM-dd') as date,
                COUNT(*) as total,
                SUM(CASE WHEN sync_status = 1 THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN sync_status = 2 THEN 1 ELSE 0 END) as failed
            ")
            ->groupByRaw("FORMAT(created_at, 'yyyy-MM-dd')")
            ->orderBy('date')
            ->get();

        // 4. 失败原因分布
        $failReasons = QuanyuOrder::where('created_at', '>=', $startDate)
            ->where('sync_status', 2)
            ->whereNotNull('sync_error')
            ->where('sync_error', '!=', '')
            ->selectRaw("sync_error, COUNT(*) as count")
            ->groupBy('sync_error')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // 5. 各渠道推送量
        $channelStats = QuanyuOrder::where('created_at', '>=', $startDate)
            ->selectRaw("pid, COUNT(*) as total, SUM(CASE WHEN sync_status = 1 THEN 1 ELSE 0 END) as success")
            ->groupBy('pid')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $this->success([
            'period' => $days . '天',
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => now()->format('Y-m-d H:i:s'),
            'quanyu_stats' => $quanyuStats,
            'forward_stats' => $forwardStats,
            'daily_trend' => $dailyTrend,
            'fail_reasons' => $failReasons,
            'channel_stats' => $channelStats,
        ]);
    }
}