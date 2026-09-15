# 数据库迁移文件索引

## 文档信息
| 项目 | 内容 |
|------|------|
| 创建日期 | 2026-08-06 |
| 版本 | v1.0 |
| 说明 | 按业务领域对迁移文件分类，方便查找和管理 |

---

## 领域分类索引

### 1. 系统基础 (System)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `0001_01_01_000000_create_users_table.php` | 用户表 |
| 2 | `0001_01_01_000001_create_cache_table.php` | 缓存表 |
| 3 | `0001_01_01_000002_create_jobs_table.php` | 任务队列表 |
| 4 | `2026_01_12_032722_create_personal_access_tokens_table.php` | 个人访问令牌（Sanctum） |
| 5 | `2026_01_15_082329_create_permission_tables.php` | 权限角色表（Spatie Permission） |
| 6 | `2026_05_15_085630_add_role_to_users_table.php` | 用户表添加角色字段 |

### 2. 登录审计 (Auth)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_12_033907_create_login_logs_table.php` | 登录日志表 |

### 3. 审计日志 (Audit)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_12_084137_create_audits_table.php` | 审计日志表 |

### 4. 区域数据 (Area)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_12_062028_create_areas_table.php` | 省市区镇四级区域表 |

### 5. 组织架构 (Organization)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_13_015135_create_organizations_table.php` | 组织表 |
| 2 | `2026_01_13_015146_create_organization_partnerships_table.php` | 组织合作关系表 |
| 3 | `2026_01_13_015158_create_departments_table.php` | 部门表 |
| 4 | `2026_01_13_015208_create_positions_table.php` | 岗位表 |
| 5 | `2026_01_13_015217_create_employees_table.php` | 员工表 |
| 6 | `2026_01_13_015227_create_employee_transfers_table.php` | 员工调动记录表 |
| 7 | `2026_01_13_022058_add_head_position_foreign_to_departments_table.php` | 部门表添加负责人外键 |
| 8 | `2026_01_14_033913_create_organization_banks.php` | 组织银行账户表 |

### 6. 字典数据 (Dictionary)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_15_013959_create_dictionaries.php` | 字典数据表 |

### 7. 项目管理 (Project)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_15_030803_create_project_bids.php` | 项目投标表 |
| 2 | `2026_01_15_030823_create_project.php` | 项目表 |
| 3 | `2026_01_15_030902_create_project_subcontracts.php` | 项目分包表 |
| 4 | `2026_01_15_030924_create_project_settlements.php` | 项目结算表 |
| 5 | `2026_01_15_030945_create_settlement_details.php` | 结算明细表 |
| 6 | `2026_01_15_031006_create_project_receivables.php` | 项目应收款表 |
| 7 | `2026_01_15_031034_create_project_payables.php` | 项目应付款表 |
| 8 | `2026_01_15_031057_create_invoices.php` | 发票表 |
| 9 | `2026_02_05_020617_create_settlements.php` | 结算总表 |
| 10 | `2026_02_05_021134_create_settlement_items.php` | 结算明细项表 |

### 8. 薪资管理 (Salary)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_15_031130_create_salary_details.php` | 薪资明细表 |
| 2 | `2026_01_15_031200_create_salary_payments.php` | 薪资发放表 |
| 3 | `2026_01_15_031256_create_tax_decarations.php` | 税务申报表 |

### 9. 任务与审批 (Task & Approval)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_15_080001_create_task.php` | 任务表 |
| 2 | `2026_01_15_080111_create_approval_logs.php` | 审批日志表 |

### 10. 三方渠道 (Third Channel)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_26_012124_create_third_channels.php` | 第三方渠道表 |
| 2 | `2026_01_26_012642_create_channel_api_sync_logs.php` | 渠道API同步日志表 |
| 3 | `2026_04_20_094818_add_ip_whitelist_to_third_channels_table.php` | 渠道表添加IP白名单字段 |

### 11. 产品订单 (Product Order)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_01_27_013651_create_product_orders_table.php` | 产品订单表 |
| 2 | `2026_03_12_062104_product_order_statuses.php` | 产品订单状态表 |
| 3 | `2026_04_08_020913_add_verification_code_to_product_orders_table.php` | 产品订单表添加验证码字段 |

### 12. 业务与产品 (Business & Product)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_02_26_020627_create_business_table.php` | 业务单位表 |
| 2 | `2026_02_26_020637_create_products_table.php` | 产品表 |
| 3 | `2026_02_26_022640_create_channel_products_table.php` | 渠道产品关联表 |
| 4 | `2026_02_27_073321_create_carrier_table.php` | 运营商表 |
| 5 | `2026_03_02_080636_create_products_province_table.php` | 产品省份价格表 |

### 13. 合同管理 (Contract)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_04_16_073305_create_master_contracts_table.php` | 框架合同表 |
| 2 | `2026_04_17_085035_create_master_contract_logs_table.php` | 框架合同日志表 |
| 3 | `2026_04_17_085054_create_master_contract_changes_table.php` | 框架合同变更表 |
| 4 | `2026_04_17_085116_create_master_contract_amendments_table.php` | 框架合同补充协议表 |
| 5 | `2026_04_17_085243_create_execution_contractions_table.php` | 执行合同表 |
| 6 | `2026_05_09_094639_create_execute_contract_logs_table.php` | 执行合同日志表 |

### 14. 索引优化 (Indexes)
| # | 文件名 | 说明 |
|---|--------|------|
| 1 | `2026_06_03_000001_add_foreign_keys_and_indexes.php` | 外键约束与索引优化 |

---

## 命名规范

### 现有文件命名格式
```
YYYY_MM_DD_HHMMSS_create_{table_name}_table.php
YYYY_MM_DD_HHMMSS_add_{column}_to_{table}_table.php
```

### 新迁移文件命名建议
为便于按领域分组，建议在新迁移文件名中使用领域前缀：

```
{YYYY_MM_DD_HHMMSS}_{domain}_{description}.php
```

示例：
| 领域 | 前缀 | 示例 |
|------|------|------|
| 系统基础 | system | `2026_08_07_000000_system_add_login_attempts_to_users.php` |
| 组织架构 | org | `2026_08_07_000001_org_add_department_level.php` |
| 项目管理 | project | `2026_08_07_000002_project_add_budget_fields.php` |
| 合同管理 | contract | `2026_08_07_000003_contract_add_approval_flow.php` |
| 产品订单 | order | `2026_08_07_000004_order_add_delivery_status.php` |
| 三方渠道 | channel | `2026_08_07_000005_channel_add_rate_limit.php` |
| 业务产品 | business | `2026_08_07_000006_business_add_category.php` |
| 薪资管理 | salary | `2026_08_07_000007_salary_add_bonus_field.php` |
| 字典数据 | dict | `2026_08_07_000008_dict_add_system_flags.php` |
| 审计日志 | audit | `2026_08_07_000009_audit_add_ip_location.php` |

### 命名规范要点
1. **领域前缀**：使用小写英文缩写标识所属业务领域
2. **分隔符**：各部分之间使用下划线 `_` 连接
3. **描述动词**：使用 `add_` / `create_` / `update_` / `drop_` 开头
4. **表名**：使用复数形式（如 `users` 而非 `user`）
5. **避免缩写冲突**：如领域前缀与已有表名冲突，使用完整域名

---

## 统计

| 统计项 | 数量 |
|--------|------|
| 迁移文件总数 | 50 |
| 业务领域数 | 14 |
| 最早迁移 | 0001_01_01_000000_create_users_table.php |
| 最近迁移 | 2026_06_03_000001_add_foreign_keys_and_indexes.php |