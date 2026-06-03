<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Reports\ProductOrderReportService;

class ProductOrderReportController extends Controller
{
    protected $reportService;

    // 通过构造函数注入 Service
    public function __construct(ProductOrderReportService $reportService)
    {
        $this->reportService = $reportService;
    }
    /**
     * 获取产品订单简报
     */
    public function getProductOrderBriefing(Request $request)
    {
        // 1. 严格验证输入参数
        // 特别注意：Laravel 12 推荐使用 validated() 获取验证后的数据，防止注入
        $validated = $request->validate([
            'time_type'  => 'nullable|in:order_time,created_at', // 修正了原本代码中的 create_at 为 created_at
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
            'pid'        => 'nullable|string'
        ]);

        try {
            // 2. 直接调用 Model 静态方法，传入验证后的参数数组
            $result = ProductOrder::getProductOrderBriefing($validated);

            // 3. 使用统一的成功响应方法（假设已在父类 Controller 使用了 ApiResponse Trait）
            return $this->success($result, '报表统计数据获取成1功');

        } catch (\Exception $e) {
            // 记录详细异常日志以便排查
            Log::error("Sales Report API Error: " . $e->getMessage(), [
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error('报表生成失败，请稍后重试'.$e->getMessage());
        }
    }

    //读取日销量拆线
    public function getSalesMonthCharts(Request $request)
    {
        $data =ProductOrder::getSalesMonthCharts();
        return $this->success($data,'读取图表数据成功');
    }

    //读取产品销量占比图
    public function getSalesPieCharts(Request $request)
    {
        $data =ProductOrder::getSalesPieCharts();
        return $this->success($data,'读取产品销量占比成功');
    }

    //读取当天产品销量
    public function getSalesTodayData(Request $request)
    {
        $data =ProductOrder::getSalesTodayData();
        return $this->success($data,'读取当天销售数据');
    }

    /**
     * 获取业务统计数据
     * SQL Server 2019 环境
     */
    public function getBusinessStats(Request $request)
    {
         // 1. 验证输入（生产环境建议使用 FormRequest）
        $filters = $request->validate([
            'type' => 'required|in:day,month,year',
            'business_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        try {
            // 2. 调用服务层逻辑
            $data = $this->reportService->getAggregatedData($filters);

            // 3. 统一成功响应
            return $this->success($data, '报表数据加载成功');
        } catch (\Exception $e) {
            // 4. 统一错误响应
            return $this->error('查询失败：' . $e->getMessage(), 500);
        }
    }
}
