<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ThirdChannel\ThirdChannelRequest;
use Illuminate\Http\Request;

use App\Models\ThirdChannels;
use App\Models\Products;
use App\Models\ChannelProducts;
use Illuminate\Support\Facades\Cache;

class ThirdChannelController extends Controller
{
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
     * * @param int $id 渠道ID
     * @return JsonResponse
     */
    public function show($id)
    {
        $channel = ThirdChannels::with(['channelProducts.products.business'])->findOrFail($id);
        // return response()->json(['code'=>100,'message'=>'success','data'=>$channel]);
        // 3. 规范化返回结构
        $data = [
            'id'              => $channel->id,
            'name'            => $channel->name,
            'pid'             => $channel->pid,
            'key'             => $channel->key,
            'organization_id' => $channel->organization_id,
            'method'          => $channel->method,
            'remark'          => $channel->remark,
            'status'          => $channel->status,
            'is_ip_restricted'=> $channel->is_ip_restricted,
            'ip_whitelist'    => $channel->ip_whitelist,
            // 将关联的产品数据直接带出
            'products'        => $channel->channelProducts->map(function ($p) {
                return [
                    'id'         => $p->id,
                    'sku_code'   => $p->products->sku_code,//产品标识
                    'bus_code'   => $p->products->business->code,//业务标识
                    'product_id' => $p->product_id,
                    'remark'     => $p->remark,
                    'status'     => $p->status,
                    'product_name' => $p->products ? $p->products->name : '未知产品',
                ];
            })
        ];

        return $this->success($data);
    }

    /**
     * 新增渠道
     */
    public function store(ThirdChannelRequest $request)
    {

        $data = ThirdChannels::create($request->validated());
        return $this->success($data,'渠道创建成功');
    }

    /**
     * 更新渠道
     */
    public function update(ThirdChannelRequest $request, ThirdChannels $channel)
    {

        $data =$channel->update($request->validated());

        //清除缓存
        Cache::forget("channel_auth:{$request->pid}:test");
        Cache::forget("channel_auth:{$request->pid}:prod");
        return $this->success($data,'更新成功');
    }

    /**
     * 逻辑删除
     */
    public function destroy(ThirdChannels $channel)
    {
        return $this->error('生成之后不可删除');

        // 逻辑删除：数据库 deleted_at 会被填充，数据不消失
        // 同时 Audit 会记录一条 event 为 'deleted' 的数据
        $data->delete();
        return $this->success($data,'删除成功');
    }

    /**
     * 获取特定渠道的审计日志 (供前端 showAudit 调用)
     */
    public function audits(ThirdChannels $channel)
    {
        // 获取该记录的所有历史变更
        $data = $channel->audits()->with('user')->orderBy('created_at', 'desc')->get();
        return $this->success($data);
    }

    /**
     * 同步渠道产品配置
     * 逻辑：逻辑删除旧配置，写入新配置
     */
    public function syncProducts(Request $request, $id)
    {

        // 1. 验证参数
        $data = $request->validate([
            'products' => 'present|array',
            'products.*.product_id' => 'required|integer',
            'products.*.status'     => 'required|in:0,1,2',
            'products.*.remark'     => 'nullable|string|max:100'
        ]);

        $channel = ThirdChannels::findOrFail($id);

        // 2. 开启事务，确保数据一致性
        return \DB::transaction(function () use ($channel, $data) {

            // 3. 逻辑删除当前渠道关联的所有产品
            // 这样会触发审计插件记录 delete 行为
            $channel->channelProducts()->delete();

            // 4. 插入新配置
            if (!empty($data['products'])) {
                foreach ($data['products'] as $item) {
                    $channel->channelProducts()->create([
                        'product_id' => $item['product_id'],
                        'status'     => $item['status'],
                        'remark'     => $item['remark'] ?? '',
                    ]);
                }
            }
            return $this->success(null,'更新成功');
        });
    }
}
