<?php
//鑫全域数据控制
namespace App\Services\ThirdChannel;

use App\Models\QuanyuOrder;
use App\Models\AuditLog; // 假设的审计日志模型
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class QuanyuService
{

    // 天轩配置信息
    const baseUrl = 'http://cladmintest.xinquanyu.top';//测试地址
    // const baseUrl = 'http://cladmin.xinquanyu.top';//正式地址
    const key = '1252KS25D7F3ZC7J'; // 建议放入 .env
    const pid = '186';
    const busCode = '96339749';
    const skuCode = 'SCJKGJHYYK';

    /**
     * 处理订单数据（新增或更新）
     * * @param array $data 经过校验的订单数组
     * @param string $source 操作来源 (例如: 'API:SupplierA' 或 'WEB')
     * @return QuanyuOrder
     */
    public static function store($data,$organization_id)
    {
        $data['organization_id']=$organization_id;
        return QuanyuOrder::create($data);
    }

    /**
     * 退订处理
     *
     */
    public static function saveCancel($data){
        if(!isset($data['order_no'])){
            return  response()->json(['code' => 401, 'msg' => '需要有订单号','data'=>$data], 402);
        }

        $order =QuanyuOrder::where('order_no',$data['order_no'])
            ->whereNull('deleted_at') // 确保不操作已逻辑删除的数据
            ->first();;
        if($order==null){
            return  response()->json(['code' => 402, 'msg' => '找不到对应的订单']);
        }
        $order->fill(['order_status'=>1]);//订单状态改为退订
        $order->save();
        return $order;
    }

    /**
     * 执行推送逻辑 (可被 Controller 或 手动重试的任务复用)
     * * @param Order $order
     * @return Order
     */
    public function OrderPushToTianxuan($order)
    {

        $url ='/api_v2/ThirdChannel/syncUserData';
        // 准备请求参数 (严格对应文档字段)
        $params = [
            'mobile'      => $order->mobile,
            'pid'         => self::pid,
            'bus_code'    => self::busCode,
            'sku_code'    => self::skuCode,
            'order_no'    => $order->order_no,
            'create_time' => (string)time(), // 10位时间戳
            'type'        => '1',            // 平安健康业务
            'platform'    => $order->platform ?? '333',
            'pack'        => $order->pack ?? 'eee',
            'url'         => $order->url ?? 'www.baidu.com',
            'ip'          => request()->ip() ?? '127.0.0.1',
            'sms_time'    => (string)time(),
            'code'        => $order->code ?? '123456',
        ];

        // 生成签名
        $sign = self::generateSign($params);

        try {
            $response = Http::withHeaders(['sign' => $sign])
                ->timeout(10)
                ->post(self::baseUrl . $url, $params);

            $resData = $response->json();
            // 文档规定：code=0 是成功
            if ($response->successful() && isset($resData['code']) && $resData['code'] === 0) {
                $order->update([
                    'sync_status' => 1,
                    'pushed_at'   => now(),
                    'sync_error'  => null
                ]);
                return response()->json(['code'=>0,'msg'=>'同步成功','url'=>$url,'push'=>$resData]);//成功
            } else {
                $errorMsg = $resData['msg'] ?? '接口返回错误';
                self::markAsFailed($order, "Code: {$resData['code']}, Msg: {$errorMsg}");
                return response()->json(['code' => 403, 'msg' => $errorMsg,'params'=>$params,'return'=>$resData,'url'=>$url]);//失败
            }
        } catch (\Exception $e) {
            self::markAsFailed($order, "网络异常: " . $e->getMessage());
            return response()->json(['code' => 405, 'msg' => '网络异常','error'=>$e->getMessage(),'url'=>$url]);//失败
        }
        return response()->json(['code' => 405, 'msg' => '同步失败，原因未知','url'=>$url]);
    }
    /**
     * 执行推送逻辑 (可被 Controller 或 手动重试的任务复用)
     * * @param Order $order
     * @return Order
     */
    public static function cancelPushToTianxuan($order)
    {

        $url ='/api_v2/ThirdChannel/syncUnsubUserData';
        // 准备请求参数 (严格对应文档字段)
        $params = [
            'mobile'      => $order->mobile,
            'pid'         => self::pid,
            'bus_code'    => self::busCode,
            'order_no'    => '202405281643134432',//$order->order_no,
            'create_time' => (string)time(), // 10位时间戳
        ];

        // 生成签名
        $sign = self::generateSign($params);

        try {
            $response = Http::withHeaders(['sign' => $sign])
                ->timeout(10)
                ->post(self::baseUrl . $url, $params);

            $resData = $response->json();
            // 文档规定：code=0 是成功
            if ($response->successful() && isset($resData['code']) && $resData['code'] === 0) {
                $order->update([
                    'cancel_sync_status' => 1,
                    'cancel_at'   => now(),
                    'sync_error'  => null
                ]);
                return response()->json(['code'=>0,'msg'=>'同步成功','url'=>$url]);//成功
            } else {
                $errorMsg = $resData['msg'] ?? '接口返回错误';
                self::cancelMarkAsFailed($order, "Code: {$resData['code']}, Msg: {$errorMsg}");//记录推送结果
                return response()->json(['code' => 403, 'msg' => $errorMsg,'params'=>$params,'return'=>$resData,'url'=>$url]);//失败
            }
        } catch (\Exception $e) {
            self::cancelMarkAsFailed($order, "网络异常: " . $e->getMessage());//记录推送结果
            return response()->json(['code' => 405, 'msg' => '网络异常','error'=>$e->getMessage(),'url'=>$url]);//失败
        }
        return response()->json(['code' => 405, 'msg' => '同步失败，原因未知','url'=>$url]);
    }

    /**
     * 严谨的签名算法实现
     * 1. 字典序排序 2. 格式化拼接 3. 拼Key 4. MD5 5. 大写
     */
    protected static function generateSign(array $params): string
    {
        // 1. 按字典序排序参数名 (ksort)
        ksort($params);

        // 2. 格式化参数为字符串 (http_build_query 会处理 URL 编码，文档要求是原始拼接)
        // 注意：文档示例是 bus_code=...&mobile=...，需手动构建避免自动 urlencode 干扰签名
        $string = '';
        foreach ($params as $key => $val) {
            $string .= "{$key}={$val}&";
        }

        // 3. 拼接 Key
        $string .= "key=" . self::key;
        // 4 & 5. MD5 加密并转化成大写
        return strtoupper(md5($string));
    }

    protected function markAsFailed($order, string $reason): void
    {
        $order->update([
            'sync_status' => 2,
            'sync_error'  => mb_substr($reason, 0, 500)
        ]);
        Log::warning("下家同步失败 [ID: {$order->id}]: " . $reason);
    }
    protected static function cancelMarkAsFailed($order, string $reason): void
    {
        $order->update([
            'cancel_sync_status' => 2,
            'cancel_sync_error'  => mb_substr($reason, 0, 500)
        ]);
        Log::warning("下家同步失败 [ID: {$order->id}]: " . $reason);
    }
    /**
     * 格式化并过滤字段，确保符合 Schema
     */
    private function formatOrderData(array $data): array
    {
        return [
            'mobile'       => $data['mobile'],
            'pid'          => $data['pid'],
            'bus_code'     => $data['bus_code'],
            'sku_code'     => $data['sku_code'],
            'order_no'     => $data['order_no'],
            'create_time'  => $data['create_time'] ?? (string)time(),
            'type'         => $data['type'] ?? '1',
            'platform'     => $data['platform'] ?? null,
            'pack'         => $data['pack'] ?? null,
            'url'          => $data['url'] ?? null,
            'ip'           => $data['ip'] ?? request()->ip(),
            'sms_time'     => $data['sms_time'] ?? null,
            'code'         => $data['code'] ?? null,
            'sync_status'  => $data['sync_status'] ?? 0,
            'order_status' => $data['order_status'] ?? 0,
            'ext_json'     => isset($data['ext_json']) ? json_encode($data['ext_json'], JSON_UNESCAPED_UNICODE) : null,
            'updated_by'   => Auth::id() ?? 0,
        ];
    }

    /**
     * 记录审计日志
     */
    private function recordAudit($orderId, $action, $before, $after, $source)
    {
        // 严谨规范：记录谁、在什么时间、把哪个订单从什么改成了什么
        Log::info("Order Audit [{$action}]", [
            'order_id' => $orderId,
            'source'   => $source,
            'before'   => $before,
            'after'    => $after,
            'user_id'  => Auth::id() ?? 0,
            'ip'       => request()->ip()
        ]);

        // 如果有 audit_logs 表，在此处入库
        /*
        AuditLog::create([
            'table_name' => 'quanyu_orders',
            'row_id'     => $orderId,
            'event'      => $action,
            'before'     => json_encode($before),
            'after'      => json_encode($after),
            'operator'   => $source
        ]);
        */
    }
}
