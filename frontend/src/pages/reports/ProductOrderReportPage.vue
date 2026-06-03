<template>
  <q-page class="q-pa-lg bg-grey-1">
    <div class="row items-center q-mb-xl">
      <div class="column">
        <div class="row items-center">
          <q-icon name="trending_up" color="primary" size="md" class="q-mr-sm" />
          <div class="text-h5 text-weight-bold text-grey-9">业绩简报</div>
        </div>
        <div class="text-caption text-grey-6 q-ml-md">数据更新于: {{ lastUpdateTime }}</div>
      </div>
      <q-space />
      <q-btn unelevated color="white" text-color="primary" icon="refresh" label="刷新报表"
             @click="fetchData" :loading="loading" class="shadow-sm border-primary" />
    </div>

    <div class="row q-col-gutter-lg">
      <div v-for="(item, key) in statsConfig" :key="key" class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="report-card" :class="{ 'report-card--loading': loading }">
          <q-card-section>
            <div class="row items-center justify-between q-mb-sm">
              <div class="text-subtitle2 text-grey-7">{{ item.label }}</div>
              <q-avatar :icon="item.icon" :color="item.color" text-color="white" size="32px" />
            </div>

            <div class="text-h4 text-weight-bolder text-grey-9 q-my-md">
              <template v-if="loading">
                <q-skeleton type="text" width="70%" />
              </template>
              <template v-else>
                {{ formatAmount(statsData[key]?.total_amt) }}
              </template>
            </div>

            <div class="row items-center justify-between q-mt-md">
              <div class="column">
                <div class="text-caption text-grey-6">订单数</div>
                <div class="text-weight-bold">{{ statsData[key]?.total_qty || 0 }}</div>
              </div>

              <div v-if="key !== 'total'" class="column items-end">
                <div class="text-caption text-grey-6">较{{ item.compareLabel }}</div>
                <div :class="getTrendClass(calculateTrend(key))" class="row items-center text-weight-bold">
                  <q-icon :name="getTrendIcon(calculateTrend(key))" size="xs" />
                  {{ calculateTrend(key) }}%
                </div>
              </div>
            </div>
          </q-card-section>

          <q-inner-loading :showing="loading">
            <q-spinner-soft-els color="primary" size="3em" />
          </q-inner-loading>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { productOrderReportApi } from 'src/api/reports'
import { useQuasar, date } from 'quasar'

const $q = useQuasar()
const loading = ref(false)
const lastUpdateTime = ref('-')

// 基础数据结构（包含对比所需的字段）
const statsData = ref({
  today: { total_qty: 0, total_amt: 0 },
  yesterday: { total_qty: 0, total_amt: 0 },
  week: { total_qty: 0, total_amt: 0 },
  last_week: { total_qty: 0, total_amt: 0 },
  month: { total_qty: 0, total_amt: 0 },
  last_month: { total_qty: 0, total_amt: 0 },
  total: { total_qty: 0, total_amt: 0 }
})

// 卡片 UI 配置映射
const statsConfig = {
  today: { label: '今日营收', compareLabel: '昨日', icon: 'today', color: 'blue-6' },
  week: { label: '本周营收', compareLabel: '上周', icon: 'date_range', color: 'purple-6' },
  month: { label: '本月营收', compareLabel: '上月', icon: 'calendar_month', color: 'orange-8' },
  total: { label: '累计总额', compareLabel: '', icon: 'account_balance_wallet', color: 'green-7' }
}

/**
 * 核心逻辑：计算趋势百分比
 * @param {string} key
 */
const calculateTrend = (key) => {
  const current = parseFloat(statsData.value[key]?.total_amt || 0)
  let previous = 0

  if (key === 'today') previous = parseFloat(statsData.value.yesterday?.total_amt || 0)
  if (key === 'week') previous = parseFloat(statsData.value.last_week?.total_amt || 0)
  if (key === 'month') previous = parseFloat(statsData.value.last_month?.total_amt || 0)

  if (previous === 0) return current > 0 ? 100 : 0
  const gap = ((current - previous) / previous) * 100
  return gap.toFixed(1)
}

// 样式与图标辅助函数
const getTrendClass = (val) => {
  if (val > 0) return 'text-positive'
  if (val < 0) return 'text-negative'
  return 'text-grey-6'
}

const getTrendIcon = (val) => {
  if (val > 0) return 'arrow_drop_up'
  if (val < 0) return 'arrow_drop_down'
  return 'horizontal_rule'
}

const formatAmount = (val) => {
  const num = parseFloat(val || 0)
  return '¥' + num.toLocaleString('zh-CN', { minimumFractionDigits: 2 })
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await productOrderReportApi.getProductOrderBriefing()
    // 注意：根据规范，如果 res 是 axios 响应对象，通常需要 res.data
    // 如果 productOrderReportApi 内部已经处理了 data 层，则直接赋值
    statsData.value = res
    lastUpdateTime.value = date.formatDate(Date.now(), 'YYYY-MM-DD HH:mm:ss')
  } catch (error) {
    console.log("加载失败",error);
    $q.notify({ type: 'negative', message: '数据加载失败' })
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchData())
</script>

<style lang="scss" scoped>
.report-card {
  border-radius: 12px;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.05);

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    border-color: var(--q-primary);
  }

  &--loading {
    border: none;
  }
}

.border-primary {
  border: 1px solid var(--q-primary);
}
</style>
