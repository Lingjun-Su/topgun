import { api, v } from 'boot/axios'

/**
 * @typedef {Object} ThirdChannel
 * @property {number} id
 * @property {string} name - 渠道名称
 * @property {string} pid - 渠道标识
 * @property {number} organization_id - 所属组织ID
 * @property {Object} organization - 组织信息
 */

/**
 * @typedef {Object} ChannelSimulatorMessage
 * @property {number} id
 * @property {string} order_no - 订单号
 * @property {string} mobile - 手机号
 * @property {number} sync_status - 同步状态
 */

/**
 * 第三方渠道管理 API
 */
export const ThirdChannelApi = {
  /** 获取渠道列表 */
  list: (params) => api.get(`/${v}/third-channel`, { params }),
  /** 保存（新增或更新） */
  save: (data) => data.id ? api.put(`/${v}/third-channel/${data.id}`, data) : api.post(`/${v}/third-channel`, data),
  /** 逻辑删除 */
  remove: (id) => api.delete(`/${v}/third-channel/${id}`),
  /** 获取审计日志 */
  getAudit: (id) => api.get(`/${v}/third-channel/${id}/audits`)
}

/**
 * 渠道消息模拟器 API
 */
export const channelSimulatorApi = {
  /** 获取模拟发送的历史记录 */
  list: (params) => api.get(`/${v}/third-channel-test`, { params }),
  /** 模拟发送新消息 */
  send: (data) => api.post(`/${v}/third-channel-test`, data),
  /** 逻辑删除记录 */
  remove: (id) => api.delete(`/${v}/third-channel-test/${id}`),
  /** 新增 */
  store: (data) => api.post(`/${v}/third-channel-test/`,data),
}