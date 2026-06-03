import { api } from 'boot/axios'
let v2 ='v2';
export const ThirdChannelApi = {
  // 获取渠道列表
  list: (params) => api.get(`/${v2}/third-channel`, { params }),
  // 保存（新增或更新）
  save: (data) => data.id ? api.put(`/${v2}/third-channel/${data.id}`, data) : api.post(`/${v2}/third-channel`, data),
  // 逻辑删除
  remove: (id) => api.delete(`/${v2}/third-channel/${id}`),
  // 获取审计日志
  getAudit: (id) => api.get(`/${v2}/third-channel/${id}/audits`)
}

// 渠道消息模拟器接口
export const channelSimulatorApi = {
  // 获取模拟发送的历史记录
  list: (params) => api.get(`/${v2}/third-channel-test`, { params }),
  // 模拟发送新消息
  send: (data) => api.post(`/${v2}/third-channel-test`, data),
  // 逻辑删除记录
  remove: (id) => api.delete(`/${v2}/third-channel-test/${id}`),
  store: (data) => api.post(`/${v2}/third-channel-test/`,data),//新增
}
