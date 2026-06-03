<template>
  <q-page padding>
    <q-card flat bordered class="render-test-card">
      <q-card-section class="row items-center">
        <div class="text-h6">ApexCharts 安装测试</div>
        <q-chip :color="chartReady ? 'positive' : 'negative'" text-color="white" class="q-ml-md">
          {{ chartReady ? '组件已挂载' : '加载中...' }}
        </q-chip>
      </q-card-section>

      <q-separator />

      <q-card-section>
        <apexchart
          width="100%"
          height="400"
          type="bar"
          :options="chartOptions"
          :series="series"
          @mounted="onChartMounted"
        ></apexchart>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, reactive } from 'vue'
// 注意：如果在 boot 文件中全局注册了，这里可以不引入。
// 为了测试稳定性，我们在这里先尝试局部引入。
import VueApexCharts from 'vue3-apexcharts'

// 注册组件
const apexchart = VueApexCharts
const chartReady = ref(false)

// 图表配置（遵循 ApexCharts 官方 API）
const chartOptions = reactive({
  chart: {
    id: 'basic-bar-test',
    toolbar: { show: true }
  },
  xaxis: {
    categories: ['开发部', '市场部', '人事部', '财务部', '生产部']
  },
  colors: ['#21ba45'], // 使用 Quasar 的 Positive 绿色
  title: {
    text: '部门人数测试数据 (Static)',
    align: 'center'
  }
})

// 图表数据
const series = reactive([
  {
    name: '在职人数',
    data: [30, 40, 15, 25, 50]
  }
])

const onChartMounted = () => {
  console.log('TopGun: ApexCharts 渲染成功！')
  chartReady.value = true
}
</script>

<style scoped>
.render-test-card {
  max-width: 800px;
  margin: 20px auto;
}
</style>
