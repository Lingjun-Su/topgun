<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; // 引入新特性
use Illuminate\Support\Carbon;
use App\Models\ThirdChannels;
use App\Models\ProductOrder;
use App\Models\Products;
use App\Models\Business;
// 引入 Product 模型，假设 ProductOrder 关联 Product
use App\Models\Product;

abstract class BaseProductOrder extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'settlement_id', 'settle_status', 'pid', 'bus_code', 'sku_code',
        'user_phone', 'user_nick', 'user_type', 'user_create', 'user_status',
        'user_source', 'user_remark', 'wx_nick', 'wx_name', 'wx_unionid',
        'province_code', 'city_code', 'order_no', 'create_time_ts', 'order_time',
        'order_status', 'coupon', 'coupon_code', 'coupon_write_off', 'type',
        'platform', 'pack', 'url', 'ip', 'sms_time', 'code', 'price',
        'quantity', 'total_amount', 'sync_status', 'ext_json','internal_amount',
        'product_id','business_id','channel_id','organization_id',
    ];

    protected $casts = [
        'settlement_id'    => 'integer',
        'settle_status'    => 'integer',
        'user_status'      => 'integer',
        'order_status'     => 'integer',
        'coupon_write_off' => 'integer',
        'sync_status'      => 'integer',

        // 时间格式化统一管理
        'user_create'   => 'datetime:Y-m-d H:i:s',
        'order_time'    => 'datetime:Y-m-d H:i:s',
        'pushed_at'     => 'datetime:Y-m-d H:i:s',
        'created_at'    => 'datetime:Y-m-d H:i:s',
        'updated_at'    => 'datetime:Y-m-d H:i:s',

        'price'         => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'internal_amount'  => 'decimal:2',
        'quantity'      => 'integer',
        'ext_json'      => 'array',
    ];


    /**
     * 产品订单简报
     */
    public static function getProductOrderBriefing(array $filters = [])
    {
        // 1. 基础查询
        $query = ProductOrder::query();

        // 2. 动态过滤逻辑
        if (!empty($filters['bus_code'])) {
            $query->where('bus_code', $filters['bus_code']);
        }
        if (!empty($filters['pid'])) {
            $query->where('pid', $filters['pid']);
        }

        // 3. 聚合查询（针对 SQL Server 优化）
        // 注意：SQL Server 的 DATEDIFF(week...) 默认周日为一周开始
        return [
            'today' => (clone $query)
                ->whereRaw("DATEDIFF(day, order_time, GETDATE()) = 0")
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),
            'yesterday' => (clone $query)
                ->whereRaw("DATEDIFF(day, order_time, GETDATE()) = 1")
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),

            'week' => (clone $query)
                ->whereRaw("DATEDIFF(week, order_time, GETDATE()) = 0")
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),
            'last_week' => (clone $query)
                ->whereRaw("DATEDIFF(week, order_time, GETDATE()) = 1")
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),

            'month' => (clone $query)
                ->whereRaw("DATEDIFF(month, order_time, GETDATE()) = 0")
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),
            'last_month' => (clone $query)
                ->whereRaw("DATEDIFF(month, order_time, GETDATE()) = 1")
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),

            'total' => (clone $query)
                ->selectRaw('ISNULL(SUM(quantity), 0) as total_qty, ISNULL(SUM(total_amount), 0) as total_amt')
                ->first(),
        ];
    }

    public function thirdChannel(): BelongsTo
    {
        return $this->belongsTo(ThirdChannels::class, 'pid', 'pid');
    }
    public function products(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id', 'id');
    }
    public function Business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id', 'id');
    }

    //读取本月的日销售数据，用于图表的折线显示
    public static function getSalesMonthCharts()
    {
        // 获取最近一个月的数据
        $endDate = now()->endOfDay();
        $startDate = now()->subDays(29)->startOfDay(); // 近30天，包括今天

        // 使用 SQL Server 的日期函数进行分组查询
        $results = ProductOrder::query()
            ->whereBetween('order_time', [$startDate, $endDate])
            ->selectRaw("
                FORMAT(order_time, 'yyyy-MM-dd') as day,
                ISNULL(SUM(quantity), 0) as total_quantity,
                ISNULL(SUM(total_amount), 0) as total_amount
            ")
            ->groupByRaw("FORMAT(order_time, 'yyyy-MM-dd')")
            ->orderBy('day')
            ->get();

        // 生成完整的30天数据，包括没有数据的日期
        $chartData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayKey = $currentDate->format('Y-m-d');
            $found = $results->firstWhere('day', $dayKey);

            $chartData[] = [
                'x' => $dayKey,
                'y' => $found ? (int)$found->total_quantity : 0,
                'amount' => $found ? (float)$found->total_amount : 0.00
            ];

            $currentDate->addDay();
        }

        return $chartData;
    }

    public static function getSalesPieCharts()
    {
        // 获取最近一周的数据
        $startDate = now()->subDays(7)->startOfDay();
        $endDate = now()->endOfDay();

        // 查询一周内各产品的销售数量
        $results = ProductOrder::query()
            ->whereBetween('order_time', [$startDate, $endDate])
            ->join('products', 'product_orders.product_id', '=', 'products.id') // Ensure 'products' table exists and 'product_orders.product_id' is a foreign key
            ->selectRaw("
                products.id as product_id,
                products.name as product_name,
                products.sku_code as sku_code,
                ISNULL(SUM(product_orders.quantity), 0) as total_quantity,
                ISNULL(SUM(product_orders.total_amount), 0) as total_amount,
                COUNT(DISTINCT product_orders.id) as order_count
            ")
            ->groupBy('products.id', 'products.name', 'products.sku_code')
            ->orderByDesc('total_quantity')
            ->limit(8) // 限制最多8个产品，避免饼图过于复杂
            ->get();

        // 计算总量
        $totalQuantity = $results->sum('total_quantity');
        $totalAmount = $results->sum('total_amount');

        // 准备饼图数据
        $pieData = [];
        $colors = [
            '#1976D2', '#2196F3', '#03A9F4', '#00BCD4', '#009688',
            '#4CAF50', '#8BC34A', '#CDDC39', '#FFC107', '#FF9800'
        ];

        foreach ($results as $index => $item) {
            $percentage = $totalQuantity > 0 ? round(($item->total_quantity / $totalQuantity) * 100, 2) : 0;

            $pieData[] = [
                'product_id' => (int)$item->product_id,
                'product_name' => $item->product_name,
                'sku_code' => $item->sku_code,
                'quantity' => (int)$item->total_quantity,
                'amount' => (float)$item->total_amount,
                'percentage' => $percentage,
                'color' => $colors[$index % count($colors)],
                'order_count' => (int)$item->order_count
            ];
        }

        // 获取在主要产品列表中未包含的产品ID
        $excludeProductIds = $results->pluck('product_id')->toArray();

        // 计算其他产品的总量
        $otherQuery = ProductOrder::query()
            ->whereBetween('order_time', [$startDate, $endDate]);

        if (!empty($excludeProductIds)) {
            $otherQuery->whereNotIn('product_id', $excludeProductIds);
        }

        $otherQuantity = $otherQuery->sum('quantity');
        $otherAmount = $otherQuery->sum('total_amount');
        // If 'product_id' is nullable or 0 means 'no product', consider orders without a valid product_id as 'other'
        // Or if we want to count distinct orders for "other products"
        $otherOrderCount = (clone $otherQuery)->count();

        // 如果有其他产品，添加到饼图数据中
        if ($otherQuantity > 0) {
            // Recalculate total quantity for percentage calculation including 'other'
            $grandTotalQuantity = $totalQuantity + $otherQuantity;
            $otherPercentage = $grandTotalQuantity > 0 ? round(($otherQuantity / $grandTotalQuantity) * 100, 2) : 0;

            $pieData[] = [
                'product_id' => 0, // Conventionally 0 for 'other'
                'product_name' => '其他产品',
                'sku_code' => 'OTHER',
                'quantity' => (int)$otherQuantity,
                'amount' => (float)$otherAmount,
                'percentage' => $otherPercentage,
                'color' => '#9E9E9E',
                'order_count' => (int)$otherOrderCount
            ];
            $totalQuantity = $grandTotalQuantity; // Update total quantity
            $totalAmount += $otherAmount; // Update total amount
        }

        return [
            'time_range' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s'),
                'days' => 7
            ],
            'total_quantity' => $totalQuantity, // This already includes otherQuantity
            'total_amount' => $totalAmount, // This already includes otherAmount
            'total_products' => count($pieData),
            'data' => $pieData
        ];
    }


    public static function getSalesTodayData()
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        // 查询当天数据
        $todayData = ProductOrder::query()
            ->whereBetween('order_time', [$todayStart, $todayEnd])
            ->selectRaw('
                ISNULL(SUM(quantity), 0) as total_quantity,
                ISNULL(SUM(total_amount), 0) as total_amount
            ')
            ->first();

        // 查询昨天数据
        $yesterdayData = ProductOrder::query()
            ->whereBetween('order_time', [$yesterdayStart, $yesterdayEnd])
            ->selectRaw('
                ISNULL(SUM(quantity), 0) as total_quantity,
                ISNULL(SUM(total_amount), 0) as total_amount
            ')
            ->first();

        // 计算百分比变化
        $yesterdayQty = (float)($yesterdayData->total_quantity ?? 0);
        $todayQty = (float)($todayData->total_quantity ?? 0);
        $yesterdayAmt = (float)($yesterdayData->total_amount ?? 0);
        $todayAmt = (float)($todayData->total_amount ?? 0);

        $quantityChangePercent = $yesterdayQty != 0
            ? round((($todayQty - $yesterdayQty) / $yesterdayQty) * 100, 2)
            : ($todayQty > 0 ? 100.00 : 0.00);

        $amountChangePercent = $yesterdayAmt != 0
            ? round((($todayAmt - $yesterdayAmt) / $yesterdayAmt) * 100, 2)
            : ($todayAmt > 0 ? 100.00 : 0.00);

        return [
            'today' => [
                'quantity' => (int)$todayQty,
                'amount' => (float)$todayAmt,
                'date' => now()->format('Y-m-d')
            ],
            'yesterday' => [
                'quantity' => (int)$yesterdayQty,
                'amount' => (float)$yesterdayAmt,
                'date' => now()->subDay()->format('Y-m-d')
            ],
            'change_percent' => [
                'quantity' => $quantityChangePercent,
                'amount' => $amountChangePercent
            ]
        ];
    }

    public static function getBusinessStats($request)
    {

        try {
            $query = DB::table('sales')
                ->whereNull('deleted_at') // 逻辑删除原则
                ->when($businessId, fn($q) => $q->where('business_id', $businessId))
                ->when($productId, fn($q) => $q->where('product_id', $productId));
                // ->whereBetween('created_at', $dateRange);

            $data = $query->select([
                DB::raw("FORMAT(created_at, '{$dateFormat}') as time_label"),
                DB::raw("SUM(amount) as total_value")
            ])
            ->groupBy(DB::raw("FORMAT(created_at, '{$dateFormat}')"))
            ->orderBy('time_label', 'asc')
            ->get();

            return $this->success($data, '报表数据查询成功');
        } catch (\Exception $e) {
            return $this->error('报表数据获取失败：' . $e->getMessage(), 500);
        }
    }

    // 定义可搜索的字段范围（Scope），提高代码可读性
    public function scopeByBusiness($query, $id)
    {
        return $id ? $query->where('business_id', $id) : $query;
    }

    public function scopeByProduct($query, $id)
    {
        return $id ? $query->where('product_id', $id) : $query;
    }
}

