<?php

namespace App\Jobs;

use App\Models\CallbackLog;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\Interpreters\DefaultCallbackInterpreter;
use App\Services\ThirdChannel\Interpreters\ProvinceOrderInterpreter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $callbackLogId;

    /**
     * 解释器注册表
     */
    protected array $interpreters = [
        'province_order_result' => ProvinceOrderInterpreter::class,
        'default' => DefaultCallbackInterpreter::class,
    ];

    public function __construct(int $callbackLogId)
    {
        $this->callbackLogId = $callbackLogId;
    }

    public function handle(): void
    {
        $log = CallbackLog::find($this->callbackLogId);

        if (! $log) {
            Log::error('ProcessCallbackJob: 回调日志不存在', [
                'callback_log_id' => $this->callbackLogId,
            ]);
            return;
        }

        // 已处理过的跳过
        if ($log->status !== CallbackLog::STATUS_PENDING) {
            Log::warning('ProcessCallbackJob: 回调日志已处理，跳过', [
                'callback_log_id' => $log->id,
                'status' => $log->status,
            ]);
            return;
        }

        // 查询渠道配置
        $channel = ThirdChannels::where('pid', $log->channel_pid)->first();
        if (! $channel) {
            $this->markFailed($log, '渠道不存在');
            return;
        }

        $callbackConfig = $channel->callback_config ?? [];
        if (empty($callbackConfig)) {
            $this->markFailed($log, '渠道未配置回调接收规则');
            return;
        }

        $type = $callbackConfig['type'] ?? 'default';

        // 选择解释器
        $interpreterClass = $this->interpreters[$type] ?? $this->interpreters['default'];

        try {
            $interpreter = app($interpreterClass);
            $result = $interpreter->interpret($log, $callbackConfig);

            // 更新回调日志
            $log->update([
                'status' => CallbackLog::STATUS_PROCESSED,
                'processed_at' => now(),
                'process_result' => $result,
                'forward_order_id' => $result['forward_order_id'] ?? null,
                'product_order_id' => $result['product_order_id'] ?? null,
            ]);

            Log::info('回调处理完成', [
                'callback_log_id' => $log->id,
                'type' => $type,
                'result' => $result['success'] ? 'success' : 'error',
                'forward_order_id' => $result['forward_order_id'] ?? null,
                'product_order_id' => $result['product_order_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('回调处理异常', [
                'callback_log_id' => $log->id,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markFailed($log, $e->getMessage());
        }
    }

    protected function markFailed(CallbackLog $log, string $error): void
    {
        $log->update([
            'status' => CallbackLog::STATUS_FAILED,
            'processed_at' => now(),
            'process_error' => $error,
        ]);
    }
}