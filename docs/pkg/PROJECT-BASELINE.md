# PROJECT-BASELINE（现状基线）

> 本文件是 topgun 项目的「项目考古」结论基线，依据 `PKG_Technical_Director_Execution_Spec.md` §40 建立。
> 记录真实系统的技术栈、模块、数据库、API、核心业务、依赖、技术债、已知问题、风险与文档冲突。
> 所有结论以「已验证代码 / 真实数据库 schema」为准，不依赖 AI 记忆。

基线日期：2026-09-02

***

## 1. 项目定位

- **名称**：唐贝管理系统（topgun）

- **形态**：运营商/业务/渠道订单与合同管理后台

- **覆盖**：运营商与业务产品配置、第三方渠道订单接入与推送、数据中转与回调日志、产品订单全链路追踪与报表、组织架构（公司/部门/岗位/员工）、框架合同与执行合同审核流转。

## 2. 技术栈

| 层   | 技术                                  | 说明                                 |
| --- | ----------------------------------- | ---------------------------------- |
| 后端  | Laravel 12 · Sanctum · SQL Server   | `backend/`，REST API + 队列 + 定时任务    |
| 前端  | Quasar2 · Vue3 · Pinia · ApexCharts | `frontend/`，管理后台 SPA               |
| 数据库 | SQL Server                          | 库名 `topgun`，`DB_CONNECTION=sqlsrv` |

## 3. 接口层（双重面）

| 面     | 前缀           | 鉴权                                         |
| ----- | ------------ | ------------------------------------------ |
| 管理后台  | `/api/v1/**` | Sanctum 令牌 + data.permission               |
| 第三方渠道 | `/api/v2/**` | 渠道签名（VerifyChannelSignature）+ 限流 + log.api |

## 4. 模块地图（9 模块）

| 模块      | 职责               | 关键表                                                                                 |
| ------- | ---------------- | ----------------------------------------------------------------------------------- |
| 认证与系统   | 登录/用户/权限/失败任务/通知 | users, permission\_tables, failed\_jobs                                             |
| 组织人事    | 组织/部门/岗位/员工/地区   | organizations, departments, positions, employees, areas                             |
| 基础数据    | 运营商/业务/产品/渠道产品   | carriers, business, products, channel\_products, dictionaries                       |
| 渠道管理    | 第三方渠道配置          | third\_channels                                                                     |
| 订单接入与转发 | 接收/中转/回调/追踪      | product\_orders, forward\_orders, callback\_logs                                    |
| 合同      | 框架/执行合同          | master\_contracts, execution\_contracts                                             |
| 项目管理    | 项目/投标/分包/结算      | projects, project\_bids, project\_subcontracts, project\_settlements                |
| 财务      | 结算/应收应付/发票/薪酬/税务 | settlements, project\_receivables/payables, invoices, salary\_\*, tax\_declarations |
| 报表      | 订单/推送统计          | （读 product\_orders + forward/stat）                                                  |

## 5. 核心业务链路（订单接入与转发）

```text
A(供应商) ──签名鉴权──> ReceiverController.store(v2)
                          ├─ processOrderJob(queue=high) 异步写库
                          └─ dispatchForwardIfNeeded（同步）
                               ├─ ChannelConfigLoader.getForwardTargetPid（auto_forward+forward_target_pid > ext_config.target_pid）
                               ├─ 创建 ForwardOrder（STATUS_PENDING）
                               └─ ForwardService.executeStep(0=getCode) → 命中 push_steps + request_mapping + 签名 → HTTP 推送目标
                                     └─ 成功判定：步骤 success_rule > 渠道 push_success_rule > 默认 data.code=='00000'
   verifySubmit(v2) ──> ForwardService.executeStep(1=submit) → STATUS_SUCCESS/Failed → 回调 dispatchCallback
```

关键事实：

- 多步推送统一走 `forward_orders`（ForwardOrder + ForwardService）。

- `PushService` 提供单步推送的统一 HTTP + 判定。

- 订单业务字段正本在 `product_orders`；中转编排在 `forward_orders`。

- `products` 无 `business_code` 列，正确关联是 `products.business_id → business.code`（订单校验 bus\_code 走 business.code）。

## 6. 关键状态机

- **产品订单 order\_status**：`0 未付款 → 1 首次订购`（验证码提交成功置 1；1 终态）。

- **中转 forward\_status**：`0 待处理 / 1 成功 / 2 失败 / 4 待验证`。

- **回调 callback\_status**：`0 待回调 / 1 成功 / 2 失败`。

- **渠道 role**：`supplier_a / channel_c / unset`（与 method 正交）。

- **渠道 method（能力位）**：`接收 / 发送 / 转发`。

## 7. 已知问题与技术债

| 编号       | 级别 | 问题                                               | 处置       |
| -------- | -- | ------------------------------------------------ | -------- |
| ERR-001  | 确定 | OrderCreated/OrderStatusUpdated 事件无监听器消费（监听器未注册） | 注册或删除监听器 |
| ERR-002  | 确定 | tianxuan\_orders 表不存在，Tianxuan\* 全栈死代码           | T-11 删除  |
| ERR-003  | 确定 | quanyu\_orders 写路径断裂，32,561 孤儿行 sync\_status=0   | 归档或删除    |
| WARN-001 | 可疑 | 执行路径公司字面量特例未清除                                   | T-10 清理  |
| WARN-002 | 可疑 | PushService 与 ForwardService 判定逻辑重复              | 待定收敛     |
| WARN-003 | 可疑 | API 契约 snake\_case/camelCase 命名一致性               | 扫描确认     |

## 8. 风险

- 事件链路静默失效：依赖 `OrderCreated/OrderStatusUpdated` 的审计/通知/状态变更在无监听器时 no-op，可能造成隐性业务缺口。

- 死代码与废弃表混杂：quanyu / tianxuan 残留可能诱导后续开发者误用。

- 自指向模式易被误判：`forward_target_pid == 自身 pid` 是合法模式，重审时不得当成错误修复。

## 9. 文档与代码冲突记录

- 早期基线文档（作废的 T-2/T-3/T-8）曾假设 `products.business_code` 存在——真实 schema 无此列（实为 `business.code`）。

- 曾将「HBGY 自指向」判为 malformed 并修复——已撤销（见 ADR-003）。

## 10. 开放决策项（待技术总监裁决）

1. quanyu\_orders 32,561 行孤儿数据：归档还是删除？
2. OrderProcessListener / AsyncOrderAuditListener：注册还是删除？
3. PushService 与 ForwardService 是否收敛统一判定组件？
4. 报表与前端对 quanyu 统计的依赖是否随 T-11 一并切换。

***

## 附：真实 schema 关键差异备忘

- `products` 关联业务用 `business_id`（非 business\_code）。

- `third_channels.role` 由 2026\_09\_01\_090000 迁移新增，默认 unset。

- `forward_orders` 多步骤字段（current\_step / step\_data / step\_responses）由 2026\_08\_10 增补。

- `tianxuan_orders` 表在真实库不存在。

