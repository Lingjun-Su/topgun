# 拓耕管理系统 V1.0

> 拓耕管理系统（TopGun）是一个基于 Laravel + Quasar 的全栈企业管理平台，覆盖手机增值业务订单管理、组织架构管理、合同管理、项目管理、财务结算等核心业务模块。

---

## 项目概览

| 项目 | 说明 |
|------|------|
| **产品名称** | 拓耕管理系统 V1.0 |
| **技术栈** | 后端：Laravel 12 + PHP 8.2 / 前端：Quasar 2 + Vue 3 + Vite |
| **数据库** | SQL Server 2019（主库）/ MySQL / SQLite（开发） |
| **认证方式** | Laravel Sanctum 令牌认证 |
| **部署地址** | `http://159.75.226.248:8080` |

---

## 目录结构

```
d:\TopGun/
├── backend/          # Laravel 后端项目
│   ├── app/
│   │   ├── Console/Commands/     # Artisan 命令行（定时任务）
│   │   ├── Enums/                # 枚举定义
│   │   ├── Events/               # 事件（OrderCreated）
│   │   ├── Exceptions/           # 异常处理
│   │   ├── Http/
│   │   │   ├── Controllers/      # 控制器
│   │   │   │   ├── API/          # API 控制器（业务、渠道、订单等）
│   │   │   │   │   ├── Reports/  # 报表控制器
│   │   │   │   │   └── ThirdChannel/ # 第三方渠道控制器
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── Login.php
│   │   │   │   └── Register.php
│   │   │   └── Kernel.php
│   │   ├── Jobs/                 # 队列任务（订单处理、推送、告警等）
│   │   ├── Models/               # Eloquent 模型
│   │   │   └── Base/             # 基础模型基类
│   │   ├── Services/             # 业务逻辑层
│   │   │   ├── Contract/         # 合同服务
│   │   │   ├── Reports/          # 报表服务
│   │   │   └── ThirdChannel/     # 第三方渠道服务
│   │   └── Traits/               # 公共 Trait
│   ├── config/                   # 配置文件
│   ├── database/                 # 数据库迁移与种子
│   ├── routes/                   # 路由定义
│   │   ├── api.php               # API 路由
│   │   ├── console.php           # 命令行路由
│   │   └── web.php               # Web 路由
│   └── composer.json
│
├── frontend/         # Quasar 前端项目
│   ├── src/
│   │   ├── api/                  # API 接口封装
│   │   ├── assets/               # 静态资源
│   │   ├── boot/                 # 启动文件（axios、auth-guard）
│   │   ├── components/           # 公共组件
│   │   ├── css/                  # 样式文件
│   │   ├── layouts/              # 布局组件
│   │   ├── pages/                # 页面组件
│   │   │   ├── quanyu/           # 鑫全域订单页面
│   │   │   ├── users/            # 用户管理页面
│   │   │   └── reports/          # 报表页面
│   │   ├── router/               # 路由配置
│   │   └── stores/               # Pinia 状态管理
│   └── package.json
│
├── document/         # 项目文档
├── assets/           # 编译后的前端资源
├── icons/            # 图标文件
└── ver.php           # 版本信息
```

---

## 业务模块

### 1. 手机增值业务管理

核心业务模块，处理手机增值服务（视频彩铃等）的订单全生命周期：

- **产品订单管理** — 订单列表、状态追踪、同步推送
- **运营商管理** — 维护运营商信息
- **业务管理** — 业务往来单位管理
- **产品管理** — 产品/物料 SKU 管理、省份定价
- **渠道接入管理** — 第三方渠道配置、产品关联
- **销量报表** — 订单销量统计与分析

### 2. 公司管理

- **组织架构** — 多级组织树管理（含银行账户）

### 3. 系统管理

- **用户管理** — 系统用户账号管理
- **接收测试** — 第三方订单接收测试工具

---

## 后端技术架构

### 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| PHP | ^8.2 | 运行环境 |
| Laravel Framework | ^12.0 | 核心框架 |
| Laravel Sanctum | ^4.0 | API 令牌认证 |
| Spatie Laravel Permission | ^6.24 | 角色权限管理 |
| OwenIt Laravel Auditing | ^14.0 | 数据审计日志 |
| Maatwebsite Excel | ^3.1 | Excel 导入导出 |

### 数据库模型关系

```
Organization (组织架构)
├── Department (部门)
│   └── Position (岗位)
├── Employee (员工)
│   ├── EmployeeTransfer (员工调动记录)
│   ├── SalaryDetail (薪资明细)
│   ├── SalaryPayment (薪资发放)
│   └── TaxDeclaration (个税报税)
├── OrganizationBank (银行账户)
├── Business (业务单位)
│   └── Products (产品/物料)
│       └── ProductProvince (产品省份定价)
├── Project (项目)
│   ├── ProjectBid (项目投标)
│   ├── ProjectSubcontract (项目分包)
│   ├── ProjectSettlement (项目结算)
│   │   └── SettlementDetail (结算明细)
│   │       ├── ProjectReceivable (收款记录)
│   │       ├── ProjectPayable (付款记录)
│   │       ├── Invoice (发票)
│   │       ├── SalaryDetail (薪资明细)
│   │       └── TaxDeclaration (个税报税)
├── MasterContract (框架合同)
│   └── ExecutionContract (执行合同)
├── ThirdChannels (第三方渠道)
│   ├── ChannelProducts (渠道产品关联)
│   ├── BusinessRule (业务规则)
│   └── ChannelApi (渠道 API 配置)
├── ProductOrder / ProductOrderTest (产品订单)
├── QuanyuOrder (鑫全域订单)
├── TianxuanOrder (天轩订单)
├── ForwardOrder (数据中转记录)
├── Dictionary (数据字典)
├── Areas (省市区镇)
└── User (系统用户)
```

### 订单处理流程

第三方渠道通过 API 提交订单 → 写入 ProductOrder（或 ProductOrderTest）→ 队列任务 OrderProcessJob 扫描待处理订单 → 按渠道配置分发推送（PushToXinquanyu 或通用渠道 ChannelFactory）→ 记录推送结果 → 失败时 AlertJob 告警通知

### 数据中转转发流程

源渠道（A公司）推送数据 → 接收并写入 ForwardOrder → ForwardOrderJob 按步骤执行推送（支持多步骤，如 getCode → submit）→ 签名校验 → 推送到目标渠道（移动）→ 结果回调通知源渠道

### 队列任务

| 任务 | 说明 | 调度方式 |
|------|------|----------|
| OrderProcessJob | 扫描待处理订单并推送 | 定时任务 |
| PushToXinquanyu | 推送订单到鑫全域 | 队列 |
| ForwardOrderJob | 数据中转多步骤推送 | 队列 |
| NotifyOriginJob | 中转完成后回调源渠道 | 队列 |
| ProcessOrderJob | 订单入库处理（含产品校验） | 队列 |
| AsyncOrderAuditJob | 异步写入审计日志 | 队列 |
| AlertJob | 失败订单告警通知 | 定时任务 |

### API 路由结构

**公开接口** (`/api/v1`)
- `POST /register` — 用户注册
- `POST /login` — 用户登录
- `GET /regions` — 地区数据

**认证接口** (`/api/v1`，需 Sanctum 令牌)
- `CRUD /carriers` — 运营商管理
- `CRUD /businesses` — 业务管理
- `CRUD /products` — 产品管理
- `CRUD /productsProvince` — 产品省份定价
- `CRUD /users` — 用户管理
- `CRUD /channels` — 渠道配置
- `CRUD /dictionaries` — 数据字典
- `CRUD /organizations` — 组织架构
- `CRUD /departments` — 部门管理
- `CRUD /positions` — 岗位管理
- `CRUD /employees` — 员工管理
- `CRUD /employee-assignments` — 员工任职记录
- `GET /product-orders` — 产品订单列表
- `GET /reports/product-order` — 产品订单报表

**第三方渠道接口** (`/api/v2`)
- `POST /third-channel` — 接收订单（正式）
- `PUT /third-channel/{order_no}` — 更新订单（正式）
- `POST /third-channel-test` — 接收订单（测试）
- `PUT /third-channel-test/{order_no}` — 更新订单（测试）

---

## 前端技术架构

### 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Vue 3 | ^3.5.22 | 前端框架 |
| Quasar 2 | ^2.16.0 | UI 组件库 |
| Pinia | ^3.0.1 | 状态管理 |
| Vue Router 4 | ^4.0.0 | 路由管理 |
| Axios | ^1.2.1 | HTTP 请求 |
| Quasar App Vite | ^2.1.0 | 构建工具 |
| xlsx | ^0.18.5 | Excel 处理 |
| crypto-js | ^4.2.0 | 加密工具 |

### 页面路由

| 路径 | 页面 | 说明 |
|------|------|------|
| `/` | IndexPage | 首页 |
| `/login` | LoginPage | 登录页 |
| `/product-order` | ProductOrderManagerPage | 产品订单管理 |
| `/Carrier` | CarrierManagerPage | 运营商管理 |
| `/Business` | BusinessPage | 业务管理 |
| `/OrderProduct` | ProductManagerPage | 产品管理 |
| `/ThirdChannel` | ThirdChannelManager | 渠道接入管理 |
| `/product-order-report` | ProductOrderReportPage | 产品订单报表 |
| `/organization` | OrganizationManagementPage | 组织架构管理 |
| `/userList` | UserManagerPage | 用户管理 |
| `/userInfo` | UserInfoPage | 用户信息 |
| `/dictionary` | DictionaryManager | 数据字典 |
| `/excelImport` | ExcelImportPage | Excel 导入 |
| `/receiverTest` | receiverTestPage | 接收测试 |
| `/QuanyuOrderList` | OrderListPage | 天轩订单列表 |
| `/QuanyuPushTest` | PushToPartnerTestPage | 天轩推送测试 |

### 前端布局

- 左侧抽屉导航菜单，分三级菜单组
- 顶部工具栏含通知、用户信息、修改密码、退出登录
- 标签页布局（TabsLayout），支持多标签页切换
- 首页支持标签页关闭，首页不可关闭

---

## 开发环境搭建

### 后端

```bash
cd backend
composer install
cp .env.example .env
# 编辑 .env 配置数据库连接
php artisan key:generate
php artisan migrate
php artisan serve
```

### 前端

```bash
cd frontend
npm install
quasar dev
```

### 完整开发环境

```bash
# 后端项目根目录下运行（同时启动 PHP 服务、队列监听、Vite 热更新）
composer dev
```

---

## 环境变量说明

关键 `.env` 配置项：

| 变量 | 说明 | 默认值 |
|------|------|--------|
| `APP_NAME` | 应用名称 | Laravel |
| `APP_ENV` | 运行环境 | production |
| `APP_DEBUG` | 调试模式 | false |
| `APP_URL` | 应用 URL | http://localhost |
| `DB_CONNECTION` | 数据库连接 | sqlite |
| `DB_HOST` | 数据库主机 | 127.0.0.1 |
| `DB_DATABASE` | 数据库名 | laravel |

前端 API 地址配置位于 `frontend/quasar.config.js` 的 `build.env.API_URL`：
- 开发模式：`http://127.0.0.1:8000/api/`
- 生产模式：`http://159.75.226.248:8080/api`

---

## 统一响应格式

所有 API 响应遵循统一格式：

```json
{
  "code": 200,
  "status": "success",
  "message": "操作成功",
  "data": {}
}
```

| Code | 状态 | 说明 |
|------|------|------|
| 200 | success | 完全成功 |
| 206 | partial | 部分成功（批量操作） |
| 400+ | error | 操作失败 |

---

## 数据审计

系统使用 `owen-it/laravel-auditing` 包实现数据审计，所有继承 `BaseModel` 的模型自动记录创建、更新、删除、恢复操作到 `audits` 表。

部分模型（如 `Carrier`、`User`）实现了自定义审计逻辑，手动记录操作人信息和字段变更。

---

## 第三方渠道签名机制

推送数据时支持签名校验：
- 算法支持：SHA256（默认）
- 签名头：`X-Sign`、`X-Sign-Algorithm`
- 渠道配置中的 `sign_key` 和 `sign_algorithm` 控制签名行为