<?php
// app/Http/Controllers/RegionController.php
/*
 * 控制器名称：RegionController
 * 功能描述：处理行政区域API请求，用于前端联动选择。
 *   - 方法：index - 根据parent_adcode和level获取子级列表（仅返回未软删的数据）。
 *   - 安全：假设使用API认证（如Sanctum），但当前不实现。
 *   - 审计：查询不需审计，修改/删除在模型中处理。
 *   - 删除验证：在模型中已处理，此控制器不涉及删除。
 * 命名规范：大驼峰，以Controller结尾，文件名为RegionController.php。
 * 路由示例：在routes/api.php中：Route::get('/regions', [RegionController::class, 'index']);
 */

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * 获取区域列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // 验证输入
        $request->validate([
            // 'parent_adcode' => 'nullable|string',
            'level' => 'nullable|integer|min:0|max:3',
        ]);

        // 查询：根据parent_adcode和level过滤，未软删
        $query = Region::query()
            ->whereNull('deleted_at'); // 逻辑删除过滤

        if ($request->has('parent_adcode')) {
            $query->where('parent_adcode', $request->parent_adcode['value']);
        } else {
            // 如果无parent_adcode，默认顶级（省）
            $query->whereNull('parent_adcode');
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        $regions = $query->select('adcode', 'name', 'level', 'parent_adcode')
            ->orderBy('name') // 按名称排序
            ->get();

        return $this->success($regions);
    }
}
