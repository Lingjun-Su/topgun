<?php

namespace App\Http\Controllers;

use App\Http\Requests\DictionaryRequest;
use App\Models\Dictionary;
use Illuminate\Http\Request;

class DictionaryController extends Controller
{
    /**
     * 列表：支持分页和简单搜索
     */
    public function index(Request $request)
    {
        $data = Dictionary::query()
            ->when($request->type, fn ($q) => $q->where('type', 'like', "%{$request->type}%"))
            ->when($request->label, fn ($q) => $q->where('label', 'like', "%{$request->label}%"))
            ->orderBy($request->get('sort_by', 'sort'), $request->get('order', 'asc'))
            ->paginate($request->get('per_page', 15));

        return $this->success($data);
    }

    /**
     * 新增
     */
    public function store(DictionaryRequest $request)
    {
        $data = Dictionary::create($request->validated());

        return $this->success($data);
    }

    /**
     * 更新
     */
    public function update(DictionaryRequest $request, Dictionary $dictionary)
    {
        $dictionary->update($request->validated());

        return $this->success($dictionary);
    }

    /**
     * 删除
     */
    public function destroy(Dictionary $dictionary)
    {
        $dictionary->delete();

        return $this->success($dictionary, '删除成功');
    }
}
