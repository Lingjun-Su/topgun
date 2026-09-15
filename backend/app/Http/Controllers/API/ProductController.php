<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // // 建议使用 $request->filled() 判断，确保不是空字符串或 null
        $data = Products::when($request->filled('business_id'), function ($q) use ($request) {
            $q->where('business_id', $request->business_id);
        })
            ->when($request->filled('search'), function ($query) use ($request) {
                // 使用嵌套闭包，相当于 SQL 中的 (name LIKE ... OR sku_code LIKE ...)
                $query->where(function ($q) use ($request) {
                    $search = $request->search;
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku_code', 'like', "%{$search}%");
                });
            })
            ->with(['business.organization', 'business.carrier', 'productProvince.areas']) // 预加载关联，防止 N+1 问题
            ->paginate($request->integer('per_page', 15));

        return $this->success($data);

    }

    public function store(ProductRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['created_by'] = Auth::id();
            $data['org_id'] = Auth::user()->org_id; // 从当前登录用户获取组织ID

            $product = Products::create($data);

            return $this->success($product, '产品成功入库');
        });
    }

    public function update(ProductRequest $request, Products $product)
    {
        return DB::transaction(function () use ($request, $product) {
            $data = $request->validated();
            $data['updated_by'] = Auth::id();

            $product->update($data);

            return $this->success($product, '产品已更新');
        });
    }

    public function destroy(Products $product)
    {
        $product->deleted_by = Auth::id();
        $product->save();
        $product->delete();

        return $this->data($product, '产品已删除');
    }

    /**
     * 获取单个产品的审计变更历史
     */
    public function auditHistory($id)
    {
        $product = Products::withTrashed()->findOrFail($id);
        // 使用多态关联获取审计记录
        $audits = $product->audits()
            ->with('user:id,name') // 关联操作人
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($audits);
    }
}
