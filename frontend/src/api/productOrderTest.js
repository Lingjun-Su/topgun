import { api,v } from 'boot/axios'

/**
 * 产品订单模块接口
 * 对应后端 API: v1/product-order-tests
 */

const resource = `${v}/product-order-tests`

export const productOrderTestApi = {
  /**
   * 获取分页列表
   * @param {Object} params - 筛选条件 (order_no, user_phone, sync_status, bus_id, sku_code等)
   */
  list: (params) => api.get(resource, { params }),

  // 提交测试数据
  store: (data) => api.post(resource, data)

}
