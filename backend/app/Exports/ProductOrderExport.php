<?php

namespace App\Exports;

use App\Repositories\ProductOrder\ProductOrderRepository;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductOrderExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected array $filters;

    protected string $environment;

    protected ?Collection $cachedCollection = null;

    /** 解析后的每行转发日志摘要（行索引 => 摘要数组） */
    protected array $parsedLogs = [];

    /** 最大日志条数，决定动态列数 */
    protected int $maxLogEntries = 0;

    public function __construct(array $filters, string $environment = 'prod')
    {
        $this->filters = $filters;
        $this->environment = $environment;
    }

    public function collection(): Collection
    {
        $this->ensureParsed();

        return $this->cachedCollection;
    }

    public function headings(): array
    {
        $this->ensureParsed();

        $headings = [
            '订单号',
            '会员手机',
            '验证码',
            'linkId',
            '总金额',
            '内部金额',
            '渠道ID',
            '业务名称',
            '产品名称',
            '会员昵称',
            '会员类型',
            '用户状态',
            '来源',
            '微信昵称',
            'UnionID',
            '省份代码',
            '城市代码',
            '订购时间',
            '订单状态',
            '券号',
            '投放平台',
            '提交IP',
            '单价',
            '数量',
            '接收时间',
            '推送时间',
            '推送状态',
            '失败原因',
        ];

        // 固定添加两个转发日志列
        $headings[] = '第一步';
        $headings[] = '第二步';

        return $headings;
    }

    public function map($row): array
    {
        $orderStatusMap = [0 => '未付款', 1 => '首订', 2 => '继订中', 3 => '退订'];
        $syncStatusMap = [0 => '待处理', 1 => '成功', 2 => '失败'];

        $data = [
            $row->order_no,
            $row->user_phone,
            $row->code,
            $row->link_id,
            $row->total_amount,
            $row->internal_amount,
            $row->pid,
            $row->business->short_name ?? '',
            $row->products->name ?? '',
            $row->user_nick,
            $row->user_type,
            $row->user_status === 0 ? '启用' : '停用',
            $row->user_source,
            $row->wx_nick,
            $row->wx_unionid,
            $row->province_code,
            $row->city_code,
            $row->order_time,
            $orderStatusMap[$row->order_status] ?? $row->order_status,
            $row->coupon_code,
            $row->platform,
            $row->ip,
            $row->price,
            $row->quantity,
            $row->created_at,
            $row->pushed_at,
            $syncStatusMap[$row->sync_status] ?? $row->sync_status,
            $this->resolveFailureReason($row),
        ];

        // 填充转发日志摘要（使用 collection 中缓存的索引查找）
        $index = $this->cachedCollection ? $this->cachedCollection->search(function ($item) use ($row) {
            return $item === $row;
        }) : false;

        // 按位置填充转发日志摘要（固定取前两条）
        $summaries = ($index !== false && isset($this->parsedLogs[$index]))
            ? $this->parsedLogs[$index]
            : $this->parseForwardLog($row->forward_log ?? '');

        $data[] = $summaries[0] ?? '';
        $data[] = $summaries[1] ?? '';

        return $data;
    }

    /**
     * 确保已拉取并解析数据，collection() 和 headings() 任一先调用都生效
     */
    protected function ensureParsed(): void
    {
        if ($this->cachedCollection !== null) {
            return;
        }

        $repository = new ProductOrderRepository($this->environment);

        $this->cachedCollection = $repository->getAll($this->filters);

        foreach ($this->cachedCollection as $index => $row) {
            $summaries = $this->parseForwardLog($row->forward_log ?? '');
            $this->parsedLogs[$index] = $summaries;
            $this->maxLogEntries = max($this->maxLogEntries, count($summaries));
        }
    }

    /**
     * 解析失败原因：优先取 sync_error，为空时从 forward_log 中最近一条“失败”条目提取原因
     */
    protected function resolveFailureReason($row): string
    {
        $reason = isset($row->sync_error) ? trim((string) $row->sync_error) : '';

        if ($reason !== '') {
            return $reason;
        }

        $log = $row->forward_log ?? '';
        if (empty(trim($log))) {
            return '';
        }

        $lastReason = '';
        // 注意：这里必须用“原始日志条目”而非 parseForwardLog()（后者已把条目标签化成摘要，会丢失移动反馈 JSON）
        foreach ($this->splitRawLogEntries($log) as $entry) {
            $firstLine = explode("\n", $entry)[0];
            $content = preg_replace('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] /', '', $firstLine);

            $mobileErr = $this->extractMobileErrorCode($entry);

            // 依据“ - 成功/失败”状态词判定，避免括号/移动反馈 JSON 里的“失败”字样误触发
            $sep = strpos($content, ' - ');
            if ($sep !== false && preg_match('/^(成功|失败)/', substr($content, $sep + 3), $st)) {
                if ($st[1] === '失败') {
                    if (preg_match('/失败\s*\(原因[:：]\s*(.*)$/', $content, $m)) {
                        $lastReason = trim($m[1]);
                    } elseif ($mobileErr !== '') {
                        $lastReason = $mobileErr;
                    } else {
                        $lastReason = $this->extractSummary($entry);
                    }
                    continue;
                }
                // 状态为成功：若移动反馈含业务错误，仍按失败原因导出
                if ($mobileErr !== '') {
                    $lastReason = $mobileErr;
                }
                continue;
            }

            // 非标准“操作 - 成功/失败”格式，仅尝试移动反馈错误
            if ($mobileErr !== '') {
                $lastReason = $mobileErr;
            }
        }

        return $lastReason;
    }

    /**
     * 按时间戳行切分原始日志条目（保留每个条目的多行原文，不做摘要化）
     */
    protected function splitRawLogEntries(string $log): array
    {
        if (empty(trim($log))) {
            return [];
        }

        $lines = explode("\n", $log);
        $entries = [];
        $current = '';

        foreach ($lines as $line) {
            if (preg_match('/^\[/', $line)) {
                if ($current !== '') {
                    $entries[] = $current;
                }
                $current = $line;
            } else {
                $current .= "\n" . $line;
            }
        }

        if ($current !== '') {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * 从“移动/B公司”反馈 JSON 中提取业务错误码，如平台判定成功但实际办理失败
     * 例: {"state":"success","data":{"code":"E0005","message":"办理业务失败。提示：'40008'"}} => 返回 40008
     * 无错误时返回空串
     */
    protected function extractMobileErrorCode(string $entry): string
    {
        if (! preg_match('/(?:移动反馈|B公司反馈|A公司反馈|渠道反馈)\s*[:：]\s*(\{.*\})\s*$/s', $entry, $m)) {
            return '';
        }

        $data = json_decode($m[1], true);
        if (! is_array($data)) {
            return '';
        }

        $code = $data['data']['code'] ?? $data['code'] ?? null;
        $message = $data['data']['message'] ?? $data['message'] ?? '';

        $isErrorCode = $code !== null && $code !== '' && (string) $code !== '00000' && (string) $code !== '0000';
        $hasFailureMsg = is_string($message) && mb_strpos($message, '失败') !== false;

        if (! $isErrorCode && ! $hasFailureMsg) {
            return '';
        }

        // 优先返回 message 中的 5 位业务码（如 '40008'），没有时退回响应里的 code
        if (is_string($message) && preg_match("/'(\d{5})'/", $message, $mm)) {
            return $mm[1];
        }

        return (string) $code;
    }

    /**
     * 解析转发日志文本，返回每条日志的摘要数组
     */
    protected function parseForwardLog(string $log): array
    {
        if (empty(trim($log))) {
            return [];
        }

        $lines = explode("\n", $log);
        $entries = [];
        $currentEntry = '';

        foreach ($lines as $line) {
            if (preg_match('/^\[/', $line)) {
                if (!empty($currentEntry)) {
                    $entries[] = $this->extractSummary($currentEntry);
                }
                $currentEntry = $line;
            } else {
                $currentEntry .= "\n" . $line;
            }
        }

        if (!empty($currentEntry)) {
            $entries[] = $this->extractSummary($currentEntry);
        }

        return $entries;
    }

    /**
     * 从单条日志中提取摘要
     *
     * 示例输入:
     *   [2026-08-16 15:58:34] 发送验证码 - 成功 (linkId: 2088898130872078337)
     * 示例输出:
     *   发送验证码-成功
     *
     * 示例输入:
     *   [2026-08-16 15:59:13] 提交订单 - 失败 (原因: 移动业务错误(E0005): 办理业务失败。提示：'40008')
     * 示例输出:
     *   提交订单-失败E0005
     *
     * 示例输入:
     *   [2026-08-16 14:06:22] 提交订单 - 失败 (原因: 移动业务错误(E0005): 短信验证码不正确，请重新获取！)
     * 示例输出:
     *   提交订单-失败E0005
     */
    protected function extractSummary(string $entry): string
    {
        $firstLine = explode("\n", $entry)[0];

        // 去除时间戳前缀 [YYYY-MM-DD HH:MM:SS]
        $content = preg_replace('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] /', '', $firstLine);

        // 格式: "操作 - 结果 (详情)"
        if (preg_match('/^(.+?) - (.+)$/', $content, $matches)) {
            $action = trim($matches[1]);

            // 优先识别“移动反馈”里的业务错误码（如平台判定成功但实际办理失败），视为失败并附码
            $mobileCode = $this->extractMobileErrorCode($entry);
            if ($mobileCode !== '') {
                return "{$action}-失败({$mobileCode})";
            }

            $resultPart = trim($matches[2]);

            if (str_contains($resultPart, '成功')) {
                return "{$action}-成功";
            } elseif (str_contains($resultPart, '失败')) {
                // 优先提取 '4000x' 格式的 5 位数字错误码
                if (preg_match("/'(\d{5})'/", $resultPart, $codeMatches)) {
                    return "{$action}-失败{$codeMatches[1]}";
                }
                // 其次提取 "移动业务错误(E000X)" 中的字母数字错误码
                if (preg_match('/移动业务错误\(([\w]+)\)/', $resultPart, $codeMatches)) {
                    return "{$action}-失败{$codeMatches[1]}";
                }
                return "{$action}-失败";
            }

            // 兜底: 取括号前的内容
            $cleanResult = preg_replace('/\(.*\)/', '', $resultPart);
            $cleanResult = trim($cleanResult);
            return $cleanResult ? "{$action}-{$cleanResult}" : $action;
        }

        return $content;
    }
}
