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

    // 数据权限检查：如果用户有 page_restrictions，只能访问允许的页面
    const dp = authStore.dataPermissions
    if (dp && dp.page_restrictions && dp.page_restrictions.length > 0) {
      // 获取当前路由的路径（去掉开头的 /）
      const path = to.path.replace(/^\//, '')
      // 检查路径是否匹配任何允许的页面
      const allowed = dp.page_restrictions.some(page => path.startsWith(page))
      if (!allowed) {
        // 不允许访问，重定向到允许的第一个页面
        const firstPage = dp.page_restrictions[0]
        return { path: '/' + firstPage }
      }
    }

    // 其他情况正常放行
    return true
  })
})
