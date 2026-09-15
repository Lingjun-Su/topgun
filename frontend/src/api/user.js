import { api,v } from 'boot/axios'

/**
 * @typedef {Object} User
 * @property {number} id
 * @property {string} name - 用户名
 * @property {string} email - 邮箱
 * @property {string} phone - 手机号
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {number} organization_id - 所属组织ID
 * @property {Object} organization - 组织信息
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * 用户管理 API
 */
export const userApi = {
  /** 获取用户列表，支持分页 */
  list: (params) => api.get(`${v}/users`, { params }),
  /** 创建新用户 */
  store: (data) => api.post(`${v}/users`, data),
  /** 更新用户信息（如果是admin，后端会限制仅能改密码） */
  update: (id, data) => api.put(`${v}/users/${id}`, data),
  /** 逻辑删除 */
  remove: (id) => api.delete(`${v}/users/${id}`),
  /** 修改密码 */
  changePassword: (data) => api.post(`${v}/changePassword`, data),
}