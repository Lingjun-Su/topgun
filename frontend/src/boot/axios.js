import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'
import { LocalStorage, Notify, Dialog } from 'quasar'

const v ='v1';//版本

/**
 * 定义业务异常类
 * 用于标记已经过拦截器处理（Notify）的错误，防止控制台二次报错
 */
class BusinessError extends Error {
  constructor(message, code, data) {
    super(message)
    this.name = 'BusinessError'
    this.code = code
    this.data = data
    this.isHandled = true // 核心标记：界面已处理
  }
}

const api = axios.create({
  baseURL: process.env.API_URL,
  timeout: 15000,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
})

export default defineBoot(({ app }) => {
  // 1. 请求拦截器
  api.interceptors.request.use(
    (config) => {
      const token = LocalStorage.getItem('auth_token')
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }
      return config
    },
    (error) => Promise.reject(error)
  )

  // 2. 响应拦截器
  api.interceptors.response.use(
    (response) => {
      const { code, message, data } = response.data // 对接 ApiResponse Trait
      const { method, silent } = response.config

      // 2.1 完全成功 (200)
      if (code === 200) {
        if (['post', 'put', 'delete'].includes(method.toLowerCase()) && !silent) {
          Notify.create({ type: 'positive', message: message || '操作成功', position: 'center' })
        }
        return data // 解包返回
      }

      // 2.2 部分成功 (206)
      if (code === 206) {
        if (!silent) {
          Notify.create({
            type: 'warning',
            icon: 'warning',
            message: message || '部分任务处理成功',
            caption: data?.counts ? `成功 ${data.counts.success} / 失败 ${data.counts.fail}` : '请核对明细',
            position: 'center',
            timeout: 5000
          })
        }
        return data // 同样返回数据以供组件渲染明细
      }

      // 2.3 业务拦截 (400, 403, 422 等自定义 Code)
      if (!silent) {
        Notify.create({ type: 'negative', message: message || '业务执行异常', position: 'center' })
        console.log("response",response);
        if (code === 403) {
          Dialog.create({ title: '权限受限', message: message || '已被审计记录', persistent: true })
        }
      }

      // 抛出自定义错误，方便 Page 层 try-catch 但不报错
      return Promise.reject(new BusinessError(message, code, data))
    },

    // 3. 网络/系统级错误 (HTTP Status != 2xx)
    (error) => {
      let errorMsg = '网络连接异常'
      const status = error.response?.status;
      const message =error.response?.data?.message;

      if (status === 401) { // 认证失败规范
        LocalStorage.remove('auth_token')
        Dialog.create({
          title: '会话过期',
          message: '请重新登录以确保数据审计安全。',
          persistent: true
        }).onOk(() => { window.location.href = '/login' })
      } else {
        // 根据状态码分配错误信息
        const statusMap = {
          422: '数据校验失败:'+message,
          404: '资源不存在:'+message,
          500: '服务器内部错误 (SQL Server Exception):'+message,
        }
        errorMsg = statusMap[status] || (error.message.includes('timeout') ? '请求超时' : errorMsg)

        if (!error.config?.silent) {
          Notify.create({ type: 'negative', message: errorMsg, position: 'center' })
        }
      }

      return Promise.reject(error)
    }
  )

  app.config.globalProperties.$api = api
})

// 增加全局 Promise 错误监听，彻底消除 Uncaught 警告
if (typeof window !== 'undefined') {
  window.addEventListener('unhandledrejection', (event) => {
    if (event.reason?.isHandled) {
      event.preventDefault() // 阻止业务错误在控制台爆红
    }
  })
}

export { api,v }
