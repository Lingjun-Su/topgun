<?php

namespace App\Services\Reports;

use App\Models\ProductOrder;
use Illuminate\Support\Facades\DB;

class ProductOrderReportService
{
    /**
     * 执行业务报表聚合查询（含同期对比）
     */
    public function getAggregatedData(array $filters)
    {
        // SQL Server 日期格式化映射
        $dateFormat = match ($filters['type'] ?? 'day') {
            'year' => 'yyyy',
            'month' => 'yyyy-MM',
            default => 'yyyy-MM-dd',
        };

        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        // 计算同期范围（往前推相同天数/月数/年数）
        $periodLength = match ($filters['type'] ?? 'day') {
            'year' => 'year',
            'month' => 'month',
            default => 'day',
        };

        $prevStartDate = date('Y-m-d', strtotime("-1 {$periodLength}", strtotime($startDate)));
        $prevEndDate = date('Y-m-d', strtotime("-1 {$periodLength}", strtotime($endDate)));

        $baseQuery = function ($start, $end) use ($filters, $dateFormat) {
            return ProductOrder::query()
                ->byBusiness($filters['business_id'] ?? null)
                ->byProduct($filters['product_id'] ?? null)
                ->whereIn('order_status', [1, 2]) // 仅统计首次订购+继订中，排除未付款和退订
                ->whereBetween('order_time', [$start, $end])
                ->select([
                    DB::raw("FORMAT(order_time, '{$dateFormat}') as time_label"),
                    DB::raw('SUM(total_amount) as total_value'),
                    DB::raw('SUM(quantity) as total_quantity'),
                ])
                ->groupBy(DB::raw("FORMAT(order_time, '{$dateFormat}')"))
                ->orderBy('time_label', 'asc')
                ->get();
        };

        $currentData = $baseQuery($startDate, $endDate);
        $previousData = $baseQuery($prevStartDate, $prevEndDate);

        $currentTotalValue = (float) $currentData->sum('total_value');
        $currentTotalQty = (int) $currentData->sum('total_quantity');
        $previousTotalValue = (float) $previousData->sum('total_value');
        $previousTotalQty = (int) $previousData->sum('total_quantity');

        $valueChangePercent = $previousTotalValue != 0
            ? round((($currentTotalValue - $previousTotalValue) / $previousTotalValue) * 100, 2)
            : ($currentTotalValue > 0 ? 100.00 : 0.00);

        $qtyChangePercent = $previousTotalQty != 0
            ? round((($currentTotalQty - $previousTotalQty) / $previousTotalQty) * 100, 2)
            : ($currentTotalQty > 0 ? 100.00 : 0.00);

        return [
            'current' => $currentData,
            'previous' => $previousData,
            'summary' => [
                'current_total_value' => $currentTotalValue,
                'current_total_quantity' => $currentTotalQty,
                'previous_total_value' => $previousTotalValue,
                'previous_total_quantity' => $previousTotalQty,
                'value_change_percent' => $valueChangePercent,
                'quantity_change_percent' => $qtyChangePercent,
            ],
        ];
    }
}