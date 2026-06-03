import { api,v } from 'boot/axios'

export const organizationApi = {
  tree(){
    return api.get(`/${v}/organizations/null/tree`)
  },
  // 获取列表（支持分页和筛选）
  index(params) {
    return api.get(`/${v}/organizations`, { params })
  },
  // 获取详情
  show(id) {
    return api.get(`/${v}/organizations/${id}`)
  },
  // 新增
  store(data) {
    return api.post(`/${v}/organizations`, data)
  },
  // 更新
  update(id, data) {
    return api.put(`/${v}/organizations/${id}`, data)
  },
  // 删除
  destroy(id) {
    return api.delete(`/${v}/organizations/${id}`)
  }
}
