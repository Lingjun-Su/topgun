<?php
namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

abstract class BaseResourceController extends Controller
{
    // 子类必须定义模型类名
    protected string $modelClass;

    // 子类可选定义验证器类名
    protected string $storeRequest = '';
    protected string $updateRequest = '';

    /**
     * 列表查询
     */
    public function index(Request $request)
    {
        $query = $this->modelClass::query();
        // 预留一个钩子用于子类自定义查询条件（如搜索、筛选）
        $this->applyFilters($query, $request);

        return response()->json($query->latest()->paginate($request->get('per_page', 15)));
    }

    /**
     * 详情查询
     */
    public function show($id)
    {
        return response()->json($this->modelClass::findOrFail($id));
    }

    /**
     * 存储数据
     */
    public function store(Request $request)
    {
        $data = $this->storeRequest ? app($this->storeRequest)->validated() : $request->all();

        return DB::transaction(function () use ($data) {
            $item = $this->modelClass::create($data);
            $this->afterStore($item); // 钩子
            return response()->json($item, 201);
        });
    }

    /**
     * 更新数据
     */
    public function update(Request $request, $id)
    {
        $item = $this->modelClass::findOrFail($id);
        $data = $this->updateRequest ? app($this->updateRequest)->validated() : $request->all();

        $item->update($data);
        return response()->json($item);
    }

    /**
     * 删除数据 (包含 Observer 异常捕获)
     */
    public function destroy($id)
    {
        try {
            $item = $this->modelClass::findOrFail($id);

            // 执行删除，会自动触发 Observer 中的 deleting 事件
            $item->delete();

            return response()->json(['message' => '删除成功']);
        } catch (Exception $e) {
            // 这里捕获 Observer 抛出的“有关联数据无法删除”的异常
            return response()->json([
                'message' => '操作失败',
                'errors'  => $e->getMessage()
            ], 422); // 422 状态码前端最容易处理
        }
    }

    // --- 钩子函数，子类如有需要可重写 ---
    protected function applyFilters($query, $request) {}
    protected function afterStore($item) {}
}
