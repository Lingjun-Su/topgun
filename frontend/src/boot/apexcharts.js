import { defineBoot } from '#q-app/wrappers'
import VueApexCharts from 'vue3-apexcharts'

/**
 * 严谨注册 ApexCharts 插件
 * 使得 <apexchart> 组件可以在全站 Vue 文件中直接使用
 */
export default defineBoot(({ app }) => {
  // 注入插件
  app.use(VueApexCharts)

  // 如果需要，也可以在这里配置 ApexCharts 的全局默认属性
  // window.Apex = { ... }
})
