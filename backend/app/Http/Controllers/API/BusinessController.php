<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Http\Requests\BusinessRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    // 查询列表 (包含搜索与分页)
    public function index(Request $request)
    {

       // 严谨写法：使用 with 加载模型中定义的 organization 方法名
        $data = Business::with(
            [
                'organization' => function($query) {
                    // 如果只需要组织表的某几个字段，可以在这里精简，提高性能
                    $query->select('id','name','short_name');
                },
                'carrier'=>function($query){
                    $query->select('id','name','short_name');
                }
            ]
        )
        ->orderBy('created_at', 'desc')
        ->paginate(200);
        return $this->success($data);
    }

    public function show(Request $request){
        $query = Business::query();
        $list = $query->orderBy('code', 'desc')->paginate($request->get('per_page', 100));

        return $this->success($list);
    }

    // 新增
    public function store(BusinessRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['created_by'] = Auth::id();

            $business = Business::create($data);
            return $this->success($business,'创建成功');
        });
    }

    // 更新
    public function update(BusinessRequest $request, Business $business)
    {
        return DB::transaction(function () use ($request, $business) {
            $data = $request->validated();
            $data['updated_by'] = Auth::id();

            $business->update($data);
            return $this->success($business,'更新成功');
        });
    }

    // 逻辑删除
    public function destroy(Business $business)
    {
        return DB::transaction(function () use ($business) {
            // 严谨性：如果该单位下有未删除的产品，禁止删除（视业务逻辑而定）
            if ($business->products()->exists()) {
                return response()->json(['message' => '该单位下有关联产品，无法删除'], 422);
            }

            $business->deleted_by = Auth::id();
            $business->save(); // 先保存删除人
            $business->delete(); // 执行逻辑删除 (deleted_at)
            return $this->success($business,'删除成功');
        });
    }
}
