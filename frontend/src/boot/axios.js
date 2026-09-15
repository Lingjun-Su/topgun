import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'
import { LocalStorage, Notify, Dialog } from 'quasar'

const v ='v1'

/**
 * @typedef {Object} ApiResponse
 * @property {number} code - 业务状态码（200=成功, 4xx=客户端错误, 5xx=服务端错误）
 * @property {string} status - 状态分类: success / error / partial
 * @property {string} message - 描述信息
 * @property {*} data - 业务数据主体
 */

/**
 * @typedef {Object} BusinessError
 * @property {string} name - 错误名称: BusinessError
 * @property {number} code - 业务状态码
 * @property {*} data - 错误关联数据
 * @property {boolean} isHandled - 是否已处理标记
 */

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
    this.isHandled = true
  }
}

const api = axios.create({
  baseURL: process.env.API_URL,
  timeout: 15000,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN'
})

export default defineBoot(({ app }) => {
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

  api.interceptors.response.use(
    (response) => {
      // 如果是 blob 下载，直接返回 response（不需要解析 JSON）
      if (response.config.responseType === 'blob') {
        return response
      }

      const { code, message, data } = response.data
      const { method, silent } = response.config

      if (code === 200) {
        if (['post', 'put', 'delete'].includes(method.toLowerCase()) && !silent) {
          Notify.create({ type: 'positive', message: message || '操作成功', position: 'center' })
        }
        return response.data
      }

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
        return response.data
      }

      if (!silent) {
        Notify.create({ type: 'negative', message: message || '业务执行异常', position: 'center' })
        if (code === 403) {
          Dialog.create({ title: '权限受限', message: message || '已被审计记录', persistent: true })
        }
      }

      return Promise.reject(new BusinessError(message, code, data))
    },

    (error) => {
      let errorMsg = '网络连接异常'
      const status = error.response?.status;
      const message =error.response?.data?.message;

      if (status === 401) {
        LocalStorage.remove('auth_token')
        Dialog.create({
          title: '会话过期',
          message: '请重新登录以确保数据审计安全。',
          persistent: true
        }).onOk(() => { window.location.href = '/login' })
      } else {
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

if (typeof window !== 'undefined') {
  window.addEventListener('unhandledrejection', (event) => {
    if (event.reason?.isHandled) {
      event.preventDefault()
    }
  })
}

export { api,v }