<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\OrganizationService;
use Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    private OrganizationService $service;

    private static int $creditCodeCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrganizationService;
    }

    private function baseData(array $overrides = []): array
    {
        self::$creditCodeCounter++;
        $code = str_pad((string) self::$creditCodeCounter, 18, '0', STR_PAD_LEFT);

        return array_merge([
            'name' => '测试组织',
            'short_name' => '测试',
            'social_credit_code' => $code,
            'status' => 1,
            'type' => 1,
        ], $overrides);
    }

    public function test_create_root_organization(): void
    {
        $org = $this->service->createOrganization($this->baseData(['name' => '测试根组织', 'short_name' => '根组织']));

        $this->assertInstanceOf(Organization::class, $org);
        $this->assertEquals(0, $org->parent_id);
        $this->assertEquals(1, $org->level);
        $this->assertEquals('测试根组织', $org->name);
    }

    public function test_create_child_organization_with_level_calculation(): void
    {
        $parent = $this->service->createOrganization($this->baseData(['name' => '父组织']));

        $child = $this->service->createOrganization($this->baseData([
            'name' => '子组织',
            'parent_id' => $parent->id,
            'type' => 2,
        ]));

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals(2, $child->level);
    }

    public function test_create_organization_with_bank_accounts(): void
    {
        $org = $this->service->createOrganization($this->baseData([
            'name' => '带银行账号的组织',
            'bankAccounts' => [
                [
                    'bank_name' => '工商银行',
                    'bank_account' => '6222021234567890',
                    'account_name' => '测试账户',
                    'is_default' => 1,
                    'status' => 1,
                ],
                [
                    'bank_name' => '建设银行',
                    'bank_account' => '6227009876543210',
                    'account_name' => '备用账户',
                    'is_default' => 0,
                    'status' => 1,
                ],
            ],
        ]));

        $this->assertCount(2, $org->bankAccounts);
        $this->assertEquals('工商银行', $org->bankAccounts->first()->bank_name);
    }

    public function test_create_organization_with_empty_bank_accounts(): void
    {
        $org = $this->service->createOrganization($this->baseData([
            'name' => '空银行账号组织',
            'bankAccounts' => [],
        ]));

        $this->assertCount(0, $org->bankAccounts);
    }

    public function test_create_organization_without_bank_accounts_key(): void
    {
        $org = $this->service->createOrganization($this->baseData(['name' => '无银行键的组织']));

        $this->assertInstanceOf(Organization::class, $org);
        $this->assertCount(0, $org->bankAccounts);
    }

    public function test_create_organization_filters_invalid_bank_accounts(): void
    {
        $org = $this->service->createOrganization($this->baseData([
            'name' => '过滤银行账号',
            'bankAccounts' => [
                ['bank_name' => '', 'bank_account' => '', 'account_name' => ''],
                [
                    'bank_name' => '有效银行',
                    'bank_account' => '6222020000000000',
                    'account_name' => '有效账户',
                ],
            ],
        ]));

        $this->assertCount(1, $org->bankAccounts);
        $this->assertEquals('有效银行', $org->bankAccounts->first()->bank_name);
    }

    public function test_update_organization_basic_info(): void
    {
        $org = $this->service->createOrganization($this->baseData(['name' => '原始名称']));

        $updated = $this->service->updateOrganization($org->id, [
            'name' => '更新后的名称',
            'short_name' => '新名',
        ]);

        $this->assertEquals('更新后的名称', $updated->name);
    }

    public function test_update_organization_add_bank_accounts(): void
    {
        $org = $this->service->createOrganization($this->baseData(['name' => '原始组织']));
        $this->assertCount(0, $org->bankAccounts);

        $updated = $this->service->updateOrganization($org->id, [
            'name' => '原始组织',
            'short_name' => '原始',
            'bankAccounts' => [
                [
                    'bank_name' => '招商银行',
                    'bank_account' => '6225880000000000',
                    'account_name' => '招商账户',
                ],
            ],
        ]);

        $this->assertCount(1, $updated->bankAccounts);
    }

    public function test_update_organization_remove_bank_accounts(): void
    {
        $org = $this->service->createOrganization($this->baseData([
            'name' => '含银行账号的组织',
            'bankAccounts' => [
                [
                    'bank_name' => '农业银行',
                    'bank_account' => '6228480000000000',
                    'account_name' => '农行账户',
                ],
            ],
        ]));
        $this->assertCount(1, $org->bankAccounts);

        $updated = $this->service->updateOrganization($org->id, [
            'name' => '含银行账号的组织',
            'short_name' => '含银行',
            'bankAccounts' => [],
        ]);

        $this->assertCount(0, $updated->bankAccounts);
    }

    public function test_update_organization_replaces_bank_accounts(): void
    {
        $org = $this->service->createOrganization($this->baseData([
            'name' => '替换测试',
            'bankAccounts' => [
                [
                    'bank_name' => '旧银行',
                    'bank_account' => '0000000000000000',
                    'account_name' => '旧账户',
                ],
            ],
        ]));

        $updated = $this->service->updateOrganization($org->id, [
            'name' => '替换测试',
            'short_name' => '替换',
            'bankAccounts' => [
                [
                    'bank_name' => '新银行',
                    'bank_account' => '1111111111111111',
                    'account_name' => '新账户',
                ],
            ],
        ]);

        $this->assertCount(1, $updated->bankAccounts);
        $this->assertEquals('新银行', $updated->bankAccounts->first()->bank_name);
    }
}
