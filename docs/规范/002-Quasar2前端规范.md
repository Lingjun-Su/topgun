# 002 · Quasar2 前端规范

适用范围：Quasar2 / Vue3 前端项目。

## 目录结构

```
src/
  ├── api/           # 接口请求统一封装层
  ├── stores/        # Pinia 状态
  ├── router/        # vue-router 配置与守卫
  ├── layouts/       # 布局组件
  ├── pages/         # 路由页面
  ├── components/    # 通用与业务组件
  ├── composables/   # 组合式函数 useXxx
  ├── utils/         # 纯函数与工具（含 constants/enums）
  └── assets/        # 样式、静态资源
```

## 组织与状态

- 使用**组合式 API + `script setup`**，逻辑复用走 `useXxx` 组合式函数。
- 跨组件共享的取数与状态统一由 **Pinia** 承载，不在组件内裸发请求。
- 页面组件只做"编排与展示"，把可复用的交互拆成 `components/`，把可复用的逻辑拆成 `composables/`。

## 请求层

- 所有后端请求集中在 `api/` 封装 baseURL、鉴权头与统一拦截，页面不直接拼 URL（详见 010）。
- 鉴权头统一走 Bearer Token，令牌由状态或缓存统一持有并注入拦截器。

## 命名与样式

- 组件命名 `UpperCamelCase`，页面以业务语义命名。
- 布尔变量用 `is`/`has` 前缀，事件回调与 props 语义封闭。
- 样式可使用 BEM 或组件 `scoped`；主题令牌（颜色、间距、字号）集中定义供全站复用，不散落魔法色值。

## 枚举

- 业务枚举值只在 `src/utils/constants` 映射展示，不做业务判断（详见 006）。