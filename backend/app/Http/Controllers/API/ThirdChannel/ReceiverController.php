<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Http\Requests\ProductOrderRequest;
use App\Jobs\NotifyOriginJob;
use App\Jobs\ProcessOrderJob;
use App\Models\ForwardOrder;
use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ChannelConfigLoader;
use App\Services\ThirdChannel\ForwardService;
use App\Services\ThirdChannel\ForwardStopService;
use App\Services\ThirdChannel\InboundMapper;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiverController extends Controller
{
    /**
     * 新增订单
     */
    public function store(ProductOrderRequest $request)
    {
        // 1. 从中间件获取已验证的渠道信息
        $channel = $request->get('_authenticated_channel');
        $isTestEnv = $request->get('_is_test_env');

        if (! $channel) {
            return $this->error('未通过身份验证', 401);
        }

        // 2. 获取已验证的业务数据
        $data = $request->validated();

        // 2.1 入站映射透传的原始 payload，留痕到 ext_json（product_orders 有该列）
        $inboundRaw = $request->get('_inbound_raw');
        if (! empty($inboundRaw)) {
            $data['ext_json'] = is_string($inboundRaw) ? $inboundRaw : json_encode($inboundRaw, JSON_UNESCAPED_UNICODE);
        }

        // 3. 同步验证业务标识和产品标识是否属于该渠道且状态正确
        $product = $this->verifyProductForForward($channel, $data);
        if (! $product) {
            \Illuminate\Support\Facades\Log::warning('订单同步验证失败：未经授权的产品或业务', [
                'pid' => $channel->pid,
                'sku_code' => $data['sku_code'] ?? '',
                'bus_code' => $data['bus_code'] ?? '',
                'order_no' => $data['order_no'] ?? '',
            ]);
            return $this->error('数据验证失败: 业务编码(bus_code)或产品编码(sku_code)不属于当前渠道或未启用', 422);
        }

        // 3.1 停止条件拦截：满足任一停止条件则切断 C→A 转发，直接返回提示
        $stopHit = app(ForwardStopService::class)->evaluate($channel, $data, 'getCode');
        if ($stopHit) {
            return $this->error($stopHit['message'], $this->stopErrorCode($stopHit['condition_id'] ?? ''), [
                'stop' => true,
                'condition_type' => $stopHit['condition_type'] ?? null,
                'condition_id' => $stopHit['condition_id'] ?? null,
            ]);
        }

        // 4. 生成全链路追踪 trace_id
        $data['trace_id'] = \Illuminate\Support\Str::uuid()->toString();

        // 5. 派发到队列异步处理（写入数据库、触发事件）
        ProcessOrderJob::dispatch($data, $channel, $isTestEnv, $request->ip())->onQueue('high');

        // 6. 检查是否配置了验证码中转流程（ext_config.target_pid → 目标渠道有 push_steps）
        $forwardResult = $this->dispatchForwardIfNeeded($channel, $data, $isTestEnv);

        $responseData = ['order_no' => $data['order_no']];
        if ($forwardResult) {
            $responseData['forward_order_id'] = $forwardResult['forward_order_id'];
            $responseData['forward_status'] = $forwardResult['forward_status'];

            if ($forwardResult['success']) {
                // 同步转发成功，返回移动完整响应
                $responseData['linkId'] = $forwardResult['linkId'] ?? null;
                $responseData['current_step'] = $forwardResult['current_step'] ?? 1;
                $responseData['b_response'] = $forwardResult['b_response'] ?? null;
                $message = '订单已接收，getCode 转发成功';
            } else {
                // 同步转发失败，返回错误信息
                $responseData['forward_error'] = $forwardResult['error'] ?? null;
                $message = '订单已接收，但 getCode 转发失败';
            }
        } else {
            $message = '订单已接收，处理中';
        }

        return $this->success($responseData, $message);
    }

    /**
     * 检查是否需要自动创建数据中转记录
     * 当源渠道配置了 auto_forward=true 且 forward_target_pid 有值时，
     * 且目标渠道有 push_steps 时，自动创建 ForwardOrder 并同步执行第0步（getCode）转发
     *
     * 优先级：新字段 auto_forward + forward_target_pid > 旧字段 ext_config.target_pid
     * 创建前会验证上家数据是否包含下家要求的必要字段
     *
     * @return array|null 成功时返回 {forward_order_id, success, forward_status, linkId, error}, 未创建时返回 null
     */
    protected function dispatchForwardIfNeeded($channel, array $data, bool $isTestEnv = false): ?array
    {
        // 目标 A 解析：从业务(bus_code/sku_code)归属组织找到 supplier_a 渠道
        // 不再使用 C 自身渠道配置（auto_forward/forward_target_pid/ext_config.target_pid）来定位上家
        $targetChannel = $this->resolveForwardTargetByBusiness($channel, $data);
        if (! $targetChannel) {
            return null;
        }
        $targetPid = $targetChannel->pid;

        try {
            $steps = $targetChannel->push_steps ?? [];
            if (empty($steps) || ! is_array($steps)) {
                // 目标渠道没有配置多步骤推送，不需要创建中转
                return null;
            }

            // 验证 A 公司的数据是否包含 B 公司要求的必要字段
            $validationErrors = $this->validateForwardParams($data, $steps);
            if (! empty($validationErrors)) {
                Log::warning('自动中转跳过：A 公司数据缺少 B 公司要求的必要字段', [
                    'source_pid' => $channel->pid,
                    'target_pid' => $targetPid,
                    'errors' => $validationErrors,
                ]);
                return null;
            }

            // 创建 ForwardOrder
            $forwardOrder = ForwardOrder::create([
                'trace_id' => $data['trace_id'] ?? null,
                'source_channel_id' => $channel->id,
                'target_channel_id' => $targetChannel->id,
                'source_order_no' => $data['order_no'] ?? '',
                'source_pid' => $channel->pid,
                'target_pid' => $targetPid,
                'source_data' => $data,
                'forward_status' => ForwardOrder::STATUS_PENDING,
                'current_step' => 0,
                'mobile' => $data['user_phone'] ?? $data['mobile'] ?? null,
                'callback_url' => $data['callback_url'] ?? $channel->callback_url,
                'organization_id' => $channel->organization_id,
                'product_id' => $this->extractProductId($data),
            ]);

            Log::info('自动创建数据中转记录（验证码流程）', [
                'forward_order_id' => $forwardOrder->id,
                'source_order_no' => $data['order_no'] ?? '',
                'source_pid' => $channel->pid,
                'target_pid' => $targetPid,
                'steps_count' => count($steps),
            ]);

            // 同步执行第0步（getCode）转发
            $forwardService = app(ForwardService::class);
            $result = $forwardService->executeStep($forwardOrder, 0);

            // 无论移动回复成功/失败，都创建产品订单（状态：未付款），记录转发日志
            $this->createProductOrderFromForward($channel, $data, $isTestEnv, $forwardOrder->id, $result);

            if ($result['success']) {
                $stepData = $forwardOrder->step_data ?? [];
                $newStepData = array_merge($stepData, $result['step_data']);

                $forwardOrder->update([
                    'forward_status' => ForwardOrder::STATUS_VERIFYING,
                    'current_step' => 1,
                    'step_data' => $newStepData,
                    'step_responses' => [
                        [
                            'step_index' => 0,
                            'step_name' => $result['step_name'] ?? 'step_0',
                            'request' => $forwardOrder->transformed_data ?? [],
                            'response' => $result['result'],
                            'time' => now()->toDateTimeString(),
                        ],
                    ],
                ]);

                Log::info('同步 getCode 转发成功', [
                    'forward_order_id' => $forwardOrder->id,
                    'linkId' => $result['step_data']['linkId'] ?? null,
                ]);

                $linkId = $result['step_data']['linkId'] ?? null;

                return [
                    'forward_order_id' => $forwardOrder->id,
                    'success' => true,
                    'forward_status' => ForwardOrder::STATUS_VERIFYING,
                    'linkId' => $linkId,
                    'current_step' => 1,
                    'b_response' => $result['b_response'] ?? null,
                ];
            } else {
                $forwardOrder->update([
                    'forward_status' => ForwardOrder::STATUS_FAILED,
                    'forward_error' => $result['error'],
                    'error_code' => $result['error_code'] ?? null,
                    'product_id' => $this->extractProductId($data),
                ]);

                Log::error('同步 getCode 转发失败', [
                    'forward_order_id' => $forwardOrder->id,
                    'error' => $result['error'],
                ]);

                return [
                    'forward_order_id' => $forwardOrder->id,
                    'success' => false,
                    'forward_status' => ForwardOrder::STATUS_FAILED,
                    'error' => $result['error'],
                ];
            }
        } catch (\Exception $e) {
            Log::error('自动创建中转记录异常', [
                'source_pid' => $channel->pid,
                'target_pid' => $targetPid ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 从订单数据中提取产品ID（sku_code 优先，其次 product_id）
     */
    protected function extractProductId(array $data): ?string
    {
        $productId = $data['sku_code'] ?? $data['product_id'] ?? null;
        if ($productId === null || $productId === '') {
            return null;
        }
        return (string) $productId;
    }

    /**
     * 根据业务归属组织解析转发目标（A 供应商渠道）
     *
     * 同步链路：B 收到 C 的验证码请求后，通过业务(bus_code/sku_code)取得其所属组织，
     * 再经 ChannelConfigLoader.getForwardTargetByOrg() 找到该组织的 supplier_a 渠道，
     * 最后使用该 A 的推送配置（push_base_url + push_steps）完成转发。
     *
     * @param  mixed  $channel  发起请求的 C 渠道
     * @param  array  $data     C 提交的订单数据（含 bus_code/sku_code）
     * @return \App\Models\ThirdChannels|null
     */
    protected function resolveForwardTargetByBusiness($channel, array $data): ?ThirdChannels
    {
        $busCode = $data['bus_code'] ?? null;
        $skuCode = $data['sku_code'] ?? null;
        if (! $busCode || ! $skuCode) {
            Log::warning('自动中转解析目标 A 失败：缺少 bus_code/sku_code', [
                'source_pid' => $channel->pid,
            ]);
            return null;
        }

        $orgId = DB::table('business')
            ->join('products', 'products.business_id', '=', 'business.id')
            ->where('business.code', $busCode)
            ->where('products.sku_code', $skuCode)
            ->value('business.org_id');

        if (! $orgId) {
            Log::warning('自动中转解析目标 A 失败：业务不存在', [
                'source_pid' => $channel->pid,
                'bus_code' => $busCode,
                'sku_code' => $skuCode,
            ]);
            return null;
        }

        $targetChannel = app(ChannelConfigLoader::class)->getForwardTargetByOrg((int) $orgId);
        if (! $targetChannel) {
            Log::warning('自动中转解析目标 A 失败：组织未配置 supplier_a 渠道', [
                'source_pid' => $channel->pid,
                'org_id' => $orgId,
                'bus_code' => $busCode,
            ]);
        }

        return $targetChannel;
    }

    /**
     * 验证 A 公司的数据是否包含 B 公司要求的必要字段
     * 检查 request_mapping 中每个字段是否能从 A 数据中找到对应的源字段
     */
    protected function validateForwardParams(array $data, array $steps): array
    {
        $errors = [];

        foreach ($steps as $stepIndex => $step) {
            $requestMapping = $step['request_mapping'] ?? [];
            foreach ($requestMapping as $bField => $sourceExpr) {
                // 固定值（如 "gxhjy004"、"2000"、"0411cca9"）无需校验
                if (is_string($sourceExpr) && ! isset($data[$sourceExpr]) && ! preg_match('/^\{/', $sourceExpr)) {
                    continue;
                }

                // 上一步输出引用（如 {step0.linkId}）无需校验，会在运行时填充
                if (is_string($sourceExpr) && preg_match('/^\{/', $sourceExpr)) {
                    continue;
                }

                // 字段引用（如 "mobile"、"user_phone"、"order_no"）→ 检查 A 数据中是否有此字段
                if (is_string($sourceExpr) && ! isset($data[$sourceExpr])) {
                    $errors[] = "步骤{$stepIndex}({$step['name']}) 缺少字段: {$bField} (需要源字段: {$sourceExpr})";
                }
            }
        }

        return $errors;
    }

    /**
     * 更新订单状态
     */
    public function update(ProductOrderRequest $request)
    {
        // 1. 从中间件获取已验证的渠道信息
        $channel = $request->get('_authenticated_channel');
        $isTestEnv = $request->get('_is_test_env');

        if (! $channel) {
            return $this->error('未通过身份验证', 401);
        }

        // 2. 获取已验证的业务数据
        $data = $request->validated();

        // 3. 选择表模型
        $modelClass = $isTestEnv ? ProductOrderTest::class : ProductOrder::class;
        $statusTable = $isTestEnv ? 'product_order_status_tests' : 'product_order_statuses';

        // 4. 查询订单（不加锁）
        $order = $modelClass::where('order_no', $data['order_no'])
            ->where('pid', $channel->pid)
            ->first();

        if (! $order) {
            return $this->error('未找到对应订单', 404);
        }

        $newStatus = isset($data['order_status']) ? (int) $data['order_status'] : $order->order_status;

        if ($order->order_status !== $newStatus) {
            $oldStatus = $order->order_status;

            // 更新主表
            $order->order_status = $newStatus;
            $order->save();

            // 准备状态流水数据
            $statusData = [
                'table' => $statusTable,
                'order_id' => $order->id,
                'pid' => $channel->pid,
                'order_no' => $order->order_no,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'price' => $order->price,
                'total_amount' => $order->total_amount,
                'operator' => 'API_CALLBACK',
                'created_at' => now(),
            ];

            // 触发事件驱动流水线（审计日志、状态变更通知等）
            event(new OrderStatusUpdated(
                $order, $channel, $isTestEnv, $oldStatus, $newStatus, $statusData
            ));

            return $this->success($order, '修改成功');
        }

        return $this->success(null, '状态没有改变');
    }

    /**
     * 提交验证码（A公司收到短信后调用，触发 submit 步骤）
     *
     * 请求参数：
     *   mobile  - 手机号
     *   linkId  - getCode 接口返回的验证码ID
     *   smsCode - 用户收到的短信验证码
     *
     * 流程：
     *   1. 查找该手机号对应的待验证 ForwardOrder
     *   2. 将 smsCode 写入 step_data
     *   3. 同步执行第1步（submit）转发，等 B 回复后直接返回结果
     */
    public function verifySubmit(Request $request): JsonResponse
    {
        $channel = $request->get('_authenticated_channel');
        $isTestEnv = $request->get('_is_test_env', false);
        if (! $channel) {
            return $this->error('未通过身份验证', 401);
        }

        // 入站映射：按渠道 receive_mapping.verify 翻译外部字段为内部键（mobile/linkId/smsCode/bus_code/sku_code）
        $verifyInput = $request->all();
        $verifyMapping = $channel->receive_mapping['verify'] ?? null;
        if (! empty($verifyMapping)) {
            $mapped = app(InboundMapper::class)->mapForReceive($verifyInput, $verifyMapping);
            $verifyInput = array_merge($verifyInput, $mapped['mapped'] ?? []);
        }

        // 验证输入
        $mobile = $verifyInput['mobile'] ?? null;
        $linkId = $verifyInput['linkId'] ?? null;
        $smsCode = $verifyInput['smsCode'] ?? null;
        $busCode = $verifyInput['bus_code'] ?? null;
        $skuCode = $verifyInput['sku_code'] ?? null;

        $errors = [];
        if (! $mobile) {
            $errors[] = '手机号(mobile)不能为空';
        }
        if (! $linkId) {
            $errors[] = '验证码ID(linkId)不能为空';
        }
        if (! $smsCode) {
            $errors[] = '验证码(smsCode)不能为空';
        }
        if (! $busCode) {
            $errors[] = '业务编码(bus_code)不能为空';
        }
        if (! $skuCode) {
            $errors[] = '产品编码(sku_code)不能为空';
        }
        if (! empty($errors)) {
            return $this->error(implode('; ', $errors), 400);
        }

        // 查找待验证的 ForwardOrder
        $forwardOrder = ForwardOrder::pendingVerify($mobile, $channel->pid)
            ->latest()
            ->first();

        if (! $forwardOrder) {
            Log::warning('验证码提交失败：未找到待验证的订单', [
                'mobile' => $mobile,
                'source_pid' => $channel->pid,
            ]);
            return $this->error('未找到待验证的订单，请先发送验证码', 404);
        }

        // 验证 bus_code 和 sku_code 与原始数据一致
        $sourceData = $forwardOrder->source_data ?? [];
        $origBusCode = $sourceData['bus_code'] ?? null;
        $origSkuCode = $sourceData['sku_code'] ?? null;
        if ($busCode !== $origBusCode) {
            Log::warning('验证码提交失败：业务编码不匹配', [
                'forward_order_id' => $forwardOrder->id,
                'submitted' => $busCode,
                'expected' => $origBusCode,
            ]);
            return $this->error('业务编码(bus_code)与原始订单不一致', 400);
        }
        if ($skuCode !== $origSkuCode) {
            Log::warning('验证码提交失败：产品编码不匹配', [
                'forward_order_id' => $forwardOrder->id,
                'submitted' => $skuCode,
                'expected' => $origSkuCode,
            ]);
            return $this->error('产品编码(sku_code)与原始订单不一致', 400);
        }

        // 如果已达到最大尝试次数，拒绝
        $maxAttempts = 5;
        if ($forwardOrder->verify_attempts >= $maxAttempts) {
            Log::warning('验证码提交失败：超过最大尝试次数', [
                'forward_order_id' => $forwardOrder->id,
                'attempts' => $forwardOrder->verify_attempts,
            ]);
            return $this->error('验证码提交次数过多，订单已锁定', 429);
        }

        // 并发锁：防止同一 ForwardOrder 被并发提交（A 公司可能短时间内多次调用）
        $lockKey = "verify_submit:{$forwardOrder->id}";
        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            Log::warning('验证码提交被并发锁拦截', [
                'forward_order_id' => $forwardOrder->id,
                'mobile' => $mobile,
            ]);
            return $this->error('验证码提交处理中，请勿重复提交', 429);
        }

        try {
            // 获取锁后重新查询，确认 ForwardOrder 仍处于待验证状态
            $forwardOrder->refresh();
            if ($forwardOrder->forward_status !== ForwardOrder::STATUS_VERIFYING) {
                Log::warning('验证码提交跳过：ForwardOrder 状态已变更（并发冲突）', [
                    'forward_order_id' => $forwardOrder->id,
                    'current_status' => $forwardOrder->forward_status,
                ]);
                return $this->error('该订单已处理，请勿重复提交', 409);
            }

            // 3.1 停止条件拦截：submit 环节也校验停止条件，命中则切断 C→A 转发直接返回提示
            $verifyData = array_merge($forwardOrder->source_data ?? [], $forwardOrder->step_data ?? []);
            $verifyData['smsCode'] = $smsCode;
            $stopHit = app(ForwardStopService::class)->evaluate($channel, $verifyData, 'submit');
            if ($stopHit) {
                return $this->error('submit 转发已停止: '.$stopHit['message'], $this->stopErrorCode($stopHit['condition_id'] ?? ''), [
                    'stop' => true,
                    'condition_type' => $stopHit['condition_type'] ?? null,
                    'condition_id' => $stopHit['condition_id'] ?? null,
                ]);
            }

            // 更新 step_data 加入 smsCode 和 linkId
            $stepData = $forwardOrder->step_data ?? [];
            $stepData['smsCode'] = $smsCode;
            $stepData['linkId'] = $linkId;
            $forwardOrder->update([
                'step_data' => $stepData,
                'verify_attempts' => $forwardOrder->verify_attempts + 1,
            ]);

            // 同步执行 submit 步骤（stepIndex=1），等 B 回复后直接返回
            $forwardService = app(ForwardService::class);
            $result = $forwardService->executeStep($forwardOrder, 1);

            if ($result['success']) {
                // 记录步骤响应
                $stepResponses = $forwardOrder->step_responses ?? [];
                $stepResponses[] = [
                    'step_index' => 1,
                    'step_name' => $result['step_name'] ?? 'step_1',
                    'request' => $forwardOrder->transformed_data ?? [],
                    'response' => $result['result'],
                    'time' => now()->toDateTimeString(),
                ];

                $targetOrderNo = $result['result']['data']['order_no'] ?? $result['result']['order_no'] ?? $result['result']['data']['transId'] ?? null;

                $forwardOrder->update([
                    'forward_status' => ForwardOrder::STATUS_SUCCESS,
                    'current_step' => 2,
                    'target_response' => $result['result'],
                    'target_order_no' => $targetOrderNo,
                    'step_data' => $stepData,
                    'step_responses' => $stepResponses,
                ]);

                Log::info('验证码提交成功，submit 转发成功', [
                    'forward_order_id' => $forwardOrder->id,
                    'mobile' => $mobile,
                    'source_order_no' => $forwardOrder->source_order_no,
                    'target_order_no' => $targetOrderNo,
                ]);

                // 移动反馈成功，修改产品订单状态为"首次订购"（order_status=1），记录提交日志
                $this->updateProductOrderStatusToFirstOrder($forwardOrder, $channel, $isTestEnv, $result);

                return $this->success([
                    'forward_order_id' => $forwardOrder->id,
                    'source_order_no' => $forwardOrder->source_order_no,
                    'target_order_no' => $targetOrderNo,
                    'forward_status' => ForwardOrder::STATUS_SUCCESS,
                    'b_response' => $result['result'] ?? null,
                ], '验证码提交成功，submit 转发成功');
            } else {
                // 记录失败响应
                $stepResponses = $forwardOrder->step_responses ?? [];
                $stepResponses[] = [
                    'step_index' => 1,
                    'step_name' => $result['step_name'] ?? 'step_1',
                    'request' => $forwardOrder->transformed_data ?? [],
                    'response' => $result['result'] ?? ['error' => $result['error']],
                    'time' => now()->toDateTimeString(),
                ];

                $forwardOrder->update([
                    'forward_status' => ForwardOrder::STATUS_FAILED,
                    'forward_error' => $result['error'],
                    'error_code' => $result['error_code'] ?? null,
                    'step_responses' => $stepResponses,
                ]);

                // 更新产品订单的 forward_log，记录提交失败信息
                $this->appendForwardLogOnFailure($forwardOrder, $channel, $isTestEnv, $result);

                Log::error('验证码提交失败，submit 转发失败', [
                    'forward_order_id' => $forwardOrder->id,
                    'mobile' => $mobile,
                    'error' => $result['error'],
                ]);

                return $this->error('submit 转发失败: '.$result['error'], 502);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * 查询转发记录（A公司通过签名认证查询）
     * GET /api/v2/third-channel/query-forward
     *
     * 请求参数：
     *   mobile - 手机号（必填）
     *   source_order_no - 订单号（可选，精确筛选）
     *
     * 返回转发记录列表，包含各步骤详情和 linkId
     */
    public function queryForward(Request $request): JsonResponse
    {
        $channel = $request->get('_authenticated_channel');
        if (! $channel) {
            return $this->error('未通过身份验证', 401);
        }

        $mobile = $request->input('mobile');
        if (! $mobile) {
            return $this->error('手机号(mobile)不能为空', 400);
        }

        $query = ForwardOrder::where('source_pid', $channel->pid)
            ->where('mobile', $mobile)
            ->orderBy('id', 'desc');

        if ($request->filled('source_order_no')) {
            $query->where('source_order_no', $request->input('source_order_no'));
        }

        $orders = $query->get()->map(function ($order) {
            $stepData = $order->step_data ?? [];
            $callbackStatusMap = [
                0 => '待回调',
                1 => '已回调成功',
                2 => '回调失败',
            ];
            $cbStatus = $order->callback_status;
            return [
                'id' => $order->id,
                'source_order_no' => $order->source_order_no,
                'forward_status' => $order->forward_status,
                'current_step' => $order->current_step,
                'mobile' => $order->mobile,
                'linkId' => $stepData['linkId'] ?? null,
                'forward_error' => $order->forward_error,
                'target_order_no' => $order->target_order_no,
                'callback_url' => $order->callback_url,
                'callback_status' => $cbStatus,
                'callback_status_text' => $callbackStatusMap[$cbStatus] ?? '未知',
                'callback_response' => $order->callback_response,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return $this->success($orders);
    }

    /**
     * 推送完整订单数据到来源系统（A 公司）
     * 转发完成后，将完整订单数据推送到 callback_url
     * 异步执行，不阻塞接口响应
     */
    protected function dispatchCallback(ForwardOrder $forwardOrder): void
    {
        $callbackUrl = $forwardOrder->callback_url;
        if (! $callbackUrl) {
            return;
        }

        // 已回调过的不再重复
        if ($forwardOrder->callback_status !== ForwardOrder::CALLBACK_PENDING) {
            return;
        }

        // 查找产品订单
        $order = ProductOrder::where('order_no', $forwardOrder->source_order_no)
            ->where('pid', $forwardOrder->source_pid)
            ->first();

        if (! $order) {
            // 没有产品订单时，回退到 NotifyOriginJob 发送通知
            NotifyOriginJob::dispatch($forwardOrder->id);
            return;
        }

        // 直接执行推送（不依赖队列驱动，避免 database 队列不执行 afterResponse 的问题）
        $this->executeCallbackPush($callbackUrl, $forwardOrder, $order);
    }

    /**
     * 执行回调推送（同步执行，不受队列驱动影响）
     */
    protected function executeCallbackPush(string $callbackUrl, ForwardOrder $forwardOrder, ProductOrder $order): void
    {
        try {
            // 准备推送数据（与批量推送的数据格式一致）
            $pushData = $order->toArray();
            $internalFields = ['id', 'sync_status', 'pushed_at', 'sync_error',
                'link_id', 'trace_id', 'product_id', 'business_id', 'channel_id',
                'organization_id', 'deleted_at', 'created_at', 'updated_at',
                'ext_json', 'internal_amount', 'settlement_id', 'settle_status'];
            foreach ($internalFields as $field) {
                unset($pushData[$field]);
            }

            // 追加转发结果
            $pushData['forward_id'] = $forwardOrder->id;
            $pushData['forward_status'] = $forwardOrder->forward_status;
            $pushData['target_order_no'] = $forwardOrder->target_order_no;
            $pushData['callback_processed_at'] = now()->toDateTimeString();

            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($callbackUrl, $pushData);

            $httpStatus = $response->status();
            $isSuccess = $httpStatus >= 200 && $httpStatus < 300;

            // 更新回调状态
            $forwardOrder->update([
                'callback_status' => $isSuccess ? ForwardOrder::CALLBACK_SUCCESS : ForwardOrder::CALLBACK_FAILED,
                'callback_response' => $response->json() ?: $response->body(),
            ]);

            if ($isSuccess) {
                Log::channel('push')->info('已推送完整订单数据到来源系统（验证码阶段）', [
                    'forward_order_id' => $forwardOrder->id,
                    'order_no' => $order->order_no,
                    'callback_url' => $callbackUrl,
                    'http_status' => $httpStatus,
                ]);
            } else {
                Log::channel('push_failure')->warning('推送完整订单数据到来源系统失败（验证码阶段）', [
                    'forward_order_id' => $forwardOrder->id,
                    'order_no' => $order->order_no,
                    'callback_url' => $callbackUrl,
                    'http_status' => $httpStatus,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            $forwardOrder->update([
                'callback_status' => ForwardOrder::CALLBACK_FAILED,
                'callback_response' => ['error' => $e->getMessage()],
            ]);

            Log::channel('push_failure')->warning('推送完整订单数据到来源系统异常（验证码阶段）', [
                'forward_order_id' => $forwardOrder->id,
                'order_no' => $forwardOrder->source_order_no,
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 业务链路校验（已移至 ProcessOrderJob）
     * @deprecated
     */

    // ---- 转发流程订单辅助方法 ----

    /**
     * 从转发数据创建产品订单（状态：未付款）
     * 无论移动getCode步骤成功/失败，都同步创建产品订单，并记录转发日志
     */
    protected function createProductOrderFromForward($channel, array $data, bool $isTestEnv, int $forwardOrderId, array $forwardResult): void
    {
        $modelClass = $isTestEnv ? ProductOrderTest::class : ProductOrder::class;

        // 产品授权验证
        $product = $this->verifyProductForForward($channel, $data);
        if (! $product) {
            Log::warning('转发创建订单跳过：产品验证失败', [
                'pid' => $channel->pid,
                'order_no' => $data['order_no'] ?? '',
                'forward_order_id' => $forwardOrderId,
            ]);
            return;
        }

        // 构造转发日志
        $forwardSuccess = $forwardResult['success'] ?? false;
        $bResponse = $forwardResult['b_response'] ?? $forwardResult['result'] ?? null;
        $linkId = $forwardResult['step_data']['linkId'] ?? null;
        $errorMsg = $forwardResult['error'] ?? null;

        $logEntry = sprintf(
            "[%s] 发送验证码 - %s",
            now()->format('Y-m-d H:i:s'),
            $forwardSuccess ? '成功' : '失败'
        );

        if ($forwardSuccess) {
            if ($linkId) {
                $logEntry .= " (linkId: {$linkId})";
            } else {
                $logEntry .= " (未获取到linkId)";
            }
        } else {
            $logEntry .= " (原因: {$errorMsg})";
        }

        if ($bResponse) {
            $logEntry .= "\n移动反馈: " . json_encode($bResponse, JSON_UNESCAPED_UNICODE);
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        $price = $product->base_price;
        $totalAmount = $price * $quantity;

        try {
            $order = $modelClass::create(array_merge($data, [
                'pid' => $channel->pid,
                // 发送验证码失败也同步记录失败状态与原因，便于列表/导出展示
                'sync_status' => $forwardSuccess ? 0 : 2,
                'sync_error' => $forwardSuccess ? null : ($errorMsg ?? '发送验证码失败'),
                'pushed_at' => $forwardSuccess ? now() : null,
                'order_time' => $data['order_time'] ?? now(),
                'price' => $price,
                'total_amount' => $totalAmount,
                'internal_amount' => $totalAmount,
                'product_id' => $product->product_id,
                'business_id' => $product->business_id,
                'channel_id' => $product->channel_id,
                'organization_id' => $channel->organization_id,
                'order_status' => 0, // 未付款
                'forward_log' => $logEntry,
                'link_id' => $linkId,
            ]));

            // 触发事件
            event(new OrderCreated($order, $channel, $isTestEnv));

            Log::info('转发流程创建订单成功（未付款）', [
                'order_no' => $order->order_no,
                'forward_order_id' => $forwardOrderId,
                'pid' => $channel->pid,
                'forward_log' => $logEntry,
            ]);
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '2601', '2627'])) {
                Log::warning('转发创建订单跳过：重复订单号', [
                    'order_no' => $data['order_no'] ?? '',
                    'forward_order_id' => $forwardOrderId,
                ]);
                return;
            }
            throw $e;
        }
    }

    /**
     * 产品授权验证
     * 验证 bus_code 和 sku_code 是否属于该渠道且产品状态为启用
     * 同时用于 store() 同步验证和转发流程创建订单
     */
    protected function verifyProductForForward($channel, array $data)
    {
        $cacheKey = "forward_auth:{$channel->id}:{$data['sku_code']}:{$data['bus_code']}";

        return Cache::remember($cacheKey, 3600, function () use ($channel, $data) {
            return DB::table('channel_products')
                ->join('products', 'channel_products.product_id', '=', 'products.id')
                ->join('business', 'products.business_id', '=', 'business.id')
                ->where('channel_products.channel_id', $channel->id)
                ->where('products.sku_code', $data['sku_code'])
                ->where('products.status', 1)
                ->where('business.code', $data['bus_code'])
                ->first(['channel_id', 'product_id', 'business_id', 'base_price']);
        });
    }

    /**
     * 移动反馈成功后，将产品订单状态修改为"首次订购"（order_status=1）
     */
    protected function updateProductOrderStatusToFirstOrder(ForwardOrder $forwardOrder, $channel, bool $isTestEnv, array $forwardResult = []): void
    {
        $modelClass = $isTestEnv ? ProductOrderTest::class : ProductOrder::class;

        $order = $modelClass::where('order_no', $forwardOrder->source_order_no)
            ->where('pid', $forwardOrder->source_pid)
            ->first();

        if (! $order) {
            Log::warning('转发更新订单状态失败：未找到订单', [
                'order_no' => $forwardOrder->source_order_no,
                'forward_order_id' => $forwardOrder->id,
            ]);
            return;
        }

        $oldStatus = $order->order_status;

        // 构建提交日志
        $forwardSuccess = $forwardResult['success'] ?? true;
        $bResponse = $forwardResult['b_response'] ?? $forwardResult['result'] ?? null;
        $errorMsg = $forwardResult['error'] ?? null;

        $submitLog = sprintf(
            "\n[%s] 提交订单 - %s",
            now()->format('Y-m-d H:i:s'),
            $forwardSuccess ? '成功' : '失败'
        );
        if (! $forwardSuccess && $errorMsg) {
            $submitLog .= " (原因: {$errorMsg})";
        }
        if ($bResponse) {
            $submitLog .= "\n移动反馈: " . json_encode($bResponse, JSON_UNESCAPED_UNICODE);
        }

        // 5分钟内防重复：如果订单状态已非“未付款”且最近5分钟内被更新过，只追加日志，不修改状态
        $shouldSkipStatusUpdate = $oldStatus != 0
            && $order->updated_at
            && $order->updated_at->diffInMinutes(now()) < 5;

        if ($shouldSkipStatusUpdate) {
            Log::info('转发流程跳过状态更新（5分钟内已处理），仅追加日志', [
                'order_no' => $order->order_no,
                'forward_order_id' => $forwardOrder->id,
                'current_status' => $oldStatus,
                'updated_at' => $order->updated_at->toDateTimeString(),
            ]);

            // 原子追加日志，不覆盖并发写入
            $this->atomicAppendForwardLog($order, $submitLog);
            return;
        }

        // 记录验证码和提交时间
        $stepData = $forwardOrder->step_data ?? [];
        $code = ! empty($stepData['smsCode']) ? $stepData['smsCode'] : $order->code;
        $smsTime = now()->format('Y-m-d H:i:s');

        // 原子更新：同时修改状态和追加日志，避免并发覆盖
        DB::update("UPDATE {$order->getTable()} SET order_status = 1, code = ?, sms_time = ?, forward_log = CONCAT(ISNULL(forward_log, ''), ?), updated_at = ? WHERE id = ?", [
            $code,
            $smsTime,
            $submitLog,
            now(),
            $order->id,
        ]);

        // 记录状态变更事件
        $statusTable = $isTestEnv ? 'product_order_status_tests' : 'product_order_statuses';
        $statusData = [
            'table' => $statusTable,
            'order_id' => $order->id,
            'pid' => $forwardOrder->source_pid,
            'order_no' => $order->order_no,
            'old_status' => $oldStatus,
            'new_status' => 1,
            'price' => $order->price,
            'total_amount' => $order->total_amount,
            'operator' => 'FORWARD_VERIFY',
            'created_at' => now(),
        ];

        event(new OrderStatusUpdated(
            $order, $channel, $isTestEnv, $oldStatus, 1, $statusData
        ));

        Log::info('转发流程更新订单状态成功（首次订购）', [
            'order_no' => $order->order_no,
            'forward_order_id' => $forwardOrder->id,
            'old_status' => $oldStatus,
            'new_status' => 1,
            'code' => $code,
            'sms_time' => $smsTime,
        ]);
    }

    /**
     * 验证码提交（submit）失败时，更新产品订单的 forward_log 记录失败信息
     * 无论成功失败，都写入验证码和提交时间
     */
    protected function appendForwardLogOnFailure(ForwardOrder $forwardOrder, $channel, bool $isTestEnv, array $forwardResult): void
    {
        $modelClass = $isTestEnv ? ProductOrderTest::class : ProductOrder::class;

        $order = $modelClass::where('order_no', $forwardOrder->source_order_no)
            ->where('pid', $forwardOrder->source_pid)
            ->first();

        if (! $order) {
            Log::warning('转发提交失败记录日志跳过：未找到订单', [
                'order_no' => $forwardOrder->source_order_no,
                'forward_order_id' => $forwardOrder->id,
            ]);
            return;
        }

        $errorMsg = $forwardResult['error'] ?? '未知错误';
        $bResponse = $forwardResult['b_response'] ?? $forwardResult['result'] ?? null;

        $logEntry = sprintf(
            "\n[%s] 提交订单 - 失败 (原因: %s)",
            now()->format('Y-m-d H:i:s'),
            $errorMsg
        );

        if ($bResponse) {
            $logEntry .= "\n移动反馈: " . json_encode($bResponse, JSON_UNESCAPED_UNICODE);
        }

        // 原子追加日志，避免并发覆盖
        $this->atomicAppendForwardLog($order, $logEntry);

        // 无论成功失败，都写入验证码和提交时间
        $stepData = $forwardOrder->step_data ?? [];

        $update = [
            'sync_status' => 2,
            'sync_error' => $errorMsg,
            'updated_at' => now(),
        ];
        if (! empty($stepData['smsCode'])) {
            $update['code'] = $stepData['smsCode'];
            $update['sms_time'] = now()->format('Y-m-d H:i:s');
        }

        DB::table($order->getTable())
            ->where('id', $order->id)
            ->update($update);

        Log::info('转发提交失败，已记录日志和验证码到产品订单', [
            'order_no' => $order->order_no,
            'forward_order_id' => $forwardOrder->id,
            'forward_log' => $logEntry,
            'code' => $stepData['smsCode'] ?? $order->code,
            'sms_time' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 原子追加 forward_log，使用 SQL CONCAT 拼接避免并发覆盖
     */
    protected function atomicAppendForwardLog($order, string $logEntry): void
    {
        DB::update("UPDATE {$order->getTable()} SET forward_log = CONCAT(ISNULL(forward_log, ''), ?), updated_at = ? WHERE id = ?", [
            $logEntry,
            now(),
            $order->id,
        ]);
    }

    /**
     * 停止条件拦截的业务码：T000X，X 由 condition_id 末尾序号 +1（从 0001 开始）
     * 例如 condition_id = cond_1789441937816_0 → code = T0001
     */
    protected function stopErrorCode(string $conditionId): string
    {
        $parts = explode('_', $conditionId);
        $tail = end($parts);
        $x = (is_string($tail) && is_numeric($tail)) ? (int) $tail : 0;

        return 'T000'.($x + 1);
    }
}
