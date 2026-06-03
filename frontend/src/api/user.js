import { api,v } from 'boot/axios'

//用户
export const userApi = {
  // 获取用户列表，支持分页
  list: (params) => api.get(`${v}/users`, { params }),
  // 创建新用户
  store: (data) => api.post(`${v}/users`, data),
  // 更新用户信息（如果是admin，后端会限制仅能改密码）
  update: (id, data) => api.put(`${v}/users/${id}`, data),
  // 逻辑删除
  remove: (id) => api.delete(`${v}/users/${id}`),
  changePassword: (data) => api.post('v1/changePassword', data),
}
