<?php

namespace App\Jobs;

use App\Models\QuanyuOrder;
use App\Services\ThirdPartyApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 异步推送任务：将订单数据推送到另一家公司
 * 包含：签名生成、前置拦截、状态回写、指数退避重试
 */
class PushToXinquanyu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大重试次数
     * @var int
     */
    public $tries = 5;

    /**
     * 任务重试的间隔时间（秒）
     * 第一次失败后等10秒，第二次30秒，以此类推，减轻对方服务器压力
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 600];
    }

    /**
     * 任务数据
     */
    public function __construct(
        protected array $data,
        protected string $endpoint
    ) {}

    /**
     * 执行推送逻辑
     */
    public function handle(): void
    {
        // 1. 获取最新的数据库记录 (防止在排队期间管理员取消了任务)
        $order = QuanyuOrder::where('order_no', $this->data['order_no'])->first();

        // 2. 前置检查：如果订单不存在，或已被标记为“取消同步(-1)”
        if (!$order) {
            Log::warning("推送任务跳过：订单数据不存在", ['order_no' => $this->data['order_no']]);
            return;
        }

        if ($order->sync_status === -1) {
            Log::info("推送任务拦截：该订单已被管理员手动取消同步", ['order_no' => $order->order_no]);
            return;
        }

        // 3. 准备签名和请求参数
        $appKey = '1252KS25D7F3ZC7J'; // 建议从 config('services.partner.key') 读取
        // $url = 'http://cladmintest.xinquanyu.top' . $this->endpoint;
        $url = 'http://159.75.226.248:8080/' . $this->endpoint;

        // 生成签名 (字典序排序参数)
        $sign = ThirdPartyApiService::generateSign($this->data, $appKey);

        // 4. 发起 HTTP POST 请求
        try {
            $response = Http::timeout(10) // 10秒超时控制
                ->withHeaders([
                    'sign' => $sign,      // 对方要求的公共Header
                    'Accept' => 'application/json'
                ])
                ->post($url, $this->data);

            $result = $response->json();

            // 5. 判定结果
            if (isset($result['code']) && $result['code'] === 0) {
                // --- 同步成功 ---
                $order->update([
                    'sync_status' => 1, // 成功
                    'updated_at' => now()
                ]);
                Log::info("第三方推送成功", ['order_no' => $order->order_no, 'response' => $result]);
            } else {
                // --- 业务层面失败 (例如参数错、对方逻辑错) ---
                $order->update(['sync_status' => 2]); // 标记为失败，等待重试
                Log::error("第三方推送业务失败", [
                    'order_no' => $order->order_no,
                    'status' => $response->status(),
                    'response' => $result
                ]);

                // 抛出异常触发 Laravel 队列自动重试 (直到达到 $tries 次数)
                throw new \Exception("Partner API Error: " . ($result['msg'] ?? 'Unknown Error'));
            }

        } catch (\Exception $e) {
            // --- 网络异常、超时或代码报错 ---
            $order->update(['sync_status' => 2]);
            Log::error("推送任务异常(网络/连接)", [
                'order_no' => $this->data['order_no'],
                'error' => $e->getMessage()
            ]);

            // 重新抛出异常，让队列系统根据 $tries 和 backoff 处理重试
            throw $e;
        }
    }

    /**
     * 当任务达到最大重试次数 ($tries) 仍然失败时执行
     */
    public function failed(\Throwable $exception): void
    {
        $order = QuanyuOrder::where('order_no', $this->data['order_no'])->first();
        if ($order) {
            $order->update(['sync_status' => 2]); // 最终确定为失败
        }

        Log::critical("订单推送彻底失败，已停止所有重试", [
            'order_no' => $this->data['order_no'],
            'final_error' => $exception->getMessage()
        ]);
    }
}
