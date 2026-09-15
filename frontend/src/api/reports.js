//报表接口
import { api,v } from 'boot/axios'

/**
 * @typedef {Object} SalesBriefing
 * @property {number} total_sales - 总销售额
 * @property {number} today_sales - 今日销售额
 * @property {number} total_orders - 总订单数
 * @property {number} pending_orders - 待处理订单数
 * @property {number} month_over_month - 环比增长率
 */

/**
 * @typedef {Object} ChartDataPoint
 * @property {string} date - 数据日期
 * @property {number} value - 数据值
 * @property {string} [label] - 数据标签
 */

/**
 * @typedef {Object} PieChartItem
 * @property {string} name - 分类名称
 * @property {number} value - 数值
 * @property {number} percentage - 百分比
 */

/**
 * @typedef {Object} ProductOrderStats
 * @property {number} total_orders - 总订单量
 * @property {number} total_amount - 总金额
 * @property {number} avg_amount - 平均金额
 * @property {number} max_amount - 最大金额
 * @property {number} min_amount - 最小金额
 */

/**
 * 产品订单报表 API
 */
export const productOrderReportApi = {
  /** 获取图表数据（近一年月销量折线图） */
  getCharts: (params) => api.get(v+'/reports/product-order-chart', { params }),
  /** 近一年月销量简报 */
  getProductOrderBriefing: (params) => api.get(v+'/reports/product-order-briefing', { params }),
  /** 产品销量占比饼形图 */
  getProductOrderSalesPieChart:(params)=>api.get(v+'/reports/product-order-sales-pie-chart',{params}),
  /** 今日销售数据 */
  getSalesTodayData:(params)=>api.get(v+'/reports/product-order-sales-today-data',{params}),
  /** 业务统计 */
  getStats:(params)=>api.get(v+'/reports/product-order-business-stats',{params}),
}