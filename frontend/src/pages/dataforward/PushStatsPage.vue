<template>
  <div class="q-pa-md">
    <div class="text-h5 q-mb-md">推送统计仪表盘</div>

    <!-- 时间筛选 -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center q-gutter-sm">
        <q-select
          outlined
          dense
          v-model="days"
          :options="[7, 14, 30]"
          label="统计周期"
          class="col-12 col-sm-2"
          emit-value
          map-options
          :option-label="(opt) => opt + '天'"
          @update:model-value="fetchStats"
        />
        <q-space />
        <q-btn icon="refresh" label="刷新" color="primary" flat @click="fetchStats" />
      </q-card-section>
    </q-card>

    <!-- 统计概览卡片（数据源：forward_orders） -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">推送总量</div>
            <div class="text-h4 text-primary">{{ stats.forward_stats?.total || 0 }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">推送成功</div>
            <div class="text-h4 text-positive">{{ stats.forward_stats?.success || 0 }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">推送失败</div>
            <div class="text-h4 text-negative">{{ stats.forward_stats?.failed || 0 }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">中转成功率</div>
            <div class="text-h4 text-orange">{{ successRate }}%</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <!-- 每日推送趋势 -->
      <div class="col-12 col-md-8">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">每日推送趋势</div>
          </q-card-section>
          <q-separator />
          <q-card-section style="height: 300px;">
            <div v-if="dailyTrend.length === 0" class="text-center text-grey-5" style="padding-top: 100px;">
              暂无数据
            </div>
            <div v-else class="row items-end" style="height: 250px; gap: 4px;">
              <div v-for="item in dailyTrend" :key="item.date" class="column items-center" style="flex: 1; height: 100%;">
                <div class="row items-end" style="height: 200px; gap: 2px;">
                  <div
                    class="bg-positive rounded-borders"
                    style="width: 12px;"
                    :style="{ height: getBarHeight(item.success, maxDaily) + '%' }"
                  ></div>
                  <div
                    class="bg-negative rounded-borders"
                    style="width: 12px;"
                    :style="{ height: getBarHeight(item.failed, maxDaily) + '%' }"
                  ></div>
                </div>
                <div class="text-caption text-grey-7" style="writing-mode: vertical-lr; font-size: 10px;">
                  {{ item.date.slice(5) }}
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- 失败原因分布 -->
      <div class="col-12 col-md-4">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">失败原因分布</div>
          </q-card-section>
          <q-separator />
          <q-card-section>
            <div v-if="failReasons.length === 0" class="text-center text-grey-5 q-py-xl">
              暂无失败记录
            </div>
            <div v-else v-for="(item, index) in failReasons" :key="index" class="q-mb-sm">
              <div class="row items-center">
                <div class="col-8 text-caption text-grey-8 ellipsis">{{ item.sync_error }}</div>
                <div class="col-4 text-right text-weight-medium">{{ item.count }}次</div>
              </div>
              <q-linear-progress :value="item.count / maxFailReason" color="negative" class="q-mt-xs" />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()
const stats = ref({})
const days = ref(7)

const dailyTrend = computed(() => {
  return stats.value.daily_trend || []
})

// 中转成功率（基于 forward_stats）
const successRate = computed(() => {
  const s = stats.value.forward_stats || {}
  const total = s.total || 0
  if (!total) return 0
  return Math.round(((s.success || 0) / total) * 100)
})

const failReasons = computed(() => {
  return stats.value.fail_reasons || []
})

const maxDaily = computed(() => {
  const items = dailyTrend.value
  if (items.length === 0) return 1
  return Math.max(...items.map(i => Math.max(i.total, 1)))
})

const maxFailReason = computed(() => {
  const items = failReasons.value
  if (items.length === 0) return 1
  return Math.max(...items.map(i => i.count))
})

const getBarHeight = (value, max) => {
  return Math.max((value / max) * 100, 2)
}

const fetchStats = async () => {
  try {
    const res = await api.get('/api/v1/push-stats/overview', { params: { days: days.value } })
    if (res.data.code === 200) {
      stats.value = res.data.data
    }
  } catch (error) {
    console.log('获取统计失败', error)
    $q.notify({ type: 'negative', message: '获取统计数据失败' })
  }
}

onMounted(fetchStats)
</script>