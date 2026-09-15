import { api,v } from 'boot/axios'

/**
 * @typedef {Object} Organization
 * @property {number} id
 * @property {string} name - 组织名称
 * @property {number} parent_id - 父级组织ID
 * @property {number} level - 组织层级
 * @property {string} path - 组织路径
 * @property {Object[]} children - 子组织列表
 * @property {number} sort - 排序
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * 组织架构管理 API
 */
export const organizationApi = {
  /** 获取组织树 */
  tree(){
    return api.get(`/${v}/organizations/null/tree`)
  },
  /** 获取列表（支持分页和筛选） */
  index(params) {
    return api.get(`/${v}/organizations`, { params })
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