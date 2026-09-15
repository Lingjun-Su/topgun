<?php

namespace App\Console\Commands;

use App\Models\ForwardOrder;
use App\Models\ForwardStatsDaily;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 按天聚合验证码流程统计数据
 *
 * 每条 forward_orders 记录包含两步：
 *   step 0 = 验证码请求(getCode)
 *   step 1 = 订单提交(submit)
 *
 * 统计口径：
 *   - step0 成功：状态 VERIFYING / SUCCESS，或 FAILED 但已进入 submit(current_step>=1)
 *   - step0 失败：状态 FAILED 且停留在 getCode(current_step==0)
 *   - step1 成功：状态 SUCCESS
 *   - step1 失败：状态 FAILED 且已进入 submit(current_step>=1)
 *
 * 用法：
 *   php artisan stats:forward-daily                  # 默认聚合昨天
 *   php artisan stats:forward-daily --date=2026-09-01 # 聚合指定日期
 *   php artisan stats:forward-daily --rebuild         # 重建历史（--date 指定起点）
 */
class RebuildForwardDailyStats extends Command
{
    protected $signature = 'stats:forward-daily
                            {--date= : 聚合的业务日期 Y-m-d，缺省为昨天}
                            {--rebuild : 重建指定日期段（配合 --date 作为起点，无 --date 时重建全部）}';

    protected $description = '按天聚合请求验证码/提交订单的错误码统计';

    public function handle(): int
    {
        try {
            return $this->aggregate();
        } catch (\Throwable $e) {
            Log::error('按天聚合统计异常终止', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('任务异常终止: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    protected function aggregate(): int
    {
        $rebuild = (bool) $this->option('rebuild');
        $start = $rebuild
            ? ($this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : Carbon::parse('2020-01-01')->startOfDay())
            : ($this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : Carbon::yesterday()->startOfDay());
        $end = $rebuild ? Carbon::now()->startOfDay() : $start->copy()->endOfDay();

        $this->info('开始聚合转发统计...');
        $this->line("  日期范围: {$start->toDateString()} ~ {$end->toDateString()}" . ($rebuild ? ' (重建)' : ''));

        // 拉取范围内订单，逐条映射统计键
        // 分组维度：优先取 product_orders.pid（订单归属渠道 C），无对应订单时兜底用原 target_pid
        $orders = ForwardOrder::query()
            ->leftJoin('product_orders', function ($join) {
                $join->on('product_orders.order_no', '=', 'forward_orders.source_order_no')
                    ->whereNull('product_orders.deleted_at');
            })
            ->where('forward_orders.created_at', '>=', $start)
            ->where('forward_orders.created_at', '<', $end->copy()->addDay())
            ->select([
                'forward_orders.id',
                'forward_orders.created_at',
                'forward_orders.forward_status',
                'forward_orders.current_step',
                'forward_orders.product_id',
                'forward_orders.error_code',
                'forward_orders.target_pid',
                'forward_orders.forward_error',
                \Illuminate\Support\Facades\DB::raw('product_orders.pid as biz_pid'),
                \Illuminate\Support\Facades\DB::raw('product_orders.product_id as biz_product_id'),
            ])
            ->get();

        $this->line("  获取订单数: {$orders->count()}");
        if ($orders->isEmpty()) {
            $this->info('完成，无数据。');
            return self::SUCCESS;
        }

        // 聚合：key => count
        $totals = [];
        foreach ($orders as $order) {
            foreach ($this->mapToStats($order) as $key => $attrs) {
                $totals[$key] = $totals[$key] ?? $attrs;
                $totals[$key]['count'] = ($totals[$key]['count'] ?? 0) + 1;
            }
        }

        $processed = 0;
        DB::transaction(function () use ($totals, &$processed, $rebuild, $start, $end) {
            if ($rebuild) {
                // 重建模式下先清空范围内统计再插入（幂等）
                $startDate = $start->toDateString();
                $endDate = $end->toDateString();
                ForwardStatsDaily::where('stat_date', '>=', $startDate)
                    ->where('stat_date', '<=', $endDate)
                    ->delete();
            }
            foreach ($totals as $attrs) {
                ForwardStatsDaily::updateOrCreate(
                    [
                        'stat_date' => $attrs['stat_date'],
                        'target_pid' => $attrs['target_pid'],
                        'step' => $attrs['step'],
                        'is_success' => $attrs['is_success'],
                        'product_id' => $attrs['product_id'],
                        'error_code' => $attrs['error_code'],
                        'sub_error_code' => $attrs['sub_error_code'],
                    ],
                    ['count' => $attrs['count']]
                );
                $processed++;
            }
        });

        $this->info("完成。写入统计分组 {$processed} 条。");
        Log::info('转发按天聚合统计完成', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'orders' => $orders->count(),
            'groups' => $processed,
            'rebuild' => $rebuild,
        ]);
        return self::SUCCESS;
    }

    /**
     * 将单条 ForwardOrder 映射为一个或多个统计键（最多两步）
     *
     * @return array<string, array>
     */
    protected function mapToStats(ForwardOrder $order): array
    {
        $date = $order->created_at->toDateString();
        $status = $order->forward_status;
        $step = (int) $order->current_step;

        $keys = [];

        // 分组渠道：优先 product_orders.pid（订单归属渠道 C），无对应订单时兜底原 target_pid（上游 A）
        $pooledPid = $order->biz_pid ?? $order->target_pid ?? null;
        // 分组产品：优先 product_orders.product_id（products 表 id），无对应订单时兜底 forward_orders.product_id
        $pooledProductId = $order->biz_product_id ?? $order->product_id;

        $base = [
            'stat_date' => $date,
            'target_pid' => $pooledPid,
            'product_id' => $pooledProductId,
        ];

        // step0: 验证码请求
        $getCodeSuccess = $status === ForwardOrder::STATUS_VERIFYING
            || $status === ForwardOrder::STATUS_SUCCESS
            || ($status === ForwardOrder::STATUS_FAILED && $step >= 1);
        $getCodeFail = $status === ForwardOrder::STATUS_FAILED && $step < 1;

        $sub = $this->extractSubErrorCode((string) $order->forward_error);

        if ($getCodeSuccess) {
            $keys[$this->hashKey($date, $pooledPid, 0, 1, $pooledProductId, null, null)] =
                $base + ['step' => 0, 'is_success' => 1, 'error_code' => null, 'sub_error_code' => null];
        } elseif ($getCodeFail) {
            $keys[$this->hashKey($date, $pooledPid, 0, 0, $pooledProductId, $order->error_code, $sub)] =
                $base + ['step' => 0, 'is_success' => 0, 'error_code' => $order->error_code, 'sub_error_code' => $sub];
        }

        // step1: 订单提交（仅当流程进入 submit 步骤）
        if ($step >= 1 && $status !== ForwardOrder::STATUS_PENDING) {
            $submitSuccess = $status === ForwardOrder::STATUS_SUCCESS;
            if ($submitSuccess) {
                $keys[$this->hashKey($date, $pooledPid, 1, 1, $pooledProductId, null, null)] =
                    $base + ['step' => 1, 'is_success' => 1, 'error_code' => null, 'sub_error_code' => null];
            } elseif ($status === ForwardOrder::STATUS_FAILED) {
                $keys[$this->hashKey($date, $pooledPid, 1, 0, $pooledProductId, $order->error_code, $sub)] =
                    $base + ['step' => 1, 'is_success' => 0, 'error_code' => $order->error_code, 'sub_error_code' => $sub];
            }
        }

        return $keys;
    }

    protected function hashKey($date, $targetPid, $step, $success, $productId, $errorCode, $subErrorCode): string
    {
        return implode('|', [$date, $targetPid, $step, $success, $productId, $errorCode, $subErrorCode]);
    }

    /**
     * 从 forward_error 文本中提取第一个 400xx 子错误码（如 E0005 办理业务失败时附带的 40008）
     */
    protected function extractSubErrorCode(string $msg): ?string
    {
        if ($msg === '') {
            return null;
        }
        if (preg_match('/400\d{2}/', $msg, $m)) {
            return $m[0];
        }
        return null;
    }
}
