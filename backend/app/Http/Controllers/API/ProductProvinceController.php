<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductProvinceRequest;
use App\Models\ProductProvince;
use App\Models\Products; // 确保引入产品模型
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductProvinceController extends Controller
{

    // 查询列表 (包含搜索与分页)
    public function index(Request $request)
    {

       // 严谨写法：使用 with 加载模型中定义的 organization 方法名
        $data= ProductProvince::with(
            [
                'areas' => function($query) {
                    // 如果只需要组织表的某几个字段，可以在这里精简，提高性能
                    $query->select('id','name','short_name');
                }
            ]
        )
        ->paginate(100);
        return $this->success($data);
    }

    /**
     * @param ProductProvinceRequest $request
     * @param int $id 产品ID，从URL路径获取
     */
    public function update(ProductProvinceRequest $request, $id)
    {
        // 1. 严谨性检查：确保产品存在且未被逻辑删除
        $product = Products::findOrFail($id);

        // 2. 直接获取数组负载
        $newProvinceCodes = $request->all();

        try {
            return DB::transaction(function () use ($id, $newProvinceCodes) {

                // 获取当前数据库记录（包含已软删除的，用于恢复）
                $existingRecords = ProductProvince::withTrashed()
                    ->where('product_id', $id)
                    ->get();

                $existingCodes = $existingRecords->pluck('province_code')->toArray();
                $activeCodes = $existingRecords->whereNull('deleted_at')->pluck('province_code')->toArray();

                // --- 逻辑差异计算 ---

                // A. 恢复：在请求中 + 数据库中已删除
                $toRestore = array_intersect($newProvinceCodes, $existingCodes);
                ProductProvince::onlyTrashed()
                    ->where('product_id', $id)
                    ->whereIn('province_code', $toRestore)
                    ->restore();

                // B. 新增：在请求中 + 数据库中完全没有
                $toInsert = array_diff($newProvinceCodes, $existingCodes);
                if (!empty($toInsert)) {
                    $insertData = array_map(fn($code) => [
                        'product_id' => $id,
                        'province_code' => $code,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'created_by' => auth()->id() // 审计：记录创建人
                    ], $toInsert);
                    ProductProvince::insert($insertData);
                }

                // C. 逻辑删除：不在请求中 + 数据库中目前是活跃的
                $toDelete = array_diff($activeCodes, $newProvinceCodes);
                if (!empty($toDelete)) {
                    ProductProvince::where('product_id', $id)
                        ->whereIn('province_code', $toDelete)
                        ->update([
                            'deleted_at' => Carbon::now(),
                            'deleted_by' => auth()->id() // 审计：记录删除人
                        ]);
                }
                $change =[
                    'product_id' => $id,
                    'changes' => [
                        'added' => count($toInsert),
                        'restored' => count($toRestore),
                        'removed' => count($toDelete)
                    ]
                ];
                return $this->success($change,'销售省份配置更新成功');
            });
        } catch (\Exception $e) {
            return $this->error('系统错误'.$e->getMessage());
        }
    }
}
