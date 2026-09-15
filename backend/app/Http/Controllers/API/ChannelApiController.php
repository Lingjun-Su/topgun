<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelApi;
use App\Http\Requests\ChannelApiRequest;
use Illuminate\Http\Request;

class ChannelApiController extends Controller
{
    /**
     * 获取渠道列表 (支持分页)
     */
    public function index(Request $request)
    {
        $data = ChannelApi::orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $data
        ]);
    }

    /**
     * 新增渠道
     */
    public function store(ChannelApiRequest $request)
    {

        $channel = ChannelApi::create($request->validated());
        return response()->json([
            'code' => 0,
            'msg'  => '渠道创建成功',
            'data' => $channel
        ]);
    }

    /**
     * 更新渠道
     */
    public function update(ChannelApiRequest $request, ChannelApi $channel)
    {
        $channel->update($request->validated());

        return response()->json([
            'code' => 0,
            'msg'  => '更新成功',
            'data' => $channel
        ]);
    }

    /**
     * 逻辑删除
     */
    public function destroy(ChannelApi $channel)
    {
        return response()->json([
            'code' => 405,
            'msg'  => '生成之后不可以删除',
        ]);
        // 逻辑删除：数据库 deleted_at 会被填充，数据不消失
        // 同时 Audit 会记录一条 event 为 'deleted' 的数据
        $channel->delete();

        return response()->json([
            'code' => 0,
            'msg'  => '逻辑删除成功',
            'data' => []
        ]);
    }

    /**
     * 获取特定渠道的审计日志 (供前端 showAudit 调用)
     */
    public function audits(ChannelApi $channel)
    {
        // 获取该记录的所有历史变更
        $audits = $channel->audits()->with('user')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $audits
        ]);
    }
}
