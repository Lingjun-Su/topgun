<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * 创建组织架构
     *
     * * @param array $data 包含组织信息及可选的 bankAccounts 数组
     * @return Organization
     */
    public function createOrganization(array $data)
    {
        // 使用数据库事务确保数据一致性 (SQL Server 2019)
        return DB::transaction(function () use ($data) {
            // 1. 处理层级逻辑：计算 level
            if (! empty($data['parent_id'])) {
                $parent = Organization::findOrFail($data['parent_id']);
                $data['level'] = $parent->level + 1;
            } else {
                // 规范化：根节点 parent_id 设为 0，level 设为 1
                $data['parent_id'] = 0;
                $data['level'] = 1;
            }

            // 2. 创建组织主体
            // 注意：$data 中不属于 organizations 表的字段会在 create 时被 Eloquent 自动忽略（基于 $fillable）
            $organization = Organization::create($data);

            // 3. 处理银行账号 (允许为空)
            // 显式检查：数组存在且不为空
            if (isset($data['bankAccounts']) && is_array($data['bankAccounts']) && count($data['bankAccounts']) > 0) {

                // 过滤空数据：确保子项不是空的（严谨性检查）
                $validAccounts = array_filter($data['bankAccounts'], function ($account) {
                    // 根据你的审计需求，至少要有银行名或账号才视为有效记录
                    return ! empty($account['bank_name']) || ! empty($account['account_no']);
                });

                if (! empty($validAccounts)) {
                    $organization->bankAccounts()->createMany($validAccounts);
                }
            }

            // 4. 统一加载关联关系并返回
            // 无论有没有银行账号，统一 load 可以保持返回结构的一致性
            return $organization->load('bankAccounts');
        });
    }

    public function updateOrganization(int $id, array $validatedData)
    {
        return DB::transaction(function () use ($id, $validatedData) {
            $organization = Organization::findOrFail($id);

            // 1. 提取银行账户数据，并从主表数据中移除它
            $bankAccountsData = $validatedData['bankAccounts'] ?? null;
            unset($validatedData['bankAccounts']); // 关键步骤：防止它进入 organizations 的 update SQL

            // 2. 处理层级计算逻辑：根据父组织的 level + 1
            if (isset($validatedData['parent_id'])) {
                $parent = Organization::find($validatedData['parent_id']);
                $validatedData['level'] = $parent ? $parent->level + 1 : 0;
            }

            // 3. 更新组织主表（此时 $validatedData 只有主表字段）
            $organization->update($validatedData);

            // 4. 处理关联的银行账户同步
            if ($bankAccountsData !== null) {

                $incomingIds = collect($bankAccountsData)->pluck('id')->filter()->toArray();

                // 删除已不存在的账户
                $organization->bankAccounts()->whereNotIn('id', $incomingIds)->delete();

                // 更新或创建
                foreach ($bankAccountsData as $bankData) {
                    $organization->bankAccounts()->updateOrCreate(
                        ['id' => $bankData['id'] ?? null],
                        $bankData
                    );
                }
            }

            return $organization->load('bankAccounts');
        });
    }
}
