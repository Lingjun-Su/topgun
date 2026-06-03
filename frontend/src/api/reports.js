//报表接口
import { api,v } from 'boot/axios'

//产品订单报表
export const productOrderReportApi = {
  getCharts: (params) => api.get(v+'/reports/product-order-chart', { params }),
  getProductOrderBriefing: (params) => api.get(v+'/reports/product-order-briefing', { params }),//近一年月销量折线图
  getProductOrderSalesPieChart:(params)=>api.get(v+'/reports/product-order-sales-pie-chart',{params}),//产品销量占比饼形图
  getSalesTodayData:(params)=>api.get(v+'/reports/product-order-sales-today-data',{params}),//产品销量占比饼形图
  getStats:(params)=>api.get(v+'/reports/product-order-business-stats',{params}),//产品销量占比饼形图
}
