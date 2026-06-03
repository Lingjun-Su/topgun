# TopGun 编码与架构统一规范（Cursor）

## 1) 技术栈约束

### Frontend
- Node.js: ^20 || ^22
- Vue: 3.5.x
- Quasar: 2.16.x
- Vue Router: 4.x
- Pinia: 3.x
- Axios: 1.x
- ESLint: 9.x
- Prettier: 3.x

### Backend
- PHP: 8.2.x
- Laravel: 12.x
- Sanctum: 4.x
- spatie/laravel-permission: 6.x
- owen-it/laravel-auditing: 14.x
- SQL Server

### Version Policy
- 禁止无评估跨大版本升级
- 升级必须包含影响分析、回归清单、回滚方案
- 锁文件必须提交

---

## 2) 编码风格规范

### 通用
- UTF-8, LF
- 前端 2 空格；后端 4 空格
- 最大行宽 100
- 注释解释“为什么”，不解释显而易见的“做什么”

### 命名
- 前端组件 PascalCase，composable `useXxx`
- JS 变量/函数 camelCase，常量 UPPER_SNAKE_CASE
- PHP 类 PascalCase，方法 camelCase
- DB 表字段 snake_case
- 命名空间与目录大小写必须一致

### 禁止项
- 前端：`console.log/debug`
- 后端：`dd/dump/var_dump`

---

## 3) 架构与模块划分规范

### 前端分层
- `pages/`：页面编排，不堆业务细节
- `components/`：可复用UI
- `api/`：请求封装，不写UI提示逻辑
- `stores/`：全局状态，不拼接后端URL
- `boot/`：全局初始化（axios、guard、plugins）
- 所有请求必须走 `src/api/*`

### 后端分层
- `routes/`：路由映射
- `Controllers/`：编排 + 鉴权入口 + 响应
- `Requests/`：校验
- `Services/`：业务规则 + 事务
- `Models/`：关系和数据行为
- `Policies/`：权限决策
- `Middleware/`：横切关注点（认证/签名等）

### 边界原则
- Controller 不写复杂业务分支
- Service 不依赖 HTTP Request
- 状态机与状态常量必须单一事实来源（SSOT）
- `routes/api.php` 按域拆分（合同/订单/组织/渠道）

---

## 4) 安全与性能规范基础条目

### 安全
- 写接口必须：校验 + 鉴权 + 审计
- 默认启用 Sanctum 保护，公开接口白名单管理
- Token 存储键统一：`auth_token`
- 禁止硬编码敏感配置；统一环境变量
- 第三方回调必须签名校验 + 防重放

### 性能
- 列表接口必须分页
- 查询显式字段，避免 `select *`
- 用 `with()` 避免 N+1
- 长耗时任务走队列
- 生产环境禁用无条件 SQL 全量日志
- 超大前端组件（>500行）强制拆分

---

## 5) PR 最低门槛

- 分层不越界
- 无调试日志残留
- 无硬编码环境地址
- 关键链路有测试或最小回归说明
- 无新增重复状态定义与巨型文件
