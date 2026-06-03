# TopGun 项目 Code Wiki

## 1. 项目概述

TopGun 是一个基于 **Laravel 12** 后端 + **Vue3 + Quasar** 前端的企业级管理系统，主要功能包括产品订单管理、合同工作流、组织人事管理及第三方渠道集成。

### 1.1 技术栈

| 分类 | 技术 | 版本 |
|------|------|------|
| 后端框架 | Laravel | 12.x |
| 前端框架 | Vue | 3.x |
| 前端UI | Quasar | 2.x |
| 数据库 | SQLite（开发）/ SQL Server（生产） | - |
| 认证 | Laravel Sanctum | 4.x |
| 权限 | spatie/laravel-permission | 6.x |
| 审计日志 | owen-it/laravel-auditing | 14.x |
| 图表 | ApexCharts | 5.x |

### 1.2 项目架构

```
├── backend/                    # Laravel 后端
│   ├── app/
│   │   ├── Http/Controllers/   # REST API 控制器
│   │   ├── Models/             # 数据模型
│   │   ├── Services/           # 业务逻辑层
│   │   ├── Policies/           # 权限策略
│   │   └── Traits/             # 可复用特性
│   ├── routes/                 # 路由定义
│   └── database/               # 数据库迁移与种子
├── frontend/                   # Vue3 + Quasar 前端
│   ├── src/
│   │   ├── pages/              # 页面组件
│   │   ├── components/         # 可复用组件
│   │   ├── api/                # API 封装
│   │   └── router/             # 路由配置
└── document/                   # 项目文档
```

---

## 2. 核心模块

### 2.1 产品订单模块

**职责**：管理产品订单的创建、查询、统计和第三方同步。

**关键文件**：

| 文件 | 路径 | 说明 |
|------|------|------|
| 基础订单模型 | `app/Models/Base/BaseProductOrder.php` | 订单核心字段、统计方法 |
| 订单模型 | `app/Models/ProductOrder.php` | 生产环境订单 |
| 测试订单模型 | `app/Models/ProductOrderTest.php` | 测试环境订单 |
| 订单控制器 | `app/Http/Controllers/API/ProductOrderController.php` | REST API |
| 报表服务 | `app/Services/Reports/ProductOrderReportService.php` | 统计报表 |

**订单状态机**：

| 状态码 | 状态名称 | 说明 |
|--------|----------|------|
| 0 | 待处理 | 订单创建但未处理 |
| 1 | 已完成 | 订单处理完成 |
| 2 | 已取消 | 用户取消订单 |

**核心方法**（`BaseProductOrder`）：

```php
// 获取订单简报（今日/昨日/本周/上周/本月/上月/总计）
getProductOrderBriefing(array $filters = []): array

// 获取近30天销售趋势数据（折线图）
getSalesMonthCharts(): array

// 获取近7天产品销售分布（饼图）
getSalesPieCharts(): array

// 获取今日销售数据及同比变化
getSalesTodayData(): array
```

### 2.2 合同管理模块

**职责**：框架合同与执行合同的全生命周期管理，包含完整的审批工作流。

**关键文件**：

| 文件 | 路径 | 说明 |
|------|------|------|
| 框架合同模型 | `app/Models/contract/MasterContract.php` | 框架合同数据模型 |
| 执行合同模型 | `app/Models/contract/ExecutionContract.php` | 拆分后的执行合同 |
| 合同状态机 | `app/Services/Contract/MasterContractStateMachine.php` | 状态转换逻辑 |
| 框架合同控制器 | `app/Http/Controllers/Contract/MasterContractController.php` | 合同CRUD与流程 |
| 执行合同控制器 | `app/Http/Controllers/Contract/ExecutionContractController.php` | 执行合同管理 |

**框架合同状态流转**：

```
草稿(0) → 审批中(1) → 已生效(3)
         ↓           ↓
       驳回(2)     已终止(5)
         ↓
       作废(6)
```

**状态转换规则**（`MasterContractStateMachine`）：

| 当前状态 | 允许转换到 | 允许动作 |
|----------|------------|----------|
| 草稿(0) | 审批中、作废 | edit, submit, void |
| 审批中(1) | 草稿、驳回、已生效 | withdraw, approve, reject |
| 驳回(2) | 审批中、作废 | edit, submit, void |
| 已生效(3) | 已终止、已过期 | add_clause, terminate |
| 已过期(4) | 已终止 | - |
| 已终止(5) | - | - |
| 作废(6) | - | - |

**控制器方法**：

| 方法 | 路径 | 说明 |
|------|------|------|
| `index` | GET `/contract/master-contracts` | 合同列表（支持关键词、状态筛选） |
| `show` | GET `/contract/master-contracts/{id}` | 合同详情（含关联数据） |
| `store` | POST `/contract/master-contracts` | 创建合同（初始状态为草稿） |
| `update` | PUT `/contract/master-contracts/{id}` | 更新合同（生效后仅允许修改辅助字段） |
| `submit` | POST `/contract/master-contracts/{id}/submit` | 提交审批 |
| `withdraw` | POST `/contract/master-contracts/{id}/withdraw` | 撤回审批 |
| `approve` | POST `/contract/master-contracts/{id}/approve` | 通过审批 |
| `reject` | POST `/contract/master-contracts/{id}/reject` | 驳回审批 |
| `void` | POST `/contract/master-contracts/{id}/void` | 作废合同 |
| `terminate` | POST `/contract/master-contracts/{id}/terminate` | 终止合同 |
| `splitToExecution` | POST `/contract/contracts/split` | 拆分为执行合同 |

### 2.3 组织架构模块

**职责**：管理企业组织架构、部门、岗位和员工信息。

**关键文件**：

| 文件 | 路径 | 说明 |
|------|------|------|
| 组织模型 | `app/Models/Organization.php` | 组织信息 |
| 部门模型 | `app/Models/Department.php` | 部门信息 |
| 岗位模型 | `app/Models/Position.php` | 岗位信息 |
| 员工模型 | `app/Models/Employee.php` | 员工信息 |
| 员工任职模型 | `app/Models/EmployeeTransfer.php` | 任职记录 |
| 组织控制器 | `app/Http/Controllers/OrganizationController.php` | 组织管理API |

**关联关系**：

```
Organization (组织)
    ↓ hasMany
Department (部门)
    ↓ hasMany
Position (岗位)
    ↓ belongsToMany
Employee (员工)
    ↓ hasMany
EmployeeTransfer (任职记录)
```

### 2.4 第三方渠道模块

**职责**：对接第三方渠道（如鑫全域），处理订单推送和同步。

**关键文件**：

| 文件 | 路径 | 说明 |
|------|------|------|
| 渠道模型 | `app/Models/ThirdChannels.php` | 渠道配置 |
| 全域订单模型 | `app/Models/QuanyuOrder.php` | 鑫全域订单 |
| 鑫全域服务 | `app/Services/ThirdChannel/QuanyuService.php` | 推送逻辑 |
| 渠道工厂 | `app/Services/ThirdChannel/ChannelFactory.php` | 渠道服务工厂 |
| 接收控制器 | `app/Http/Controllers/API/ThirdChannel/ReceiverController.php` | 接收第三方订单 |

**推送流程**：

```
第三方订单 → ReceiverController → QuanyuService → 天轩平台
    ↓                              ↓
  验证签名                      生成签名
    ↓                              ↓
  保存订单                      HTTP请求
    ↓                              ↓
  异步任务                      更新同步状态
```

**签名算法**（`QuanyuService::generateSign`）：

1. 按字典序排序参数名（ksort）
2. 拼接为 `key=value&` 格式
3. 末尾拼接 `key=密钥`
4. MD5 加密后转大写

### 2.5 报表统计模块

**职责**：提供产品订单的统计报表和可视化图表数据。

**关键文件**：

| 文件 | 路径 | 说明 |
|------|------|------|
| 报表控制器 | `app/Http/Controllers/API/Reports/ProductOrderReportController.php` | 报表API |
| 报表服务 | `app/Services/Reports/ProductOrderReportService.php` | 报表逻辑 |

**报表API**：

| 接口 | 路径 | 说明 |
|------|------|------|
| 订单简报 | GET `/reports/product-order-briefing` | 今日/昨日/周/月统计 |
| 销售趋势图 | GET `/reports/product-order-chart` | 近30天折线图数据 |
| 销售饼图 | GET `/reports/product-order-sales-pie-chart` | 产品销售分布 |
| 今日数据 | GET `/reports/product-order-sales-today-data` | 今日数据及同比 |
| 业务统计 | GET `/reports/product-order-business-stats` | 业务维度统计 |

---

## 3. API 规范

### 3.1 统一响应格式

所有 API 响应遵循统一格式（`App\Traits\ApiResponse`）：

```json
{
  "code": 200,
  "status": "success",
  "message": "操作成功",
  "data": {}
}
```

| code | status | 场景 | 说明 |
|------|--------|------|------|
| 200 | success | 完全成功 | 查询/更新/删除成功 |
| 206 | partial | 部分成功 | 批量操作部分失败 |
| 400+ | error | 失败 | 业务错误或参数校验失败 |

### 3.2 路由结构

```php
// 公开接口（无需认证）
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// 管理后台接口（Sanctum认证）
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // 资源路由
    Route::apiResource('carriers', CarrierController::class);
    Route::apiResource('businesses', BusinessController::class);
    Route::apiResource('products', ProductController::class);
    
    // 合同管理
    Route::prefix("contract")->group(function(){
        Route::get('master-contracts', [MasterContractController::class, 'index']);
        Route::post('master-contracts/{contract}/submit', [MasterContractController::class, 'submit']);
        // ... 其他合同操作
    });
});

// 第三方渠道接口（独立签名验证）
Route::prefix('v2')->group(function () {
    Route::post('third-channel', [ReceiverController::class, 'store'])
        ->middleware('channel.auth');
});
```

### 3.3 前端 API 封装

前端在 `src/api/` 目录下封装 API 调用：

```javascript
// src/api/masterContract.js
export const masterContractApi = {
  list: (params) => api.get(v + '/contract/master-contracts', { params }),
  show: (id) => api.get(v + `/contract/master-contracts/${id}`),
  store: (data) => api.post(v + '/contract/master-contracts', data),
  update: (id, data) => api.put(v + `/contract/master-contracts/${id}`, data),
  submit: (id) => api.post(v + `/contract/master-contracts/${id}/submit`),
  approve: (id, data) => api.post(v + `/contract/master-contracts/${id}/approve`, data),
  // ...
};
```

---

## 4. 关键类与函数

### 4.1 Traits

#### ApiResponse

**路径**：`app/Traits/apiResponse.php`

**方法**：

| 方法 | 说明 | 返回结构 |
|------|------|----------|
| `success($data, $message)` | 成功响应 | `{code:200, status:"success", ...}` |
| `partial($successData, $failData, $message)` | 部分成功 | `{code:206, status:"partial", ...}` |
| `error($message, $code, $data)` | 失败响应 | `{code:400+, status:"error", ...}` |

### 4.2 基础控制器

#### BaseResourceController

**路径**：`app/Http/Controllers/Base/BaseResourceController.php`

**用途**：提供标准 CRUD 操作的基类，子类只需定义 `$modelClass` 和验证器类名。

**方法**：

| 方法 | 说明 |
|------|------|
| `index(Request $request)` | 列表查询（支持分页） |
| `show($id)` | 获取详情 |
| `store(Request $request)` | 创建资源 |
| `update(Request $request, $id)` | 更新资源 |
| `destroy($id)` | 删除资源（含异常捕获） |

**钩子函数**（可被子类重写）：

| 方法 | 说明 |
|------|------|
| `applyFilters($query, $request)` | 自定义查询条件 |
| `afterStore($item)` | 创建后钩子 |

### 4.3 状态机服务

#### MasterContractStateMachine

**路径**：`app/Services/Contract/MasterContractStateMachine.php`

**核心方法**：

| 方法 | 说明 | 返回值 |
|------|------|--------|
| `isActionAllowedByState($action)` | 检查动作是否允许 | bool |
| `canTransitionTo($targetStatus)` | 检查状态转换是否允许 | bool |
| `transitionTo($targetStatus)` | 执行状态转换 | int (新状态) |
| `submit()` | 提交审批 | STATUS_PENDING |
| `withdraw()` | 撤回审批 | STATUS_DRAFT |
| `approve()` | 通过审批 | STATUS_ACTIVE |
| `reject()` | 驳回审批 | STATUS_REJECTED |
| `void()` | 作废合同 | STATUS_VOID |
| `terminate()` | 终止合同 | STATUS_TERMINATED |

---

## 5. 数据库设计

### 5.1 核心表结构

#### master_contracts（框架合同）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | int | 主键 |
| `contract_no` | varchar | 合同编号 |
| `title` | varchar | 合同名称 |
| `org_a_id` | int | 甲方组织ID |
| `org_b_id` | int | 乙方组织ID |
| `total_limit` | decimal | 合同总限额 |
| `version` | int | 版本号 |
| `status` | int | 状态（0-6） |
| `signed_date` | date | 签订日期 |
| `effective_date` | date | 生效日期 |
| `expiry_date` | date | 到期日期 |
| `signer_a` | varchar | 甲方签字人 |
| `signer_b` | varchar | 乙方签字人 |
| `summary` | text | 合同摘要 |
| `remarks` | text | 备注 |

#### execution_contracts（执行合同）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | int | 主键 |
| `master_id` | int | 关联框架合同ID |
| `system_no` | varchar | 系统编号 |
| `external_no` | varchar | 外部编号 |
| `title` | varchar | 合同名称 |
| `type` | varchar | 类型（REVENUE/COST） |
| `total_amount` | decimal | 金额 |
| `status` | int | 状态 |

#### product_orders（产品订单）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | int | 主键 |
| `order_no` | varchar | 订单号 |
| `pid` | varchar | 渠道PID |
| `bus_code` | varchar | 业务编码 |
| `sku_code` | varchar | 产品编码 |
| `user_phone` | varchar | 用户手机号 |
| `order_time` | datetime | 下单时间 |
| `order_status` | int | 订单状态 |
| `price` | decimal | 单价 |
| `quantity` | int | 数量 |
| `total_amount` | decimal | 总金额 |
| `sync_status` | int | 同步状态 |
| `channel_id` | int | 渠道ID |
| `product_id` | int | 产品ID |
| `business_id` | int | 业务ID |

### 5.2 关联关系图

```
ThirdChannels (渠道)
    ↓ 1:N
ProductOrder (订单)
    ↓ N:1
Products (产品)
    ↓ N:1
Business (业务)

MasterContract (框架合同)
    ↓ 1:N
ExecutionContract (执行合同)
    ↓ N:1
Organization (组织)

Organization
    ↓ 1:N
Department
    ↓ 1:N
Position
    ↓ N:N
Employee
```

---

## 6. 安全与权限

### 6.1 认证机制

- **管理后台**：使用 Laravel Sanctum 进行 Token 认证
- **第三方接口**：使用独立签名验证（`VerifyChannelSignature` 中间件）

### 6.2 权限控制

使用 `spatie/laravel-permission` 实现 RBAC：

```php
// 定义权限
Permission::create(['name' => 'view master contracts']);
Permission::create(['name' => 'edit master contracts']);
Permission::create(['name' => 'approve master contracts']);

// 分配角色
$role = Role::create(['name' => 'contract_manager']);
$role->givePermissionTo(['view master contracts', 'edit master contracts']);
```

### 6.3 策略授权

控制器中使用 `AuthorizesRequests` trait 进行细粒度授权：

```php
public function approve(MasterContract $contract)
{
    $this->authorize('approve', $contract); // 调用 Policy
    // ...
}
```

---

## 7. 项目运行

### 7.1 后端启动

```bash
cd backend

# 安装依赖
composer install

# 复制环境配置
cp .env.example .env

# 生成应用密钥
php artisan key:generate

# 数据库迁移
php artisan migrate

# 运行开发服务器
php artisan serve

# 启动队列（异步任务）
php artisan queue:listen
```

### 7.2 前端启动

```bash
cd frontend

# 安装依赖
npm install

# 开发模式
npm run dev

# 生产构建
npm run build
```

### 7.3 测试

```bash
# 运行单元测试
php artisan test

# 代码风格检查
./vendor/bin/pint
```

---

## 8. 开发规范

### 8.1 命名约定

| 类型 | 规则 | 示例 |
|------|------|------|
| 表名 | 复数 snake_case | `master_contracts` |
| 列名 | snake_case | `contract_no`, `org_a_id` |
| Model类 | 单数 PascalCase | `MasterContract` |
| Controller | PascalCase + Controller | `MasterContractController` |
| FormRequest | 动作 + 资源 + Request | `StoreMasterContractRequest` |
| Service | 域名 + 职责 + Service | `MasterContractStateMachine` |

### 8.2 代码分层

```
routes/api.php → Controller → FormRequest → Service → Model → DB
                     ↓
                  Policy / Enum / Job
```

**各层职责**：

| 层级 | 职责 |
|------|------|
| Controller | 参数接收、授权验证、调用Service、返回响应 |
| FormRequest | 请求参数验证 |
| Service | 业务逻辑、状态机、跨表事务 |
| Model | 数据结构、关联关系、访问器/修改器 |
| Policy | 权限判断逻辑 |
| Job | 异步任务处理 |

### 8.3 最佳实践

1. **使用状态机**：复杂状态流转使用 State Machine 模式
2. **事务处理**：多表操作使用 `DB::transaction`
3. **软删除**：使用 `SoftDeletes` trait，避免物理删除
4. **审计日志**：使用 `laravel-auditing` 自动记录变更
5. **API版本**：路由使用 `v1`、`v2` 前缀区分版本

---

## 9. 目录结构速查

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── API/                 # 通用API控制器
│   │   │   ├── Base/                # 基础控制器
│   │   │   ├── Company/             # 公司管理
│   │   │   ├── Contract/            # 合同管理
│   │   │   └── ThirdChannel/        # 第三方渠道
│   │   ├── Middleware/              # 中间件
│   │   └── Requests/                # 表单验证
│   ├── Models/
│   │   ├── Base/                    # 基础模型
│   │   └── contract/                # 合同模型
│   ├── Services/
│   │   ├── Contract/                # 合同服务
│   │   ├── Reports/                 # 报表服务
│   │   └── ThirdChannel/            # 渠道服务
│   ├── Policies/                    # 权限策略
│   ├── Traits/                      # 特性
│   └── Providers/                   # 服务提供者
├── routes/
│   ├── api.php                      # API路由
│   └── web.php                      # Web路由
├── database/
│   ├── migrations/                  # 数据库迁移
│   └── seeders/                     # 数据种子
└── config/                          # 配置文件

frontend/
├── src/
│   ├── pages/                       # 页面组件
│   ├── components/                  # 通用组件
│   ├── api/                         # API封装
│   ├── router/                      # 路由配置
│   ├── stores/                      # Pinia状态管理
│   └── layouts/                     # 布局组件
└── public/                          # 静态资源
```

---

## 10. 核心配置文件

| 文件 | 用途 |
|------|------|
| `backend/.env` | 环境变量配置 |
| `backend/config/auth.php` | 认证配置 |
| `backend/config/sanctum.php` | Sanctum配置 |
| `backend/config/permission.php` | 权限配置 |
| `frontend/quasar.config.js` | Quasar配置 |
| `frontend/src/router/routes.js` | 前端路由 |

---

**文档版本**：v1.0  
**生成日期**：2026年6月  
**适用项目**：TopGun 管理系统