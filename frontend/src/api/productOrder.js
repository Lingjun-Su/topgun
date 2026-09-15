import { api,v } from 'boot/axios'

/**
 * 产品订单模块接口
 * 对应后端 API: v1/product-orders
 */

const resource = `${v}/product-orders`

export const productOrderApi = {
  /**
   * 获取分页列表
   * @param {Object} params - 筛选条件 (order_no, user_phone, sync_status, bus_id, sku_code等)
   */
  list: (params) => api.get(resource, { params }),

  /**
   * 导出当前筛选条件所有订单
   * @param {Object} params - 筛选条件，和list参数一致
   */
  export: (params) => api.get(`${resource}/export/list`, { params, responseType: 'blob', silent: true }),

  /**
   * 获取单条详情及审计日志
   * @param {Number|String} id - 订单ID
   */
  show: (id) => api.get(`${resource}/${id}`),

  /**
   * 新增订单
   * @param {Object} data - 订单数据
   */
  store: (data) => api.post(resource, data),

  /**
   * 更新订单
   * @param {Number|String} id - 订单ID
   * @param {Object} data - 更新的数据
   */
  update: (id, data) => api.put(`${resource}/${id}`, data),

  /**
   * 逻辑删除订单
   * @param {Number|String} id - 订单ID
   */
  remove: (id) => api.delete(`${resource}/${id}`),

  /**
   * 单条推送上家
   * @param {Number|String} id - 订单ID
   */
  push: (id) => api.post(`${resource}/${id}/push`),

  /**
   * 批量推送上家
   * @param {Array} ids - 选中的ID数组 [1, 2, 3]
   */
  pushBatch: (ids) => api.post(`${resource}/batch-push`, { ids }),

  /**
   * 获取该订单的审计变更历史记录
   * @param {Number|String} id - 订单ID
   */
  getAudits: (id) => api.get(`${resource}/${id}/audits`),

  /**
   * 订单状态统计：按当前筛选条件统计 link_id/code 分布
   * @param {Object} params - 筛选条件，和list参数一致
   */
  statusStats: (params) => api.get(`${resource}/status-stats`, { params })
}
