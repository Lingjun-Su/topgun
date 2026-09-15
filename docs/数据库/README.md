# 数据库

本目录存放表结构说明与 ER 图。当前为导入基线。

## 数据库信息

- **数据库**：`topgun`（SQL Server）
- **连接**：见 `backend/.env`（`DB_CONNECTION=sqlsrv`）
- **结构变更**：一律走迁移（`backend/database/migrations`），禁止直接改库（见 `规范/001-Laravel后端规范.md`）

## 现状

- 迁移文件位于 `backend/database/migrations`，种子与工厂位于 `backend/database/seeders`、`backend/database/factories`。
- 表结构说明与 ER 图待补充。

## 待补充

- [ ] 各表结构说明（字段 / 类型 / 约束 / 索引）
- [ ] 表关系 ER 图
- [ ] 关键业务表（订单、合同、渠道）数据流说明
