import { api,v } from 'boot/axios'

/**
 * @typedef {Object} Company
 * @property {number} id
 * @property {string} name - 公司名称
 * @property {string} code - 公司编码
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * 公司管理 API
 */
export const CompanyApi = {
  /** 读取公司列表 */
  listCompany(params) {
    return api.get(`/${v}/company`, { params })
  },
  /** 获取详情 */
  show(id) {
    return api.get(`/${v}/organizations/${id}`)
  },
  /** 新增 */
  store(data) {
    return api.post(`/${v}/organizations`, data)
  },
  /** 更新 */
  update(id, data) {
    return api.put(`/${v}/organizations/${id}`, data)
  },
  /** 删除 */
  destroy(id) {
    return api.delete(`/${v}/organizations/${id}`)
  }
}