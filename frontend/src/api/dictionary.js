import { api, v } from 'boot/axios'

/**
 * @typedef {Object} Dictionary
 * @property {number} id
 * @property {string} type - 字典类型
 * @property {string} code - 字典编码
 * @property {string} label - 字典显示值
 * @property {number} sort - 排序
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * 字典数据管理 API
 */
export const dictionaryApi = {
  /** 获取列表（支持分页和筛选） */
  index(params) {
    return api.get(`${v}/dictionaries`, { params })
  },
  /** 获取详情 */
  show(id) {
    return api.get(`${v}/dictionaries/${id}`)
  },
  /** 新增 */
  store(data) {
    return api.post(`${v}/dictionaries`, data)
  },
  /** 更新 */
  update(id, data) {
    return api.put(`${v}/dictionaries/${id}`, data)
  },
  /** 删除 */
  destroy(id) {
    return api.delete(`${v}/dictionaries/${id}`)
  }
}