<template>
  <chart-card
    title="月销量趋势"
    subtitle="近1个月的日销量"
    :loading="loading"
    @refresh="loadData"
  >
    <apexchart
      type="line"
      height="300"
      :options="chartOptions"
      :series="series"
    />
  </chart-card>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { productOrderReportApi } from 'src/api/reports'
import ChartCard from './ChartCard.vue'

const loading = ref(false);
const series = ref([]);

const chartOptions = ref({
  chart: {
    id: 'sales-trend',
    toolbar: { show: false },
    zoom: { enabled: false },
    animations: { enabled: true },//强制更新时渲染
  },
  stroke: {
    curve: 'smooth', // 平滑曲线，更有现代感
    width: 3
  },
  colors: ['#1976D2'], // 使用 Quasar Primary 颜色
  markers: { size: 4 },
  xaxis: {
    type: 'category',
    labels: { style: { colors: '#9e9e9e' } }
  },
  yaxis: {
    title: { text: '单位 (件)' }
  },
  tooltip: {
    theme: 'light',
    y: { formatter: (val) => `${val} 件` }
  }
})

// 加载数据的方法
const loadData = async () => {
  loading.value = true
  try {
    const res = await productOrderReportApi.getCharts()

    series.value = [{
      name: '销量',
      data: res.data
    }]

  } catch (error) {
    console.error('加载销量趋势失败:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>
