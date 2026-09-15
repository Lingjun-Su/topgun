<?php

namespace App\Services\ThirdChannel;

use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * 入站映射引擎：把外部方（A/C）原始 payload 翻译为 B 内部参考模型字段。
 *
 * 纯配置驱动：一切差异来自 third_channels.receive_mapping，引擎不感知公司名。
 * 映射值表达式支持：直接键名 / 嵌套路径(a.b) / 候选(a|b) / {from} / {default} / {value} / ignore / __raw__。
 *
 * 设计说明见：docs/需求与设计/渠道管理全配置化接入-后端设计.md §6
 */
class InboundMapper
{
    /** 透传开关键：未映射的外部字段统一收纳进返回值 __raw__ */
    public const RAW_KEY = '__raw__';

    /** 允许的转换器白名单，禁任意表达式求值 */
    protected const CONVERTERS = ['datetime', 'numeric', 'int', 'bool', 'enum', 'concat', 'strip'];

    /**
     * 执行一次入站映射。
     *
     * @param  array  $payload  外部原始数据
     * @param  array  $mapping  该入口分段的映射配置（如 receive_mapping.order）
     * @return array            翻译结果：['mapped' => [...], 'raw' => [...]]
     */
    public function mapForReceive(array $payload, ?array $mapping): array
    {
        $mapping = is_array($mapping) ? $mapping : [];

        $mapped = [];
        $rawEnabled = (bool) ($mapping[self::RAW_KEY] ?? false);
        unset($mapping[self::RAW_KEY]);

        $processedSourceKeys = [];

        foreach ($mapping as $internalKey => $expression) {
            // 内部键非法（含透传保留键退化）跳过
            if (! is_string($internalKey) || $internalKey === '' || $internalKey === self::RAW_KEY) {
                continue;
            }

            $spec = $expression;
            $isArraySpec = is_array($spec);

            // ignore：命中后不参与校验，仍尝试取值用于留痕
            if ($isArraySpec && ! empty($spec['ignore'])) {
                $value = $this->resolveValue($payload, $spec);
                if ($value !== null) {
                    $mapped[$internalKey] = $value;
                }
                continue;
            }

            $value = $this->resolveValue($payload, $spec);
            if ($value === null) {
                continue;
            }
            $mapped[$internalKey] = $value;

            // 记录已消费的来源路径，供 __raw__ 剔除
            foreach ($this->sourcePaths($spec) as $path) {
                if ($path !== null && $path !== '') {
                    $processedSourceKeys[] = $path;
                }
            }
        }

        $raw = [];
        if ($rawEnabled) {
            $processedSourceKeys = array_unique(array_filter($processedSourceKeys));
            $raw = $this->collectRaw($payload, $processedSourceKeys);
        }

        return ['mapped' => $mapped, 'raw' => $raw];
    }

    /**
     * 解析单个映射值表达式为实际值（经转换器）。
     * 解析顺序：候选(string a|b) → from → 直接(string 键名) → value(固定值)。
     */
    protected function resolveValue(array $payload, mixed $spec): mixed
    {
        // 数组规格：{from|value|default, convert, format, map, parts, strip...}
        if (is_array($spec)) {
            $from = $spec['from'] ?? null;
            $convert = $spec['convert'] ?? null;
            $format = $spec['format'] ?? null;

            $raw = $this->pickFromPath($payload, $from);

            // 转换后才校验是否存在（转换可能失败则报缺）
            $converted = $raw === null ? null : $this->applyConverter($raw, $convert, $spec, $payload);

            if ($converted === null) {
                return $spec['value'] ?? $spec['default'] ?? null;
            }

            return $converted;
        }

        // 字符串规格：直接键名 or 候选 a|b|c or 固定值回退
        if (is_string($spec)) {
            $spec = trim($spec);
            if ($spec === '') {
                return null;
            }
            // 含管道符视为候选集
            if (str_contains($spec, '|')) {
                foreach (explode('|', $spec) as $candidate) {
                    $v = $this->pickFromPath($payload, trim($candidate));
                    if ($v !== null) {
                        return $v;
                    }
                }

                return null;
            }

            return $this->pickFromPath($payload, $spec);
        }

        return null;
    }

    /**
     * 从 payload 按路径取值。路径支持 a.b 嵌套，\. 表示字面点分段。
     */
    protected function pickFromPath(array $payload, mixed $path): mixed
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (! is_string($path)) {
            return null;
        }
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        // 先尝试整键；否则按转义后的点分段
        if (array_key_exists($path, $payload)) {
            return $payload[$path];
        }

        $segments = $this->splitPath($path);
        if (count($segments) === 0) {
            return null;
        }

        return Arr::get($payload, implode('.', $segments));
    }

    /**
     * 路径分段：支持 \. 作为字面点（避免与嵌套分隔冲突）。
     */
    protected function splitPath(string $path): array
    {
        // 用占位替换 \. 为 \x01，再按 . 分，最后还原
        $placeHolder = "\x01";
        $escaped = str_replace('\.', $placeHolder, $path);
        $segments = explode('.', $escaped);

        foreach ($segments as &$seg) {
            $seg = str_replace($placeHolder, '.', $seg);
        }
        unset($seg);

        return $segments;
    }

    /**
     * 转换器白名单实现。
     */
    protected function applyConverter(mixed $value, ?string $convert, array $spec, array $payload): mixed
    {
        if ($convert === null || $convert === '') {
            return $value;
        }
        if (! in_array($convert, self::CONVERTERS, true)) {
            return $value; // 未知名转换器：原样返回，避免报错
        }

        switch ($convert) {
            case 'numeric':
                $num = preg_replace('/[^\d.\-]/', '', (string) $value);

                return is_numeric($num) ? (float) $num : $value;

            case 'int':
                return (int) preg_replace('/[^\d\-]/', '', (string) $value);

            case 'bool':
                return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);

            case 'datetime':
                return $this->convertDatetime($value, $spec['format'] ?? null);

            case 'enum':
                $map = $spec['map'] ?? [];
                if (is_array($map) && array_key_exists((string) $value, $map)) {
                    return $map[$value];
                }

                return $spec['default'] ?? $value;

            case 'concat':
                $parts = $spec['parts'] ?? [];
                $glue = $spec['glue'] ?? '';
                if (! is_array($parts)) {
                    return $value;
                }
                $collected = [];
                foreach ($parts as $p) {
                    if (is_array($p)) {
                        $collected[] = $this->resolveValue($payload, $p) ?? '';
                    } else {
                        $collected[] = (string) $this->pickFromPath($payload, $p) ?? '';
                    }
                }

                return implode($glue, $collected);

            case 'strip':
                $chars = is_array($spec['chars'] ?? null)
                    ? implode('', $spec['chars'])
                    : (string) ($spec['chars'] ?? '');

                return str_replace(str_split($chars), '', (string) $value);

            default:
                return $value;
        }
    }

    /**
     * 时间归一化：支持时间戳 / 常用格式 / 显式 format。
     */
    protected function convertDatetime(mixed $value, ?string $format): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // 纯数字视为时间戳（秒/毫秒）
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1_000_000_000) {
                $ts = (int) round($ts / 1000);
            }

            return Carbon::createFromTimestamp($ts)->format('Y-m-d H:i:s');
        }

        try {
            if ($format === 'timestamp') {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            }

            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null; // 解析失败记 NUL，交由校验/默认处理
        }
    }

    /**
     * 收集透传原始字段：剔除已被消费的来源路径。
     */
    protected function collectRaw(array $payload, array $processedSourceKeys): array
    {
        $raw = [];
        foreach ($payload as $k => $v) {
            // 系统注水字段（鉴权中间件注入）不外透
            if (str_starts_with((string) $k, '_')) {
                continue;
            }
            if (in_array($k, $processedSourceKeys, true) || in_array((string) $k, $processedSourceKeys, true)) {
                continue;
            }
            $raw[$k] = $v;
        }

        return $raw;
    }

    /**
     * 提取一个规格的所有来源路径（用于 __raw__ 剔除）。
     */
    protected function sourcePaths(mixed $spec): array
    {
        if (is_array($spec)) {
            $paths = [];
            if (isset($spec['from'])) {
                $paths[] = $spec['from'];
            }
            if (! empty($spec['parts']) && is_array($spec['parts'])) {
                foreach ($spec['parts'] as $p) {
                    if (is_string($p)) {
                        $paths[] = $p;
                    }
                }
            }

            return $paths;
        }
        if (is_string($spec) && ! str_contains($spec, '|')) {
            return [trim($spec)];
        }

        return [];
    }
}