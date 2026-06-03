import { api,v } from 'boot/axios'

export const CompanyApi = {
  // 读取公司列表
  listCompany(params) {
    return api.get(`/${v}/company`, { params })
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
    return api.put(`/v1/organizations/${id}`, data)
  },
  // 删除
  destroy(id) {
    return api.delete(`/v1/organizations/${id}`)
  }
}
