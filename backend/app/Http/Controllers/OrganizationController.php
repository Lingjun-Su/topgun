<?php

namespace App\Http\Controllers;

// use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationService;

class OrganizationController extends Controller
{
    protected $service;

    public function __construct(OrganizationService $service)
    {
        $this->service = $service;
    }

    public function store(OrganizationRequest $request)
    {
        $organization = $this->service->createOrganization($request->validated());

        return $this->success($organization, '组织创建成功');
    }

    public function update(OrganizationRequest $request, $id)
    {
        $organization = $this->service->updateOrganization((int) $id, $request->validated());

        return $this->success($organization, '组织更新成功');
    }

    // 组织树的生成
    public function tree()
    {
        // 只查顶级组织，并递归预加载所有下级和银行账户
        $data = Organization::with(['childrenRecursive', 'bankAccounts'])
            ->where('parent_id', 0) // 或者根据你 migration 设定的 null
            ->get();

        return $this->success($data);
    }

    public function show(int $id)
    {
        $organization = Organization::find($id);

        return $this->success($organization);
    }

    public function destroy(int $id)
    {
        $organization = Organization::destroy($id);

        return $this->success($organization, '组织删除成功');
    }
}
