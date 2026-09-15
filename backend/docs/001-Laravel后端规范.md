# 001 · Laravel 后端规范

适用范围：Laravel 后端项目。

## 代码风格

- 遵循 PSR-1 / PSR-12，命名空间按 PSR-4 映射到 `app/` 对应目录段。
- 类名 `UpperCamelCase`、方法/变量 `lowerCamelCase`、常量 `UPPER_SNAKE`（详见 005）。
- 优先依赖注入与容器解析，避免在业务代码里滥用门面与全局 `helper`。

## 分层职责

| 层 | 目录 | 职责 | 约束 |
| --- | --- | --- | --- |
| Controller | `app/Http/Controllers` | 接 HTTP、调度 Service、组装响应 | 保持"薄"，不写业务逻辑 |
| Request | `app/Http/Requests` | 参数校验与权限门面 | 校验规则内聚进请求类 |
| Service | `app/Services` | 业务编排与领域逻辑 | 可复用，不依赖 HTTP 细节 |
| Repository | `app/Repositories` | 数据存取封装 | 按聚合划分，不写业务规则 |
| Model | `app/Models` | Eloquent 映射、关系、作用域 | 声明 `$fillable`、关系与局部作用域 |
| DTO | `app/DTO` | 跨层传输的不可变结构 | 复杂入参用 DTO，避免数组满天飞 |
| Traits | `app/Traits` | 按标签复用能力 | 仅放置无状态能力，不承载状态 |

## 数据与迁移

- 结构变更走**迁移**，禁止直接改库；种子与工厂用于环境初始化与测试。
- 模型统一声明 `$fillable` 而非 `$guarded`，避免意外批量赋值。
- 业务表提供 `created_at` / `updated_at`，按需要扩展软删除与审计字段。

## 输入校验与响应

- 外部入参一律经 Request 校验类处理，业务层不再重复信任输入。
- 响应走统一返回结构（见 010 接口契约），业务层抛领域异常而非在 Controller 堆 try/catch。
- 时间用 Carbon、以应用时区为基准，对外传输统一 ISO 8601。

## 关于存储过程

- 业务逻辑留在 Service（PHP），存储过程仅限复杂事务或批量计算场景。
- 使用存储过程必须在项目中登记目的，避免隐式数据逻辑失控。