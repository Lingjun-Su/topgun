<?php

namespace App\Repositories\ProductOrder;

/**
 * 产品订单仓库接口
 * 定义订单数据访问的抽象契约
 */
interface ProductOrderRepositoryInterface
{
    /**
     * 获取环境标识
     */
    public function getEnvironment(): string;

    /**
     * 列表查询
     */
    public function index(array $filters = []);

    /**
     * 获取详情
     */
    public function show(int $id);

    /**
     * 创建订单
     */
    public function store(array $data);

    /**
     * 更新订单
     */
    public function update(int $id, array $data);

    /**
     * 删除订单
     */
    public function destroy(int $id);

    /**
     * 订单状态统计：按当前筛选条件统计 link_id/code 分布
     */
    public function statusStats(array $filters = []): array;
}
