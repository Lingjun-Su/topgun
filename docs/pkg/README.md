# PKG（Project Knowledge Graph）— topgun

本目录是唐贝管理系统（topgun）的结构化系统知识网络，依据 `PKG_Technical_Director_Execution_Spec.md` 建立。

> 核心原则：**不要让 AI 记住整个项目；让 PKG 记录整个项目，让关系告诉 AI 修改会影响什么。**

## 目录结构

```text
doc/pkg/
├── PKG.yaml                 # PKG 元数据 + 知识策略 + 硬约束
├── PROJECT-BASELINE.md      # 现状基线（项目考古结论）
├── entities/                # 实体（业务/模块/数据库/代码/外部/规则/状态）
│   ├── business/            # 业务实体
│   ├── module/              # 模块实体
│   ├── database/            # 数据库表实体
│   ├── code/                # 代码实体
│   ├── external/            # 外部系统
│   ├── rule/                # 业务规则
│   └── state/               # 状态/枚举
├── relations/               # 关系（业务/架构/API/数据库/代码/依赖）
├── generated/               # 自动生成索引与扫描报告
└── decisions/               # ADR 架构决策记录
```

## 实体 ID 约定

- 业务实体：`business.<name>`
- 模块：`module.<name>`
- 数据库表：`db.table.<name>`
- 代码：`code.backend.<name>` / `code.frontend.<name>` / `code.model.<name>`
- 外部系统：`external.<name>`
- 规则：`rule.<name>`
- 状态：`state.<name>`

## 信任等级与来源

- `confidence`：`confirmed` / `probable` / `inferred` / `unknown`（`unknown` 不得自动升级为 `confirmed`）
- `source` 优先级：用户要求 > 业务规则 > ADR > 已验证代码 > 数据库结构 > API 契约 > 推断

## 快速入口

- 系统全貌与风险：见 [`PROJECT-BASELINE.md`](./PROJECT-BASELINE.md)
- 实体索引：见 [`generated/entity-index.yaml`](./generated/entity-index.yaml)
- 关系索引：见 [`generated/relation-index.yaml`](./generated/relation-index.yaml)
- 扫描报告（孤儿/死代码/不一致）：见 [`generated/scan-report.yaml`](./generated/scan-report.yaml)
- 关键决策：见 [`decisions/`](./decisions/)