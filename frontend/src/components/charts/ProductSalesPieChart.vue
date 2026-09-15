<template>
  <!-- 引入 ChartCard 组件，它封装了 Quasar 卡片容器、加载、错误和无数据状态的通用逻辑 -->
  <chart-card
    title="近一周产品销量占比"
    :loading="loading"
    :no-data="!loading && series.length === 0"
    @refresh="loadData"
    class="q-pt-lx"
  >
    <div class="q-mt-lg">
      <!-- 饼图渲染区域 -->
      <!-- 仅当数据加载完成且有数据时才渲染图表 -->
        <apexchart
          v-if="!loading && series.length > 0"
          type="pie"
          height="350"
          :options="chartOptions"
          :series="series"
        ></apexchart>
    </div>
  </chart-card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
// 导入报表相关的 API 模块
import { productOrderReportApi } from 'src/api/reports';
// 导入 ChartCard 组件，这是一个自定义的封装组件，用于统一图表卡的UI和状态处理
import ChartCard from './ChartCard.vue'; // 确保路径正确

// 定义响应式数据
const loading = ref(false); // 控制数据加载状态，初始为 false，在加载时设为 true
const series = ref([]); // 饼图数据系列，表示各产品销量占比，例如：[44, 55, 13, 43, 22]
const labels = ref([]); // 存储产品名称，用于图表的标签

// 饼图的配置项，包括图表类型、标题、标签、颜色等
const chartOptions = ref({
    chart: {
    id: 'product-sales-pie', // 唯一ID
      type: 'pie',
      height: 350,
    toolbar: { show: false }, // 显示工具栏，允许导出等操作
    zoom: { enabled: false } // 禁用缩放
      },
  labels: labels.value, // 饼图的扇区标签，对应产品名称，初始为空
    colors: ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0', '#8d5b83', '#66DA26', '#E91E63', '#FF9800'], // 自定义饼图扇区颜色
  dataLabels: {
    enabled: false, // 启用数据标签
    formatter: function (val, { seriesIndex, w }) {
      // 自定义数据标签格式，显示产品名称和百分比
      const productName = w.config.labels[seriesIndex];
      return `${productName}: ${val.toFixed(1)}%`; // 保留一位小数
    },
    style: {
      fontSize: '13px',
      colors: ['#333'] // 标签文字颜色
    },
    dropShadow: {
      enabled: true, // 启用阴影，增加可读性
      top: 1,
      left: 1,
      blur: 1,
      color: '#000',
      opacity: 0.45
    }
  },
  tooltip: {
    theme: 'light', // 工具提示主题
    y: {
      formatter: function (val) {
        return `${val.toFixed(2)}%`; // 鼠标悬停时显示百分比，保留两位小数
      },
    },
  },
  legend: {
    position: 'right', // 图例显示在右侧
    offsetX: -10, // 图例X轴偏移
    offsetY: 0, // 图例Y轴偏移
    itemMargin: {
      horizontal: 5,
      vertical: 5
    },
    fontSize: '13px' // 图例字体大小
  },
  title: { // 取消饼图面上的标题
    text: undefined
  },
  stroke: {
    width: 0 // 去掉饼图扇区之间的描边
  },
  responsive: [
    {
      breakpoint: 480, // 在屏幕宽度小于480px时，调整图表布局
      options: {
        chart: {
          width: 280,
        },
        legend: {
          position: 'bottom', // 图例移至底部
        },
      },
    },
  ],
});
/**
 * @method loadData
 * @description 从后端 API 获取近一周产品销量数据，并处理为 ApexCharts 饼图所需的 series 和 options 格式。
 *              实现数据加载、错误处理、无数据处理等逻辑。
 */
const loadData = async () => {
  loading.value = true; // 开始加载数据，设置 loading 为 true
  series.value = []; // 清空数据系列
  labels.value = []; // 清空标签
  try {
    // 调用 api/report.js 中定义的接口获取数据
    const response = await productOrderReportApi.getProductOrderSalesPieChart();

    const rawData = response.data; // 根据实际后端返回结构调整，这里假设数据在 response.data 中

    if (rawData && rawData.length > 0) {
      // 提取产品名称作为图表标签
      labels.value = rawData.map(item => item.product_name);
      // 提取每个产品的销量百分比，并格式化为 ApexCharts 所需的 series
      // 假设后端直接返回了 percentage 字段
      series.value = rawData.map(item => item.percentage || 0);

      // 更新图表配置项的 labels
      chartOptions.value = {
        ...chartOptions.value, // 保持其他配置不变
        labels: labels.value, // 更新 labels
      };
    } else {
      // 如果后端没有返回数据，清空图表配置
      chartOptions.value = {
        ...chartOptions.value,
        labels: [],
      };
    }
  } catch (err) {
    console.error('加载产品销量占比数据失败:', err);
    // 这里可以添加更详细的错误处理，例如显示 Quasar 通知
  } finally {
    loading.value = false; // 数据加载完成或出现错误，设置 loading 为 false
  }
};

// 组件挂载时调用 loadData 获取数据
onMounted(() => {
  loadData();
});
</script>

<style lang="scss" scoped>
/*
  由于引入了 ChartCard 组件来封装卡片的UI和状态逻辑，
  原有的 .product-sales-pie-chart-card 和 .chart-container 样式不再直接在此处管理。
  ChartCard 组件应该包含自己的布局和样式。
  如果需要针对此特定图表进行样式调整，可以在这里添加。
*/
</style>

