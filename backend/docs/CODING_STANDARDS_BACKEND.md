# TopGun 后端编码规范

---

## 1. 技术栈约束

| 技术 | 版本 | 说明 |
|------|------|------|
| PHP | 8.2.x | 语言版本 |
| Laravel | 12.x | 后端框架 |
| Laravel Sanctum | 4.x | API 认证 |
| spatie/laravel-permission | 6.x | 权限管理 |
| owen-it/laravel-auditing | 14.x | 审计日志 |
| maatwebsite/excel | 3.1 | Excel 处理 |
| SQL Server | - | 数据库（生产） |
| SQLite | - | 数据库（开发） |

---

## 2. 编码风格规范

### 2.1 PHP 命名规范

| 类型 | 规则 | 示例 |
|------|------|------|
| 类名 | 单数 PascalCase | `MasterContract` |
| 方法名 | camelCase | `getProductOrderBriefing` |
| 属性名 | camelCase | `totalAmount` |
| 常量 | UPPER_SNAKE_CASE | `STATUS_DRAFT` |
| 文件名 | PascalCase | `MasterContractController.php` |
| 目录名 | lowercase | `contract/`, `api/` |

### 2.2 通用规则

| 规则 | 规范 |
|------|------|
| 编码 | UTF-8 |
| 换行符 | LF |
| 缩进 | 4 空格 |
| 最大行宽 | 100 字符 |
| 注释原则 | 解释"为什么"，不解释"做什么" |

---

## 3. 架构与模块划分规范

### 3.1 后端分层架构

```
┌─────────────────────────────────────────────────────┐
│  routes/api.php        # 路由层：请求路由映射          │
├─────────────────────────────────────────────────────┤
│  Controllers/          # 控制层：参数校验、授权、响应   │
├─────────────────────────────────────────────────────┤
│  Requests/             # 请求层：表单验证规则          │
├─────────────────────────────────────────────────────┤
│  Services/             # 服务层：业务逻辑、状态机      │
├─────────────────────────────────────────────────────┤
│  Models/               # 模型层：数据结构、关联关系     │
├─────────────────────────────────────────────────────┤
│  Policies/             # 策略层：权限决策逻辑          │
├─────────────────────────────────────────────────────┤
│  Database              # 数据层：迁移、种子、查询       │
└─────────────────────────────────────────────────────┘
```

### 3.2 各层职责边界

| 层级 | 职责 | 禁止做的事 |
|------|------|-----------|
| **Controller** | 参数接收、授权验证、调用 Service、返回响应 | 编写复杂业务逻辑 |
| **FormRequest** | 请求参数验证、类型转换 | 访问数据库、调用外部服务 |
| **Service** | 业务规则、状态转换、跨表事务 | 直接访问 HTTP Request/Response |
| **Model** | 数据结构、关联关系、访问器/修改器 | 编写复杂业务逻辑 |
| **Policy** | 权限判断逻辑 | 执行数据库操作、业务计算 |
| **Middleware** | 横切关注点（认证、签名等） | 处理业务逻辑 |

### 3.3 模块划分规范

项目按业务领域划分模块：

| 模块 | 目录结构 | 说明 |
|------|---------|------|
| **合同管理** | `contract/` | 框架合同、执行合同、状态机 |
| **产品订单** | `API/` | 产品、订单、报表统计 |
| **组织人事** | `API/` | 组织、部门、岗位、员工 |
| **第三方渠道** | `ThirdChannel/` | 渠道配置、订单接收、推送 |

### 3.4 状态管理规范

**状态常量必须单一事实来源（SSOT）**：

```php
// app/Enums/ContractStatus.php
enum ContractStatus: int {
    case DRAFT = 0;
    case PENDING = 1;
    case REJECTED = 2;
    case ACTIVE = 3;
    case EXPIRED = 4;
    case TERMINATED = 5;
    case VOID = 6;
    
    public function label(): string {
        return match($this) {
            self::DRAFT => '草稿',
            self::PENDING => '审批中',
            // ...
        };
    }
}

// 使用
use App\Enums\ContractStatus;

$contract->status = ContractStatus::DRAFT->value;
```

### 3.5 路由分组规范

`routes/api.php` 按域拆分，保持结构清晰：

```php
// 公开接口（无需认证）
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// 管理后台接口（Sanctum 认证）
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // 合同管理
    Route::prefix('contract')->group(function () {
        Route::apiResource('master-contracts', MasterContractController::class);
        Route::post('master-contracts/{contract}/submit', [MasterContractController::class, 'submit']);
    });
    
    // 组织架构
    Route::apiResource('organizations', OrganizationController::class);
    
    // 产品订单
    Route::apiResource('products', ProductController::class);
});

// 第三方渠道接口（独立签名验证）
Route::prefix('v2')->middleware('channel.auth')->group(function () {
    Route::post('third-channel', [ReceiverController::class, 'store']);
});
```

---

## 4. 安全规范

### 4.1 认证与授权

| 规则 | 说明 |
|------|------|
| 默认启用 Sanctum | 所有管理后台接口默认使用 `auth:sanctum` 保护 |
| 公开接口白名单 | 仅注册、登录等必要接口设为公开 |
| 权限策略 | 使用 Policy 进行细粒度权限控制 |

### 4.2 输入验证

| 规则 | 说明 |
|------|------|
| 强制使用 FormRequest | 所有 API 输入必须通过 FormRequest 验证 |
| 参数过滤 | 使用 `$request->validated()` 获取安全数据 |
| 防止 SQL 注入 | 使用 Eloquent ORM，避免原生 SQL |

### 4.3 第三方接口安全

| 规则 | 说明 |
|------|------|
| 签名校验 | 所有第三方回调必须验证签名 |
| IP 白名单 | 配置可信 IP 白名单 |
| 防重放攻击 | 使用时间戳和 nonce 防止请求重放 |

### 4.4 审计日志

| 规则 | 说明 |
|------|------|
| 自动记录 | 使用 `laravel-auditing` 自动记录数据变更 |
| 手动记录 | 关键操作手动记录审计日志 |
| 日志内容 | 记录操作人、时间、操作类型、变更前后数据 |

---

## 5. 性能规范

### 5.1 数据库优化

| 规则 | 说明 |
|------|------|
| 列表分页 | 所有列表接口必须分页，默认每页 15 条 |
| 避免 N+1 | 使用 `with()` 预加载关联关系 |
| 选择字段 | 查询时指定需要的字段，避免 `select *` |
| 添加索引 | 为常用查询字段添加索引 |

```php
// 正确：预加载关联，指定字段
$contracts = MasterContract::with(['organizationA:id,name', 'organizationB:id,name'])
    ->select('id', 'contract_no', 'title', 'status', 'created_at')
    ->paginate(15);
```

### 5.2 缓存策略

| 规则 | 说明 |
|------|------|
| 数据字典缓存 | 字典数据缓存 1 小时 |
| 产品授权缓存 | 渠道产品授权缓存 1 小时 |
| 查询缓存 | 复杂报表查询结果缓存 |
| 缓存键命名 | 使用统一命名规范：`{模块}:{数据类型}:{id}` |

### 5.3 异步处理

| 规则 | 说明 |
|------|------|
| 长耗时任务 | 使用 Laravel Queue 异步处理 |
| 队列优先级 | 重要任务使用高优先级队列 |
| 重试机制 | 配置合理的重试次数和延迟 |

---

**文档版本**：v1.0  
**生成日期**：2026年6月  
**适用项目**：TopGun 管理系统