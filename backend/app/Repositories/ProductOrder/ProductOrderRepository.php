<?php

namespace App\Repositories\ProductOrder;

use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 产品订单仓库实现
 * 通过环境参数动态选择对应的模型
 */
class ProductOrderRepository implements ProductOrderRepositoryInterface
{
    protected string $environment;

    protected string $modelClass;

    public function __construct(string $environment = 'prod')
    {
        $this->environment = $environment;
        $this->modelClass = $this->resolveModelClass();
    }

    /**
     * 获取当前环境标识
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * 解析对应的模型类
     */
    protected function resolveModelClass(): string
    {
        return $this->environment === 'test'
            ? ProductOrderTest::class
            : ProductOrder::class;
    }

    /**
     * 获取模型实例（不查询数据库）
     */
    public function getModel(): Model
    {
        return new $this->modelClass;
    }

    /**
     * 获取所有匹配记录（不分页，用于导出）
     */
    public function getAll(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->modelClass::query()
            ->with(['thirdChannel', 'products', 'business'])
            ->when(isset($filters['business_id']), fn ($q) => $q->where('business_id', $filters['business_id']))
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(isset($filters['order_no']), fn ($q) => $q->where('order_no', 'like', '%'.$filters['order_no'].'%'))
            ->when(isset($filters['user_phone']), fn ($q) => $q->where('user_phone', $filters['user_phone']))
            ->when(isset($filters['pid']), fn ($q) => $q->whereIn('pid', (array) $filters['pid']))
            ->when(isset($filters['order_status']), fn ($q) => $q->where('order_status', $filters['order_status']))
            ->when(isset($filters['sync_status']), fn ($q) => $q->where('sync_status', $filters['sync_status']))
            ->when(isset($filters['start_date']) && isset($filters['end_date']), fn ($q) => $q->whereBetween('order_time', [$filters['start_date'], $filters['end_date']]))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * 列表查询
     */
    public function index(array $filters = []): LengthAwarePaginator
    {
        $query = $this->modelClass::query()
            ->with(['thirdChannel', 'products', 'business'])
            ->when(isset($filters['business_id']), fn ($q) => $q->where('business_id', $filters['business_id']))
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(isset($filters['order_no']), fn ($q) => $q->where('order_no', 'like', '%'.$filters['order_no'].'%'))
            ->when(isset($filters['user_phone']), fn ($q) => $q->where('user_phone', $filters['user_phone']))
            ->when(isset($filters['pid']), fn ($q) => $q->whereIn('pid', (array) $filters['pid']))
            ->when(isset($filters['order_status']), fn ($q) => $q->where('order_status', $filters['order_status']))
            ->when(isset($filters['sync_status']), fn ($q) => $q->where('sync_status', $filters['sync_status']))
            ->when(isset($filters['start_date']) && isset($filters['end_date']), fn ($q) => $q->whereBetween('order_time', [$filters['start_date'], $filters['end_date']]))
            ->orderBy('id', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * 获取详情
     */
    public function show(int $id): ?Model
    {
        return $this->modelClass::with(['thirdChannel', 'products', 'business'])->findOrFail($id);
    }

    /**
     * 创建订单
     */
    public function store(array $data): Model
    {
        return $this->modelClass::create($data);
    }

    /**
     * 更新订单
     */
    public function update(int $id, array $data): bool
    {
        $model = $this->modelClass::findOrFail($id);

        return $model->update($data);
    }

    /**
     * 删除订单
     */
    public function destroy(int $id): bool
    {
        $model = $this->modelClass::findOrFail($id);

        return $model->delete();
    }

    /**
     * 订单状态统计：按当前筛选条件统计 link_id/code 分布
     */
    public function statusStats(array $filters = []): array
    {
        $query = $this->modelClass::query()
            ->when(isset($filters['business_id']), fn ($q) => $q->where('business_id', $filters['business_id']))
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(isset($filters['order_no']), fn ($q) => $q->where('order_no', 'like', '%'.$filters['order_no'].'%'))
            ->when(isset($filters['user_phone']), fn ($q) => $q->where('user_phone', $filters['user_phone']))
            ->when(isset($filters['pid']), fn ($q) => $q->whereIn('pid', (array) $filters['pid']))
            ->when(isset($filters['order_status']), fn ($q) => $q->where('order_status', $filters['order_status']))
            ->when(isset($filters['sync_status']), fn ($q) => $q->where('sync_status', $filters['sync_status']))
            ->when(isset($filters['start_date']) && isset($filters['end_date']), fn ($q) => $q->whereBetween('order_time', [$filters['start_date'], $filters['end_date']]));

        $total = (clone $query)->count();

        // 已发送验证码：link_id IS NOT NULL
        $sentCode = (clone $query)->whereNotNull('link_id')->where('link_id', '!=', '')->count();

        // 未发送验证码：link_id IS NULL
        $notSentCode = (clone $query)->where(function ($q) {
            $q->whereNull('link_id')->orWhere('link_id', '');
        })->count();

        // 已提交验证码：code IS NOT NULL
        $submitted = (clone $query)->whereNotNull('code')->where('code', '!=', '')->count();

        // 未发送验证码的原因分析（从 forward_orders.step_responses 解析错误码）
        $errorReasons = [
            '40001' => ['label' => '单一管控应用订购套餐个数限制', 'count' => 0],
            '40002' => ['label' => '多管控应用限制订购涉及的应用个数', 'count' => 0],
            '40003' => ['label' => '处于号码管控库（黑名单或者退订期用户）', 'count' => 0],
            '40004' => ['label' => '业务处于超限管控中（日/月发展量超阈值）', 'count' => 0],
            '40005' => ['label' => '非订购时间段管控（22点至次日7点）', 'count' => 0],
            '40006' => ['label' => '业务处于24小时退订率超限管控中（40%）', 'count' => 0],
            '40007' => ['label' => '用户不下发验证码了', 'count' => 0],
            '40008' => ['label' => '系移动系统的大数据黑名单校验', 'count' => 0],
            'other' => ['label' => '其他原因', 'count' => 0],
        ];

        // 获取未发送验证码的订单编号
        $orderNos = (clone $query)
            ->where(function ($q) {
                $q->whereNull('link_id')->orWhere('link_id', '');
            })
            ->pluck('order_no');

        if ($orderNos->isNotEmpty()) {
            // 分块查询，避免 SQL Server 2100 参数限制
            $orderNos->chunk(500)->each(function ($chunk) use (&$errorReasons) {
                $forwardOrders = \App\Models\ForwardOrder::whereIn('source_order_no', $chunk)->get();
                
                foreach ($forwardOrders as $fo) {
                    // 如果有 forward_error，直接归为其他
                    if (!empty($fo->forward_error)) {
                        $errorReasons['other']['count']++;
                        continue;
                    }
                    
                    // 如果 step_responses 为空，归为其他
                    if (empty($fo->step_responses)) {
                        $errorReasons['other']['count']++;
                        continue;
                    }
                    
                    $responses = is_string($fo->step_responses) ? json_decode($fo->step_responses, true) : $fo->step_responses;
                    if (!is_array($responses) || empty($responses)) {
                        $errorReasons['other']['count']++;
                        continue;
                    }
                    
                    $stepErrorFound = false;
                    foreach ($responses as $step) {
                        $message = $step['response']['data']['message'] ?? '';
                        $code = $step['response']['data']['code'] ?? '';
                        
                        // 匹配 "办理业务失败。提示：'40002'" 或 "办理业务失败。提示：'40005','40003'"
                        if (preg_match_all("/'(\d{5})'/", $message, $allMatches)) {
                            foreach ($allMatches[1] as $codeFound) {
                                if (isset($errorReasons[$codeFound])) {
                                    $errorReasons[$codeFound]['count']++;
                                } else {
                                    $errorReasons['other']['count']++;
                                }
                            }
                            $stepErrorFound = true;
                        } elseif (!empty($message) || (empty($code) || $code !== '00000')) {
                            // 有错误消息或者 code 不是 00000，归为其他
                            $errorReasons['other']['count']++;
                            $stepErrorFound = true;
                        }
                    }
                    
                    // 如果遍历完所有步骤都没找到错误（所有都是 00000 且空消息），仍然归为其他
                    // 因为这表示推送成功但没有返回 link_id，说明验证码并未成功发送
                    if (!$stepErrorFound) {
                        $errorReasons['other']['count']++;
                    }
                }
            });

            // 统计有多少未发送验证码的订单没有 forward_orders 记录
            // 这些也归为其他原因
            $totalWithForward = \App\Models\ForwardOrder::whereIn('source_order_no', $orderNos)->count();
            $totalWithoutForward = $orderNos->count() - $totalWithForward;
            if ($totalWithoutForward > 0) {
                $errorReasons['other']['count'] += $totalWithoutForward;
            }
        }

        return [
            'total' => $total,
            'sent_code' => $sentCode,
            'not_sent_code' => $notSentCode,
            'submitted' => $submitted,
            'error_reasons' => array_values($errorReasons),
        ];
    }
}
