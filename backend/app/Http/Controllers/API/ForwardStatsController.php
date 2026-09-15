<?php

namespace App\Http\Controllers\API;

use App\Enums\MobileErrorCode;
use App\Http\Controllers\Controller;
use App\Models\ForwardStatsDaily;
use App\Models\ThirdChannels;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 验证码流程按天错误码统计
 *
 * 数据来源：forward_stats_daily（由 stats:forward-daily 定时聚合生成）
 * 统计口径：
 *   step 0 = 请求验证码(getCode)
 *   step 1 = 提交订单(submit)
 *   is_success = 1 成功 / 0 失败
 */
class ForwardStatsController extends Controller
{
    use ApiResponse;

    /**
     * 按天汇总（每日请求总数、验证码成功/失败、最终成交/失败）
     * GET /api/v1/forward-stats/daily
     *
     * 参数：
     *   start_date / end_date - 日期范围（Y-m-d），缺省近 7 天
     *   product_id           - 按产品筛选（可选）
     */
    public function daily(Request $request): JsonResponse
    {
        $start = $request->get('start_date')
            ? date('Y-m-d', strtotime($request->get('start_date')))
            : date('Y-m-d', strtotime('-6 days'));
        $end = $request->get('end_date')
            ? date('Y-m-d', strtotime($request->get('end_date')))
            : date('Y-m-d');

        $rows = ForwardStatsDaily::selectRaw("
                stat_date,
                step,
                is_success,
                product_id,
                error_code,
                SUM(count) as total
            ")
            ->where('stat_date', '>=', $start)
            ->where('stat_date', '<=', $end)
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->get('product_id')))
            ->groupByRaw("stat_date, step, is_success, product_id, error_code")
            ->orderBy('stat_date')
            ->get();

        // 按日期聚合出每天的 请求/验证码/成交 四要素
        $daily = [];
        foreach ($rows as $row) {
            $key = $row->stat_date->format('Y-m-d');
            if (! isset($daily[$key])) {
                $daily[$key] = [
                    'date' => $key,
                    'request_total' => 0,      // 请求总数 = step0 成功+失败
                    'verify_success' => 0,     // 验证码请求成功
                    'verify_failed' => 0,      // 验证码请求失败
                    'deal_success' => 0,       // 最终成交（submit成功）
                    'deal_failed' => 0,        // 最终失败（submit失败）
                ];
            }

            $count = (int) $row->total;
            if ($row->step == 0) {
                $daily[$key]['request_total'] += $count;
                if ($row->is_success) {
                    $daily[$key]['verify_success'] += $count;
                } else {
                    $daily[$key]['verify_failed'] += $count;
                }
            } elseif ($row->step == 1) {
                if ($row->is_success) {
                    $daily[$key]['deal_success'] += $count;
                } else {
                    $daily[$key]['deal_failed'] += $count;
                }
            }
        }

        return $this->success([
            'period' => ['start' => $start, 'end' => $end],
            'daily' => array_values($daily),
        ]);
    }

    /**
     * 按错误码统计（按渠道 · 逐错误码表格）
     * GET /api/v1/forward-stats/error-codes
     *
     * 参数：
     *   start_date / end_date - 日期范围（Y-m-d），缺省近 7 天
     *   product_id       - 按产品筛选（可选）
     *   step             - 环节筛选：0/1（可选）
     *
     * 返回结构（每渠道独立表格）：
     *   by_a: [{
     *     target_pid, channel_name,
     *     steps_request: { 0: 请求总数, 1: 请求总数 },   // 该渠道该动作全部请求(成功+失败)
     *     rows: [{ error_code, sub_error_code, error_msg, step, count, request_total, ratio }]
     *   }]
     *   ratio = count / request_total （错误数 / 该动作请求总数 × 100）
     */
    public function errorCodes(Request $request): JsonResponse
    {
        $start = $request->get('start_date')
            ? date('Y-m-d', strtotime($request->get('start_date')))
            : date('Y-m-d', strtotime('-6 days'));
        $end = $request->get('end_date')
            ? date('Y-m-d', strtotime($request->get('end_date')))
            : date('Y-m-d', strtotime(date('Y-m-d')));

        $productId = $request->filled('product_id') ? $request->get('product_id') : null;
        $stepFilter = $request->filled('step') ? (int) $request->get('step') : null;

        // 各渠道 + 动作 的请求总数（成功+失败）
        $reqByStep = ForwardStatsDaily::selectRaw("target_pid, step, SUM(count) as request_total")
            ->where('stat_date', '>=', $start)
            ->where('stat_date', '<=', $end)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->groupByRaw("target_pid, step")
            ->get()
            ->map(function ($r) {
                $r->request_total = (int) $r->request_total;
                return $r;
            })
            ->keyBy(fn ($r) => $r->target_pid.'|'.$r->step);

        // 失败错误码明细
        $rows = ForwardStatsDaily::selectRaw("
                target_pid,
                step,
                error_code,
                sub_error_code,
                SUM(count) as total
            ")
            ->where('stat_date', '>=', $start)
            ->where('stat_date', '<=', $end)
            ->where('is_success', 0)
            ->whereNotNull('error_code')
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($stepFilter !== null, fn ($q) => $q->where('step', $stepFilter))
            ->groupByRaw("target_pid, step, error_code, sub_error_code")
            ->orderBy('target_pid')
            ->orderBy('step')
            ->orderByRaw("SUM(count) DESC")
            ->get();

        $byA = [];
        foreach ($rows as $row) {
            $pid = $row->target_pid ?? '?';
            if (! isset($byA[$pid])) {
                $byA[$pid] = [
                    'target_pid' => $pid,
                    'channel_name' => $this->channelName($pid),
                    'steps_request' => [0 => 0, 1 => 0],
                    'rows' => [],
                ];
            }
            $byA[$pid]['steps_request'][(int) $row->step] =
                (int) ($reqByStep[$pid.'|'.$row->step]->request_total ?? 0);

            $enum = MobileErrorCode::fromCode($row->error_code);
            $subEnum = MobileErrorCode::fromCode($row->sub_error_code);
            $requestTotal = (int) ($reqByStep[$pid.'|'.$row->step]->request_total ?? 0);

            $byA[$pid]['rows'][] = [
                'error_code' => $row->error_code,
                'sub_error_code' => $row->sub_error_code,
                'error_msg' => $row->sub_error_code
                    ? ($subEnum ? $subEnum->label() : '其他错误')
                    : ($enum ? $enum->label() : '其他错误'),
                'step' => (int) $row->step,
                'count' => (int) $row->total,
                'request_total' => $requestTotal,
                'ratio' => $requestTotal > 0
                    ? round(((int) $row->total / $requestTotal) * 100, 2)
                    : 0,
            ];
        }

        return $this->success([
            'period' => ['start' => $start, 'end' => $end],
            'by_a' => array_values($byA),
        ]);
    }

    /**
     * 根据 PID 查询渠道名称（上游 A）
     */
    protected function channelName(string $pid): string
    {
        static $cache = [];
        if (! array_key_exists($pid, $cache)) {
            $cache[$pid] = ThirdChannels::where('pid', $pid)->value('name') ?? $pid;
        }
        return $cache[$pid];
    }
}