import { api,v } from 'boot/axios'

// 业务单位接口
export const businessApi = {
  list: (params) => api.get(v+'/businesses', { params }),
  store: (data) => api.post(v+'/businesses', data),
  update: (id, data) => api.put(`${v}/businesses/${id}`, data),
  remove: (id) => api.delete(`${v}/businesses/${id}`)
}

// 产品接口 地
export const productApi = {
  list: (params) => api.get(v+'/products', { params }),
  store: (data) => api.post(v+'/products', data),
  update: (id, data) => api.put(`${v}/products/${id}`, data),
  remove: (id) => api.delete(`${v}/products/${id}`),
  audit: (id) => api.get(`${v}/products/${id}/audit`), // 获取审计日志
  listByBusiness: (businessId) => api.get(v + '/products', { params: { business_id: businessId } }),
  businessList: () => api.get(v + '/businesses/all'),
}
//产品省份设置
export const productProvinceAPI ={
  update:(id,data)=>api.put(`${v}/productsProvince/${id}`, data),//只有更新
}

// 运营商接口
export const carrierApi = {
  list: (params) => api.get(v+'/carriers', { params }),
  store: (data) => api.post(v+'/carriers', data),
  update: (id, data) => api.put(`${v}/carriers/${id}`, data),
  remove: (id) => api.delete(`${v}/carriers/${id}`),
  audit: (id) => api.get(`${v}/carriers/${id}/audit`) // 获取审计日志
}

//省市区镇接口
export const areaApi = {
  /**
   * 获取省份列表 (level=0)
   * 逻辑删除和审计由后端通过 SQL Server 2019 处理，前端仅负责调用
   */
  getProvinces: () => api.get(`${v}/areas`, { params: { level: 0 } }),//读取省份数据
  fetchChildren: (code) => api.get(`${v}/areas`, { params: { parent_code: code } }),//读取下级数据
}

// 渠道管理接口
export const channelApi = {
  // 获取渠道列表（含分页/搜索）
  list: (params) => api.get(`${v}/channels`, { params }),
  // 获取单个渠道详情（包含关联产品）
  show: (id) => api.get(`${v}/channels/${id}`),
  // 新增渠道
  store: (data) => api.post(`${v}/channels`, data),
  // 更新渠道
  update: (id, data) => api.put(`${v}/channels/${id}`, data),
  // 逻辑删除渠道
  remove: (id) => api.delete(`${v}/channels/${id}`),

  // 渠道关联产品操作
  syncProducts: (id, products) => api.post(`${v}/channels/${id}/products`, { products })
}

// 发票
export const invoiceApi = {
  // 保存发票数据到 SQL Server
  save: (data) => api.post('/v1/invoices', data),
};
