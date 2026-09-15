<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ThirdChannel\ThirdChannelRequest;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ForwardStopRegistry;
use App\Services\ThirdChannel\ThirdChannelService;
use Illuminate\Http\Request;

class ThirdChannelController extends Controller
{
    protected ThirdChannelService $service;

    public function __construct(ThirdChannelService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取渠道列表 (支持分页)
     */
    public function index(Request $request)
    {
        $data = ThirdChannels::with(['organization'])->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 10));

        return $this->success($data);
    }

    /**
     * 获取指定渠道详情及其产品配置
     */
    public function show($id)
    {
        $channel = ThirdChannels::findOrFail($id);
        $data = $this->service->getFormattedDetail($channel);

        return $this->success($data);
    }

    /**
     * 新增渠道
     */
    public function store(ThirdChannelRequest $request)
    {
        $data = ThirdChannels::create($request->validated());

        return $this->success($data, '渠道创建成功');
    }

    /**
     * 更新渠道
     */
    public function update(ThirdChannelRequest $request, ThirdChannels $channel)
    {
        $channel->update($request->validated());
        $this->service->clearCacheAfterUpdate($channel);

        return $this->success($channel, '更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy(ThirdChannels $channel)
    {
        return $this->error('生成之后不可删除');
    }

    /**
     * 获取特定渠道的审计日志
     */
    public function audits(ThirdChannels $channel)
    {
        $data = $channel->audits()->with('user')->orderBy('created_at', 'desc')->get();

        return $this->success($data);
    }

    /**
     * 停止条件类型清单（供前端渲染条件表单）
     * GET /api/v1/channels/stop-condition-types
     */
    public function stopConditionTypes()
    {
        return $this->success(ForwardStopRegistry::all());
    }

    /**
     * 同步渠道产品配置
     */
    public function syncProducts(Request $request, $id)
    {
        $data = $request->validate([
            'products' => 'present|array',
            'products.*.product_id' => 'required|integer',
            'products.*.status' => 'required|in:0,1,2',
            'products.*.remark' => 'nullable|string|max:100',
        ]);

        $this->service->syncProducts((int) $id, $data['products'] ?? []);

        return $this->success(null, '更新成功');
    }
}
