<?php

namespace App\Services\Reports;

use App\Models\ProductOrder;
use Illuminate\Support\Facades\DB;

class ProductOrderReportService
{
    /**
     * 执行业务报表聚合查询
     */
    public function getAggregatedData(array $filters)
    {
        // SQL Server 日期格式化映射
        $dateFormat = match($filters['type'] ?? 'day') {
            'year'  => 'yyyy',
            'month' => 'yyyy-MM',
            default => 'yyyy-MM-dd',
        };

        return ProductOrder::query()
            ->byBusiness($filters['business_id'] ?? null)
            ->byProduct($filters['product_id'] ?? null)
            ->whereBetween('order_time', [$filters['start_date'], $filters['end_date']])
            ->select([
                DB::raw("FORMAT(order_time, '{$dateFormat}') as time_label"),
                DB::raw("SUM(total_amount) as total_value"),
                DB::raw("SUM(quantity) as total_quantity"),
            ])
            ->groupBy(DB::raw("FORMAT(order_time, '{$dateFormat}')"))
            ->orderBy('time_label', 'asc')
            ->get();
    }
}
