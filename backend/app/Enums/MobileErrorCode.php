<?php

namespace App\Enums;

/**
 * 上游 A（律动）业务错误码枚举
 *
 * 每个 case 的枚举值即上游返回的 error_code 原文（如 C4005）；
 * 用于把统计中的 error_code 映射为可读中文含义，并区分所属系列。
 *
 * 系列约定：
 *   - E 系列    业务错误
 *   - 400 系列  管控错误
 *   - S 系列    用户状态
 */
enum MobileErrorCode: string
{
    // ---------- E 系列 · 业务错误 ----------
    case SUCCESS = '00000'; // 成功
    case ORDER_STATUS_INVALID = 'E0001'; // 订单状态异常
    case CAPTCHA_INVALID = 'E0003'; // 验证码有误
    case SMS_CAPTCHA_WRONG = 'E0005'; // 短信验证码不正确，请重新获取！ / 办理业务失败（通常会附带 400xx 子错误码）

    // ---------- 400 系列 · 管控错误 ----------
    case PLAN_COUNT_LIMIT = '40001'; // 单一管控应用订购套餐个数限制
    case APP_COUNT_LIMIT = '40002'; // 多管控应用限制订购涉及的应用个数
    case NUMBER_BLOCKLISTED = '40003'; // 处于号码管控库（黑名单或者退订期用户）
    case DEVELOP_OVER_LIMIT = '40004'; // 业务处于超限管控中（日/月发展量超阈值）
    case OFF_TIME_LIMIT = '40005'; // 非订购时间段管控（22点至次日7点）
    case UNSUB_RATE_OVER_LIMIT = '40006'; // 业务处于24小时退订率超限管控中（40%）
    case SMS_NOT_SENT = '40007'; // 用户不下发验证码了
    case BIG_DATA_BLOCKLISTED = '40008'; // 移动系统大数据黑名单校验

    // ---------- S 系列 · 用户状态 ----------
    case INVALID_NUMBER = 'S0101'; // 号码有误
    case NOT_MOBILE_USER = 'S0202'; // 非移动用户
    case AGE_NOT_ALLOWED = 'S0205'; // 年龄不符合要求
    case TOO_FREQUENT = 'S0303'; // 请求频繁

    /**
     * 是否属于业务成功
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * 错误码所属系列（中文）
     */
    public function group(): string
    {
        return match ($this) {
            self::SUCCESS,
            self::ORDER_STATUS_INVALID,
            self::CAPTCHA_INVALID,
            self::SMS_CAPTCHA_WRONG => 'E系列·业务错误',
            self::PLAN_COUNT_LIMIT,
            self::APP_COUNT_LIMIT,
            self::NUMBER_BLOCKLISTED,
            self::DEVELOP_OVER_LIMIT,
            self::OFF_TIME_LIMIT,
            self::UNSUB_RATE_OVER_LIMIT,
            self::SMS_NOT_SENT,
            self::BIG_DATA_BLOCKLISTED => '400系列·管控错误',
            self::INVALID_NUMBER,
            self::NOT_MOBILE_USER,
            self::AGE_NOT_ALLOWED,
            self::TOO_FREQUENT => 'S系列·用户状态',
        };
    }

    /**
     * 获取业务错误码的可读中文含义
     */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => '成功',
            self::ORDER_STATUS_INVALID => '订单状态异常',
            self::CAPTCHA_INVALID => '验证码有误',
            self::SMS_CAPTCHA_WRONG => '短信验证码不正确，请重新获取！ / 办理业务失败（通常会附带 400xx 子错误码）',
            self::PLAN_COUNT_LIMIT => '单一管控应用订购套餐个数限制',
            self::APP_COUNT_LIMIT => '多管控应用限制订购涉及的应用个数',
            self::NUMBER_BLOCKLISTED => '处于号码管控库（黑名单或者退订期用户）',
            self::DEVELOP_OVER_LIMIT => '业务处于超限管控中（日/月发展量超阈值）',
            self::OFF_TIME_LIMIT => '非订购时间段管控（22点至次日7点）',
            self::UNSUB_RATE_OVER_LIMIT => '业务处于24小时退订率超限管控中（40%）',
            self::SMS_NOT_SENT => '用户不下发验证码了',
            self::BIG_DATA_BLOCKLISTED => '移动系统大数据黑名单校验',
            self::INVALID_NUMBER => '号码有误',
            self::NOT_MOBILE_USER => '非移动用户',
            self::AGE_NOT_ALLOWED => '年龄不符合要求',
            self::TOO_FREQUENT => '请求频繁',
        };
    }

    /**
     * 根据上游错误码原文解析枚举；未知码返回 null（由调用方决定兜底文案）
     */
    public static function fromCode(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }
        foreach (self::cases() as $case) {
            if ($case->value === $code) {
                return $case;
            }
        }
        return null;
    }
}