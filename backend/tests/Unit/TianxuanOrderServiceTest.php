<?php

namespace Tests\Unit;

use App\Services\TianxuanOrderService;
use Tests\TestCase;

class TianxuanOrderServiceTest extends TestCase
{
    private string $testKey = 'test_secret_key_2024';

    public function test_generate_sign_consistent_output(): void
    {
        $params = [
            'app_id' => 'APP001',
            'order_no' => 'ORD20240001',
            'amount' => '100.00',
        ];

        $sign1 = TianxuanOrderService::generateSign($params, $this->testKey);
        $sign2 = TianxuanOrderService::generateSign($params, $this->testKey);

        $this->assertSame($sign1, $sign2);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $sign1);
    }

    public function test_generate_sign_different_params_different_sign(): void
    {
        $params1 = ['order_no' => 'ORD001', 'amount' => '100'];
        $params2 = ['order_no' => 'ORD002', 'amount' => '100'];

        $sign1 = TianxuanOrderService::generateSign($params1, $this->testKey);
        $sign2 = TianxuanOrderService::generateSign($params2, $this->testKey);

        $this->assertNotEquals($sign1, $sign2);
    }

    public function test_generate_sign_different_keys_different_sign(): void
    {
        $params = ['order_no' => 'ORD001', 'amount' => '100'];
        $key1 = 'key_one';
        $key2 = 'key_two';

        $sign1 = TianxuanOrderService::generateSign($params, $key1);
        $sign2 = TianxuanOrderService::generateSign($params, $key2);

        $this->assertNotEquals($sign1, $sign2);
    }

    public function test_verify_sign_with_valid_data(): void
    {
        $key = 'verify_test_key';
        $params = ['order_no' => 'ORD001', 'amount' => '200', 'app_id' => 'APP01'];
        $params['sign'] = TianxuanOrderService::generateSign($params, $key);

        $this->assertTrue(TianxuanOrderService::verifySign($params, $key));
    }

    public function test_verify_sign_with_invalid_sign(): void
    {
        $key = 'verify_test_key';
        $params = ['order_no' => 'ORD001', 'amount' => '200', 'sign' => 'INVALID_SIGN_VALUE'];

        $this->assertFalse(TianxuanOrderService::verifySign($params, $key));
    }

    public function test_verify_sign_returns_false_when_sign_not_set(): void
    {
        $params = ['order_no' => 'ORD001', 'amount' => '200'];

        $this->assertFalse(TianxuanOrderService::verifySign($params, 'any_key'));
    }

    public function test_verify_sign_with_empty_params(): void
    {
        $key = 'empty_test_key';
        $params = ['sign' => TianxuanOrderService::generateSign([], $key)];

        $this->assertTrue(TianxuanOrderService::verifySign($params, $key));
    }

    public function test_verify_sign_with_tampered_param(): void
    {
        $key = 'tamper_test_key';
        $originalParams = ['order_no' => 'ORD001', 'amount' => '100'];
        $originalParams['sign'] = TianxuanOrderService::generateSign($originalParams, $key);

        $tamperedParams = $originalParams;
        $tamperedParams['amount'] = '999';

        $this->assertFalse(TianxuanOrderService::verifySign($tamperedParams, $key));
    }

    public function test_verify_sign_with_tampered_key(): void
    {
        $key = 'original_key';
        $params = ['data' => 'test'];
        $params['sign'] = TianxuanOrderService::generateSign($params, $key);

        $this->assertFalse(TianxuanOrderService::verifySign($params, 'different_key'));
    }

    public function test_generate_sign_includes_sign_param_if_present(): void
    {
        $key = 'sign_param_test';
        $paramsWithSign = ['a' => '1', 'b' => '2', 'sign' => 'EXTRA_VALUE'];
        $paramsWithoutSign = ['a' => '1', 'b' => '2'];

        $sign1 = TianxuanOrderService::generateSign($paramsWithSign, $key);
        $sign2 = TianxuanOrderService::generateSign($paramsWithoutSign, $key);

        $this->assertNotSame($sign1, $sign2);
    }

    public function test_generate_sign_empty_params(): void
    {
        $sign = TianxuanOrderService::generateSign([], $this->testKey);

        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $sign);
    }

    public function test_generate_sign_with_special_characters(): void
    {
        $params = ['name' => '张三', 'code' => 'A&B=C'];

        $sign = TianxuanOrderService::generateSign($params, $this->testKey);

        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $sign);
    }
}
