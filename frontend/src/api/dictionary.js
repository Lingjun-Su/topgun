import { api } from 'boot/axios'

export const dictionaryApi = {
  // 获取列表（支持分页和筛选）
  index(params) {
    return api.get('dictionaries', { params })
  },
  // 获取详情
  show(id) {
    return api.get(`dictionaries/${id}`)
  },
  // 新增
  store(data) {
    return api.post('dictionaries', data)
  },
  // 更新
  update(id, data) {
    return api.put(`dictionaries/${id}`, data)
  },
  // 删除
  destroy(id) {
    return api.delete(`dictionaries/${id}`)
  }
}
