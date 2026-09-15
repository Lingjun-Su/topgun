import { api, v } from 'boot/axios'

/**
 * 验证码流程按天错误码统计 API
 */
export const ForwardStatsApi = {
  /** 按天汇总（请求总数、验证码成功/失败、最终成交/失败） */
  daily: (params) => api.get(`/${v}/forward-stats/daily`, { params }),
  /** 按错误码统计 */
  errorCodes: (params) => api.get(`/${v}/forward-stats/error-codes`, { params })
}