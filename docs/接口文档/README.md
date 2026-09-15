# 接口文档

本目录存放接口契约说明。当前为导入基线，以下为接口概览（依据 `backend/routes/` 整理），详细契约（请求 / 响应结构、错误码）见 `规范/010-接口契约规范.md` 与各接口实现。

## 接口分组

### v1 管理后台接口（`/api/v1/**`）

- **公开**：注册、登录、地区列表。
- **受保护**（`auth:sanctum` + `data.permission`）：
  - 基础数据：运营商 `carriers`、业务 `businesses`、业务产品 `products` / `productsProvince`、字典 `dictionaries`。
  - 组织与人员：`organizations`（含树 / 银行）、`company`、`departments`、`positions`、`employees`（离职 / 复职）、`employee-assignments`。
  - 渠道与订单：`channels`（含渠道下产品配置）、`quanyu-orders`（列表 / 待同步 / 重试 / 批量重试 / 取消 / 退订）、`forward-orders`、`callback-logs`、`push-stats/overview`、`product-orders`（导出 / 状态统计 / 批量推送）、`trace/{traceId}`。
  - 合同：`contract/master-contracts`（增删改查 + 提交 / 撤回 / 审核 / 废止 / 到期 / 条款）、`contract/audit/{masterId}`、`contract/contracts/split`、`contract/contract-execution`（含审核流转）。
  - 报表：`reports/product-order-*`（简报 / 图表 / 饼图 / 今日数据 / 业务统计）。
  - 系统：`users`、`changePassword`、`logout`、`failed-jobs`（重试 / 批量重试 / 删除 / 清空）。

### v2 第三方渠道接口（`/api/v2/**`）

- 鉴权：渠道签名（`channel.auth`）+ 限流（`throttle`），请求日志（`log.api`）。
- 订单接收：`POST/GET third-channel`（新增）、`PUT third-channel/{order_no}`（修改）、`POST third-channel/verify-submit`（验证码提交）。
- 状态查询：`GET third-channel-order-status/{order_id}`。
- 转发查询：`GET third-channel/query-forward`。
- 回调：`POST third-channel/callback/{pid}`、`GET|POST third-channel/callback-receive/{callback_type}`。
- 测试端点：`third-channel-test/*`（对应正式接口的测试环境版本）。

## 约定

- 统一响应结构与错误码遵循 `规范/010-接口契约规范.md`。
- 时间对外传输统一 ISO 8601。
- 详细字段级契约按模块补充到本目录（建议每模块一个文档）。
