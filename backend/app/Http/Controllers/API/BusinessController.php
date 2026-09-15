<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequest;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    protected BusinessService $service;

    public function __construct(BusinessService $service)
    {
        $this->service = $service;
    }

    /**
     * 查询列表 (包含搜索与分页)
     */
    public function index(Request $request)
    {
        $data = Business::with([
            'organization' => function ($query) {
                $query->select('id', 'name', 'short_name');
            },
            'carrier' => function ($query) {
                $query->select('id', 'name', 'short_name');
            },
        ])
            ->orderBy('created_at', 'desc')
            ->paginate(200);

        return $this->success($data);
    }

    public function show(Request $request)
    {
        $query = Business::query();
        $list = $query->orderBy('code', 'desc')->paginate($request->get('per_page', 100));

        return $this->success($list);
    }

    /**
     * 新增
     */
    public function store(BusinessRequest $request)
    {
        $business = $this->service->create($request->validated());

        return $this->success($business, '创建成功');
    }

    /**
     * 更新
     */
    public function update(BusinessRequest $request, Business $business)
    {
        $business = $this->service->update($business, $request->validated());

        return $this->success($business, '更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy(Business $business)
    {
        try {
            $this->service->delete($business);

            return $this->success($business, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
