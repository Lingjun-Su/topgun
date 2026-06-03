<template>
  <q-layout>
    <q-page-container>
      <!-- 全屏居中渐变背景布局，增强视觉专业感 -->
      <q-page class="flex flex-center bg-grey-2">
        <q-card class="login-card q-pa-lg shadow-15" style="width: 400px; max-width: 90vw;">

          <!-- 卡片头部：Logo 与 系统标识 -->
          <q-card-section class="text-center q-pb-none">
            <q-avatar size="100px" class="q-mb-md">
              <img src="~assets/TopGun.png" alt="拓耕集团 Logo" style="object-fit: contain;" />
            </q-avatar>
            <div class="text-h5 text-weight-bold text-grey-9">拓耕集团后台管理系统</div>
            <div class="text-subtitle2 text-grey-6 q-mt-xs">企业级全栈开发管理平台</div>
          </q-card-section>

          <!-- 登录表单核心区 -->
          <q-card-section>
            <!-- 绑定 onSubmit 方法，启用 Quasar 的原生校验拦截机制 -->
            <q-form @submit="onSubmit" class="q-gutter-md">

              <!-- 手机号/账号输入框 -->
              <q-input
                v-model="form.phone"
                label="手机号"
                outlined
                dense
                clearable
                maxLength="11"
                :rules="[
                  val => !!val || '请输入手机号',
                  val => /^1[3-9]\d{9}$/.test(val) || '请输入有效的11位手机号'
                ]"
                lazy-rules
              >
                <template v-slot:prepend>
                  <q-icon name="phone_android" color="primary" />
                </template>
              </q-input>

              <!-- 密码输入框 -->
              <q-input
                v-model="form.password"
                label="密码"
                outlined
                dense
                clearable
                :type="isPwdVisible ? 'text' : 'password'"
                :rules="[
                  val => !!val || '请输入密码',
                  val => val.length >= 6 || '密码长度至少6位'
                ]"
                lazy-rules
              >
                <template v-slot:prepend>
                  <q-icon name="lock" color="primary" />
                </template>
                <template v-slot:append>
                  <q-icon
                    :name="isPwdVisible ? 'visibility' : 'visibility_off'"
                    class="cursor-pointer"
                    @click="isPwdVisible = !isPwdVisible"
                  />
                </template>
              </q-input>

              <!-- 记住我 与 忘记密码 辅助栏 -->
              <div class="row items-center justify-between q-pt-xs">
                <q-checkbox v-model="form.remember" label="记住登录状态" dense class="text-grey-7" />
                <q-btn label="忘记密码？" flat color="secondary" size="sm" class="no-padding" to="/forgot-password" />
              </div>

              <!-- 提交表单动作 -->
              <div class="q-mt-xl">
                <q-btn
                  label="安 全 登 录"
                  type="submit"
                  color="primary"
                  size="lg"
                  class="full-width text-weight-bold"
                  unelevated
                  :loading="loading"
                  :disable="loading"
                />
              </div>
            </q-form>

            <!-- 业务逻辑拦截的错误信息局部展示（当拦截器抛出 reject 时，此处作为辅助承载） -->
            <div v-if="errorMsg" class="text-negative text-center q-mt-md text-caption text-weight-medium">
              <q-icon name="error" class="q-mr-xs" /> {{ errorMsg }}
            </div>
          </q-card-section>

          <!-- 卡片底部：版权声明 -->
          <q-card-section class="text-center text-grey-5 text-caption q-pt-none">
            © 2026 拓耕集团信息管理中心. All Rights Reserved.
          </q-card-section>
        </q-card>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from 'src/stores/auth'

// 依赖注入管理
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// --- 响应式状态声明 (State) ---
const form = ref({
  phone: '',
  password: '',
  remember: false
})

const isPwdVisible = ref(false) // 控制密码明文显示
const loading = ref(false)      // 全局提交状态锁
const errorMsg = ref('')        // 局部逻辑错误消息

/**
 * 核心登录表单提交逻辑
 * 遵循《前后端统一响应与业务状态码规范文档》：
 * 组件内部不需要自行处理通用的网络错误和弹窗提示，只需维护好组件自身的 Loading 状态。
 */
const onSubmit = async () => {
  errorMsg.value = ''
  loading.value = true

  try {
    // 1. 将登录核心逻辑下沉至 Pinia 统一调度，实现状态、Token、权限的高内聚封装
    // 传入整个表单对象（含 remember 状态，便于 Store 内部处理持久化策略）
    const success = await authStore.login({
      phone: form.value.phone,
      password: form.value.password,
      remember: form.value.remember
    })

    if (success) {
      // 2. 路由平滑重定向：优先跳转到来源页，若无来源则导向控制台主页
      const redirect = route.query.redirect || '/'
      router.push(redirect)
    }
  } catch (err) {
    // 3. 极简异常捕获：网络层错误和标准业务错误已由 boot/axios.js 拦截器统一弹出 Notify 提示
    // 此处仅捕获异常用于恢复组件的交互状态（如关闭 Loading、局部提示渲染）
    errorMsg.value = err?.message || '登录遇到阻碍，请核对信息'
    console.error('[Login Component Error] ->', err)
  } finally {
    loading.value = false // 释放状态锁
  }
}

/**
 * 页面守卫：防止重复登录
 * 当用户已持有有效身份凭证时，阻止其再次访问登录页，直接重定向到工作台
 */
onMounted(() => {
  if (authStore.isAuthenticated) {
    router.push('/')
  }
})
</script>

<style lang="sass" scoped>
.login-card
  border-radius: 16px
  background: #ffffff
  border: 1px solid rgba(0, 0, 0, 0.05)
  // 采用更柔和的多层阴影，更具现代质感
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08), 0 5px 15px rgba(0, 0, 0, 0.04)

// 针对大屏幕的平滑微调
@media (min-width: 600px)
  .login-card
    transform: translateY(-20px)
    transition: transform 0.3s ease
</style>
