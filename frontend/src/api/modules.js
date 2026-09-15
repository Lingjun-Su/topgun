import { api,v } from 'boot/axios'

/**
 * @typedef {Object} Business
 * @property {number} id
 * @property {string} name - 业务单位名称
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {number} organization_id - 所属组织ID
 * @property {Object} organization - 组织信息
 * @property {Object[]} products - 关联产品列表
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * @typedef {Object} Product
 * @property {number} id
 * @property {string} name - 产品名称
 * @property {string} sku_code - SKU编码
 * @property {string} unit - 单位
 * @property {number} price - 单价
 * @property {number} business_id - 所属业务单位ID
 * @property {Object} business - 业务单位信息
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * @typedef {Object} ProductProvince
 * @property {number} id
 * @property {number} product_id - 产品ID
 * @property {string} province_code - 省份编码
 * @property {string} province_name - 省份名称
 * @property {number} price - 省份价格
 */

/**
 * @typedef {Object} Carrier
 * @property {number} id
 * @property {string} name - 运营商名称
 * @property {string} code - 运营商编码
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * @typedef {Object} Area
 * @property {number} id
 * @property {string} code - 区域编码
 * @property {string} name - 区域名称
 * @property {number} level - 层级: 0=省, 1=市, 2=区, 3=镇
 * @property {string} parent_code - 父级编码
 */

/**
 * @typedef {Object} Channel
 * @property {number} id
 * @property {string} name - 渠道名称
 * @property {string} code - 渠道编码
 * @property {number} status - 状态: 0=禁用, 1=启用
 * @property {string} role - 身份角色: supplier_a=上游供应商(A) / channel_c=下游推广(C) / unset=未分类
 * @property {Object[]} products - 关联产品列表
 * @property {string} created_at - 创建时间
 * @property {string} updated_at - 更新时间
 */

/**
 * @typedef {Object} Invoice
 * @property {number} id
 * @property {string} invoice_no - 发票编号
 * @property {number} amount - 发票金额
 * @property {string} type - 发票类型
 * @property {string} created_at - 创建时间
 */

/**
 * 业务单位管理 API
 */
export const businessApi = {
  /** 获取业务单位列表 */
  list: (params) => api.get(v+'/businesses', { params }),
  /** 新增业务单位 */
  store: (data) => api.post(v+'/businesses', data),
  /** 更新业务单位 */
  update: (id, data) => api.put(`${v}/businesses/${id}`, data),
  /** 逻辑删除业务单位 */
  remove: (id) => api.delete(`${v}/businesses/${id}`)
}

/**
 * 产品管理 API
 */
export const productApi = {
  /** 获取产品列表 */
  list: (params) => api.get(v+'/products', { params }),
  /** 新增产品 */
  store: (data) => api.post(v+'/products', data),
  /** 更新产品 */
  update: (id, data) => api.put(`${v}/products/${id}`, data),
  /** 逻辑删除产品 */
  remove: (id) => api.delete(`${v}/products/${id}`),
  /** 获取审计日志 */
  audit: (id) => api.get(`${v}/products/${id}/audit`),
  /** 按业务单位获取产品列表 */
  listByBusiness: (businessId) => api.get(v + '/products', { params: { business_id: businessId } }),
  /** 获取所有业务单位（下拉选择用） */
  businessList: () => api.get(v + '/businesses/all'),
}

/**
 * 产品省份设置 API
 */
export const productProvinceAPI ={
  /** 更新省份设置 */
  update:(id,data)=>api.put(`${v}/productsProvince/${id}`, data),
}

/**
 * 运营商管理 API
 */
export const carrierApi = {
  /** 获取运营商列表 */
  list: (params) => api.get(v+'/carriers', { params }),
  /** 新增运营商 */
  store: (data) => api.post(v+'/carriers', data),
  /** 更新运营商 */
  update: (id, data) => api.put(`${v}/carriers/${id}`, data),
  /** 逻辑删除运营商 */
  remove: (id) => api.delete(`${v}/carriers/${id}`),
  /** 获取审计日志 */
  audit: (id) => api.get(`${v}/carriers/${id}/audit`)
}

/**
 * 省市区镇级联数据 API
 */
export const areaApi = {
  /** 获取省份列表 (level=0) */
  getProvinces: () => api.get(`${v}/areas`, { params: { level: 0 } }),
  /** 根据父级编码获取下级区域数据 */
  fetchChildren: (code) => api.get(`${v}/areas`, { params: { parent_code: code } }),
}

/**
 * 渠道管理 API
 */
export const channelApi = {
  /** 获取渠道列表（含分页/搜索） */
  list: (params) => api.get(`${v}/channels`, { params }),
  /** 获取单个渠道详情（包含关联产品） */
  show: (id) => api.get(`${v}/channels/${id}`),
  /** 新增渠道 */
  store: (data) => api.post(`${v}/channels`, data),
  /** 更新渠道 */
  update: (id, data) => api.put(`${v}/channels/${id}`, data),
  /** 逻辑删除渠道 */
  remove: (id) => api.delete(`${v}/channels/${id}`),

  /** 同步渠道关联产品 */
  syncProducts: (id, products) => api.post(`${v}/channels/${id}/products`, { products }),

  /** 停止条件类型清单（条件类型 + 表单 schema） */
  stopConditionTypes: () => api.get(`${v}/channels/stop-condition-types`),

  /** 停止记录查询 */
  forwardStopLogs: (params) => api.get(`${v}/forward-stop-logs`, { params }),
}

/**
 * 发票管理 API
 */
export const invoiceApi = {
  /** 保存发票数据到 SQL Server */
  save: (data) => api.post('/v1/invoices', data),
}