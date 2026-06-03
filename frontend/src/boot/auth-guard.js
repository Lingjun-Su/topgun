// src/boot/auth-guard.js
// 该 boot 文件负责全局路由认证守卫
// 详细注释：
// - boot 文件在应用启动前执行，保证守卫在任何路由导航前生效
// - 使用 Pinia 时必须传入 store 参数，否则 useAuthStore() 会报错（Pinia 未注入）

import { boot } from 'quasar/wrappers'
import { useAuthStore } from 'src/stores/auth'

export default boot(({ router, store }) => {
  // 获取 Pinia store 实例（必须传入 store 参数）
  const authStore = useAuthStore(store)
  router.beforeEach((to) => {
    // 如果路由明确标记不需要认证 → 直接放行
    if (to.meta.requiresAuth === false) {
      return true
    }

    // 已登录用户访问登录页 → 跳转首页（防止重复登录）
    if (to.name === 'login' && authStore.isAuthenticated) {
      return { name: 'home' }
    }

    // 需要认证但未登录 → 跳转登录页，并携带原路径
    if (to.meta.requiresAuth === true && !authStore.isAuthenticated) {
      return {
        name: 'login',
        query: { redirect: to.fullPath }
      }
    }

    // 其他情况正常放行
    return true
  })
})
