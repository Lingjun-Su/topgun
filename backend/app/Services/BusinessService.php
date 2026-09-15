<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 业务管理服务
 * 封装业务模块的业务逻辑
 */
class BusinessService
{
    /**
     * 创建业务
     */
    public function create(array $data): Business
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::id();

            return Business::create($data);
        });
    }

    /**
     * 更新业务
     */
    public function update(Business $business, array $data): Business
    {
        return DB::transaction(function () use ($business, $data) {
            $data['updated_by'] = Auth::id();
            $business->update($data);

            return $business->fresh();
        });
    }

    /**
     * 删除业务（含关联检查）
     *
     * @throws \Exception
     */
    public function delete(Business $business): bool
    {
        return DB::transaction(function () use ($business) {
            // 检查是否有关联产品
            if ($business->products()->exists()) {
                throw new \Exception('该单位下有关联产品，无法删除');
            }

            $business->deleted_by = Auth::id();
            $business->save();
            $business->delete();

            return true;
        });
    }
}
