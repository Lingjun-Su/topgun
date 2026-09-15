<?php

namespace App\Console\Commands;

use App\Enums\MobileErrorCode;
use App\Models\ForwardOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 回填历史失败订单的 error_code / sub_error_code（不含字典未收录的传输层错误单）
 *
 * 背景：forward_orders.error_code 是本次改造新增字段，早期失败单该字段为空，
 * 导致统计的错误码分布缺失。本命令从 forward_error 文本解析错误码并写回。
 *
 * 解析规则（按优先级）：
 *   1. 移动业务错误(E0005|S0202|400xx): ...  -> 提取括号内的业务错误码
 *   2. 移动业务错误, code: C4005              -> 提取 C4005
 *   3. cURL error 28 ...                      -> CURL_28
 *   4. B公司接口返回非成功状态码: 404          -> HTTP_404
 *   5. 以上均未命中且消息非空                  -> OTHER_BIZ
 *
 * 子码 sub_error_code：从 forward_error 里额外提取第一个 400xx（E0005 办理业务失败时附带）。
 *
 * 用法：
 *   php artisan forward:backfill-error-code            # 回填全部 error_code 为空且 forward_error 有值的失败单
 *   php artisan forward:backfill-error-code --dry-run  # 只统计将回填的数量，不写库
 *   php artisan forward:backfill-error-code --id=215   # 仅回填指定订单（便于调试）
 */
class BackfillForwardErrorCode extends Command
{
    protected $signature = 'forward:backfill-error-code
                            {--dry-run : 只统计不回写}
                            {--id= : 仅回填指定订单ID}';

    protected $description = '回填历史失败订单的 error_code / sub_error_code';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $id = $this->option('id') ? (int) $this->option('id') : null;

        $query = ForwardOrder::where('forward_status', 2)
            ->whereNotNull('forward_error')
            ->where('forward_error', '!=', '');

        // 幂等：已填过主码的不动；仅补主码为空但子码可提取的（若主码为空则不细化）
        $query->whereNull('error_code');

        if ($id !== null) {
            $query->where('id', $id);
        }

        $count = (clone $query)->count();
        $this->info($dryRun ? "【DRY RUN】将回填无 error_code 的失败单: {$count}" : "待回填失败单: {$count}");
        if ($count === 0) {
            $this->info('无需回填。');
            return self::SUCCESS;
        }

        $updated = 0;
        $details = [
            'E0005' => 0, 'E0003' => 0, 'E0001' => 0, 'E0006' => 0, 'E0002' => 0, 'E0007' => 0,
            'S0202' => 0, 'S0205' => 0, 'S0303' => 0, 'S0101' => 0, 'S0203' => 0, 'S0111' => 0,
            'CURL_28' => 0, 'HTTP_404' => 0, 'OTHER_BIZ' => 0, 'OTHER' => 0,
        ];

        $query->select(['id', 'forward_error'])->chunkById(500, function ($orders) use (&$updated, &$details, $dryRun) {
            foreach ($orders as $order) {
            $parsed = $this->parseErrorFromMessage((string) $order->forward_error);
            $code = $parsed['error_code'];

            if ($code === null) {
                continue;
            }
            $details[$code] = ($details[$code] ?? 0) + 1;

            if (! $dryRun) {
                // 注意：forward_orders 无 sub_error_code 列，子码在聚合时从 forward_error 实时提取
                DB::table('forward_orders')->where('id', $order->id)->update(['error_code' => $code]);
            }
            $updated++;
        }
        });

        $this->info($dryRun ? "【DRY RUN】将更新: {$updated}" : "已回填: {$updated}");
        if (! $dryRun) {
            arsort($details);
            foreach ($details as $k => $v) {
                if ($v > 0) {
                    $this->line("  {$k}: {$v}");
                }
            }
            Log::info('forward:backfill-error-code 完成', ['updated' => $updated, 'dry_run' => $dryRun]);
        }
        return self::SUCCESS;
    }

    /**
     * 从 forward_error 文本解析主错误码 + 子错误码
     *
     * @return array{error_code: ?string, sub_error_code: ?string}
     */
    protected function parseErrorFromMessage(string $msg): array
    {
        $errorCode = null;
        $subErrorCode = null;

        // 金额优先级1：移动业务错误(Xxx): 上下文
        if (preg_match('/移动业务错误\(([^):]+)\)/', $msg, $m)) {
            $errorCode = $m[1];
        } elseif (preg_match('/移动业务错误[,，]*\s*(?:code|Code)\s*[:：]\s*(\S+)/', $msg, $m)) {
            // 优先级2：移动业务错误, code: C4005 之类
            $errorCode = trim($m[1]);
        } elseif (preg_match('/cURL error ([0-9]+)/', $msg, $m)) {
            $errorCode = 'CURL_'.$m[1];
        } elseif (preg_match('/非成功状态码[:：]\s*([0-9]+)/', $msg, $m)) {
            $errorCode = 'HTTP_'.$m[1];
        } elseif (trim($msg) !== '') {
            $errorCode = 'OTHER_BIZ';
        }

        // 子码：提取第一个 400xx（E0005 办理业务失败附带的管控错误）
        if (preg_match('/400\d{2}/', $msg, $sm)) {
            $subErrorCode = $sm[0];
        }

        return ['error_code' => $errorCode, 'sub_error_code' => $subErrorCode];
    }
}