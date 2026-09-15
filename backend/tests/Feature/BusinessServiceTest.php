<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessService;
use Tests\TestCase;

class BusinessServiceTest extends TestCase
{
    private BusinessService $service;

    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create();
        $this->actingAs($this->testUser);

        $this->service = new BusinessService;
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'org_id' => 1,
            'carrier_id' => 1,
            'code' => 'BIZ-TEST',
            'name' => '测试业务',
            'short_name' => '测试',
        ], $overrides);
    }

    public function test_create_business(): void
    {
        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-001',
            'name' => '测试业务单位',
        ]));

        $this->assertInstanceOf(Business::class, $business);
        $this->assertEquals('测试业务单位', $business->name);
        $this->assertEquals('BIZ-TEST-001', $business->code);
    }

    public function test_create_business_sets_created_by(): void
    {
        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-002',
            'name' => '创建者测试',
        ]));

        $this->assertNotNull($business->created_by);
        $this->assertEquals($this->testUser->id, $business->created_by);
    }

    public function test_update_business(): void
    {
        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-003',
            'name' => '原始名称',
        ]));

        $updated = $this->service->update($business, [
            'name' => '更新后的名称',
        ]);

        $this->assertEquals('更新后的名称', $updated->name);
        $this->assertNotNull($updated->updated_by);
    }

    public function test_delete_business_without_products(): void
    {
        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-004',
            'name' => '待删除业务',
        ]));

        $result = $this->service->delete($business);

        $this->assertTrue($result);
        $this->assertSoftDeleted('business', ['id' => $business->id]);
    }

    public function test_delete_business_sets_deleted_by(): void
    {
        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-005',
            'name' => '删除者测试',
        ]));

        $this->service->delete($business);

        $this->assertSoftDeleted('business', [
            'id' => $business->id,
            'deleted_by' => $this->testUser->id,
        ]);
    }

    public function test_delete_business_with_products_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('该单位下有关联产品，无法删除');

        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-006',
            'name' => '有产品关联的业务',
        ]));

        $product = new \App\Models\Products;
        $product->business_id = $business->id;
        $product->name = '测试产品';
        $product->sku_code = 'SKU-001';
        $product->unit = '个';
        $product->save();

        $this->service->delete($business);
    }

    public function test_business_uses_soft_deletes(): void
    {
        $business = $this->service->create($this->baseData([
            'code' => 'BIZ-TEST-007',
            'name' => '软删除测试',
        ]));

        $this->service->delete($business);

        $this->assertNull(Business::find($business->id));
        $this->assertNotNull(Business::withTrashed()->find($business->id));
    }
}
