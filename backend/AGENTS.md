# TopGun Backend — Agent 指南

## 项目是什么

Laravel 12 API 后端：Sanctum 认证、产品/订单/渠道、组织人事、合同工作流。

## 必读文档

- **完整规范**：`docs/CONVENTIONS.md`（命名、数据库、API、分层）
- **Cursor 自动规则**：`.cursor/rules/` 下的 `.mdc` 文件

## 常用路径

| 内容 | 路径 |
|------|------|
| API 路由 | `routes/api.php` |
| 统一响应 | `app/Traits/apiResponse.php`（trait `ApiResponse`） |
| 合同领域 | `app/Http/Controllers/Contract/`, `app/Models/contract/` |
| 迁移 | `database/migrations/` |

## 实现新功能前

1. 在 `database/migrations` 与 `app/Models` 中搜索是否已有类似表/模型
2. 遵循 `docs/CONVENTIONS.md` 表名与 ApiResponse 格式
3. 需要权限时添加 Policy 并在 Controller `authorize`
4. 完成后运行 `php artisan test`

## 禁止

- 修改或泄露 `.env` 中的密钥
- 未经说明重构全库命名或删除迁移
