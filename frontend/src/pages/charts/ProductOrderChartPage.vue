<template>
  <q-page class="q-pa-md bg-grey-2">
    <div class="row items-center q-mb-md">
      <div class="text-h6 text-weight-bold">业务数据仪表盘</div>
      <q-seperator></q-seperator>
    </div>

    <div class="row q-col-gutter-md q-mb-md">
      <div v-for="stat in stats" :key="stat.title" class="col-12 col-sm-6 col-md-3">
        <q-card flat class="text-white" :style="{ background: stat.color }">
          <q-card-section>
            <div class="text-subtitle2">{{ stat.title }}</div>
            <div class="text-h4 text-weight-bolder">{{ stat.value }}</div>
            <div class="text-caption">较昨日 {{ stat.diff }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-8">
        <q-card flat bordered class="full-height">
          <q-card-section>
            <div class="text-h6 text-weight-bold q-mb-md">销售趋势图</div>
        <SalesTrendChart ref="salesChartRef" />
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-4">
        <q-card flat bordered class="full-height">
          <q-card-section>
            <div class="text-h6 text-weight-bold q-mb-md">产品销售占比</div>
            <product-sales-pie-chart/>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import SalesTrendChart from 'src/components/charts/ProductOrderChart.vue'
import ProductSalesPieChart from 'src/components/charts/ProductSalesPieChart.vue'
import { productOrderReportApi } from 'src/api/reports';

// 引用子组件，用于手动调用子组件的 loadData 方法
const salesChartRef = ref(null)

// 模拟概览数据
const stats = ref([
  { title: '今日销售额', value: '￥0', diff: '+0%', color: 'linear-gradient(135deg, #1976D2 0%, #2196F3 100%)' },
  { title: '新增订单', value: '0', diff: '+0%', color: 'linear-gradient(135deg, #26A69A 0%, #4DB6AC 100%)' },
])

onMounted(() => {
  loadData();
});

const loadData = async () => {
 // 页面加载后的逻辑
  let resp =await productOrderReportApi.getSalesTodayData();
  stats.value[0].value =resp.data.today.amount;//金额
  stats.value[0].diff =resp.data.change_percent.amount;//百分比
  stats.value[1].value =resp.data.today.quantity;//数量
  stats.value[1].diff =resp.data.change_percent.quantity;//百分比
}
</script>

<style lang="scss" scoped>
// 保证卡片在不同高度下的一致性
.full-height {
  min-height: 385px; // 匹配 SalesTrendChart 的大概高度
}
</style>
