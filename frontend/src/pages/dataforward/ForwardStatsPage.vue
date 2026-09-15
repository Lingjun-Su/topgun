<template>
  <div class="q-pa-md">
    <div class="text-h5 q-mb-md">订单错误码统计</div>

    <!-- 筛选区 -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-end q-gutter-sm">
        <q-input outlined dense v-model="filters.startDate" label="开始日期" type="date" class="col-12 col-sm-2" />
        <q-input outlined dense v-model="filters.endDate" label="结束日期" type="date" class="col-12 col-sm-2" />
        <q-select
          outlined
          dense
          v-model="filters.businessId"
          label="业务"
          :options="businessOptions"
          option-value="id"
          option-label="name"
          clearable
          emit-value
          map-options
          class="col-12 col-sm-2"
          @update:model-value="onBusinessChange"
        />
        <q-select
          outlined
          dense
          v-model="filters.productId"
          label="产品"
          :options="productOptions"
          option-value="id"
          option-label="optionLabel"
          clearable
          emit-value
          map-options
          class="col-12 col-sm-2"
        />
        <q-btn icon="search" label="查询" color="primary" :loading="loading" @click="fetchAll" />
        <q-btn icon="restart_alt" label="重置" flat @click="resetFilters" />
      </q-card-section>
    </q-card>

    <!-- 概览卡片 -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">请求总数</div>
            <div class="text-h4 text-primary">{{ summary.request_total }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">验证码请求成功 / 失败</div>
            <div class="text-h5">
              <span class="text-positive">{{ summary.verify_success }}</span>
              <span class="text-grey"> / </span>
              <span class="text-negative">{{ summary.verify_failed }}</span>
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">最终成交 / 失败</div>
            <div class="text-h5">
              <span class="text-positive">{{ summary.deal_success }}</span>
              <span class="text-grey"> / </span>
              <span class="text-negative">{{ summary.deal_failed }}</span>
            </div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-center">
            <div class="text-grey-8 text-subtitle2">最终成交率</div>
            <div class="text-h4 text-orange">{{ dealRate }}%</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <!-- 每日汇总表 -->
      <div class="col-12">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">每日汇总</div>
          </q-card-section>
          <q-separator />
          <q-table
            :rows="dailyRows"
            :columns="dailyColumns"
            row-key="date"
            flat
            dense
            :loading="loading"
            :pagination="pagination"
            hide-bottom
          >
            <template v-slot:body-cell-verify="props">
              <q-td :props="props">
                <span class="text-positive">{{ props.row.verify_success }}</span>
                <span class="text-grey"> / </span>
                <span class="text-negative">{{ props.row.verify_failed }}</span>
              </q-td>
            </template>
            <template v-slot:body-cell-deal="props">
              <q-td :props="props">
                <span class="text-positive">{{ props.row.deal_success }}</span>
                <span class="text-grey"> / </span>
                <span class="text-negative">{{ props.row.deal_failed }}</span>
              </q-td>
            </template>
            <template v-slot:body-cell-rate="props">
              <q-td :props="props">
                <span :class="props.row.dealRate >= 50 ? 'text-positive' : 'text-negative'">{{ props.row.dealRate }}%</span>
              </q-td>
            </template>
            <template v-slot:no-data>
              <div class="q-pa-md text-grey-6">暂无数据</div>
            </template>
          </q-table>
        </q-card>
      </div>

      <!-- 错误码分布 -->
      <div class="col-12">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">错误码分布</div>
          </q-card-section>
          <q-separator />
          <q-card-section>
            <div v-if="aGroups.length === 0" class="text-center text-grey-5 q-py-xl">暂无失败记录</div>
            <template v-else>
              <!-- 每渠道独立一张表 -->
              <div v-for="a in aGroups" :key="a.target_pid" class="q-mb-xl">
                <!-- 渠道标题 -->
                <div class="row items-center q-mt-sm q-mb-xs">
                  <div class="text-body2 text-weight-bold">{{ a.channel_name }} <span class="text-grey-6 text-caption">({{ a.target_pid }})</span></div>
                </div>
                <q-separator class="q-mb-sm" />
                <q-table
                  :rows="a.rows"
                  :columns="errorColumns"
                  row-key="key"
                  flat
                  dense
                  :pagination="pagination"
                  class="full-width"
                >
                  <template v-slot:body-cell-action="props">
                    <q-td :props="props">
                      <span :style="{ color: props.row.step === 1 ? '#1976d2' : '#03a9f4', fontWeight: 500 }">
                        {{ stepLabel(props.row.step) }}
                      </span>
                    </q-td>
                  </template>
                  <template v-slot:body-cell-ratio="props">
                    <q-td :props="props">
                      <span :class="props.row.ratio >= 50 ? 'text-negative' : 'text-grey-7'">{{ props.row.ratio }}%</span>
                    </q-td>
                  </template>
                  <template v-slot:no-data>
                    <div class="q-pa-md text-grey-6">无错误记录</div>
                  </template>
                </q-table>
              </div>
            </template>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ForwardStatsApi } from 'src/api/forwardStats'
import { businessApi, productApi } from 'src/api/modules'

const loading = ref(false)
const dailyData = ref([])
const errorData = ref([])
const businessOptions = ref([])
const productOptions = ref([])

// 日期格式化工具
const fmtDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const daysAgo = (n) => { const d = new Date(); d.setDate(d.getDate() - n); return fmtDate(d) }
const todayStr = () => fmtDate(new Date())

// 默认查询近 7 天
const defaultFilters = () => ({ startDate: daysAgo(6), endDate: todayStr(), businessId: null, productId: null })

const filters = ref(defaultFilters())

const pagination = { rowsPerPage: 0 }

const dailyColumns = [
  { name: 'date', label: '日期', field: 'date', align: 'left' },
  { name: 'req', label: '请求数', field: 'request_total', align: 'right' },
  { name: 'verify', label: '验证码成功/失败', field: 'verify', align: 'right' },
  { name: 'deal', label: '成交/失败', field: 'deal', align: 'right' },
  { name: 'rate', label: '成交率', field: 'rate', align: 'right' }
]

// 每渠道错误码表格列
const errorColumns = [
  { name: 'error_code', label: '业务错误码', field: 'error_code', align: 'left' },
  { name: 'sub_error_code', label: '子错误码', field: 'sub_error_code', align: 'left' },
  { name: 'error_msg', label: '错误码说明', field: 'error_msg', align: 'left' },
  { name: 'action', label: '动作', field: 'action', align: 'center' },
  { name: 'request_total', label: '请求数', field: 'request_total', align: 'right' },
  { name: 'count', label: '错误数', field: 'count', align: 'right' },
  { name: 'ratio', label: '错误比例', field: 'ratio', align: 'right' }
]

const dailyRows = computed(() => {
  return dailyData.value.map(row => ({
    ...row,
    dealRate: row.request_total ? Math.round((row.deal_success / row.request_total) * 100) : 0
  }))
})

const summary = computed(() => {
  return dailyData.value.reduce((acc, row) => {
    acc.request_total += row.request_total
    acc.verify_success += row.verify_success
    acc.verify_failed += row.verify_failed
    acc.deal_success += row.deal_success
    acc.deal_failed += row.deal_failed
    return acc
  }, { request_total: 0, verify_success: 0, verify_failed: 0, deal_success: 0, deal_failed: 0 })
})

const dealRate = computed(() => {
  const req = summary.value.request_total
  return req ? Math.round((summary.value.deal_success / req) * 100) : 0
})

// 错误码分布：每渠道独立表格，行为逐错误码/子码
const aGroups = computed(() => {
  return errorData.value.map(a => ({
    ...a,
    rows: (a.rows || []).map(code => ({
      ...code,
      key: `${a.target_pid}-${code.step}-${code.error_code}-${code.sub_error_code || ''}`,
      sub_error_code: code.sub_error_code || '-',
      ratio: code.ratio ?? 0
    }))
  }))
})

const stepLabel = (step) => (step === 0 ? '请求验证码' : step === 1 ? '提交订单' : `步骤${step}`)

const buildParams = () => {
  const params = {}
  if (filters.value.startDate) params.start_date = filters.value.startDate
  if (filters.value.endDate) params.end_date = filters.value.endDate
  if (filters.value.productId) params.product_id = filters.value.productId
  return params
}

const fetchAll = async () => {
  loading.value = true
  try {
    const params = buildParams()
    const [dailyRes, errRes] = await Promise.all([
      ForwardStatsApi.daily(params),
      ForwardStatsApi.errorCodes(params)
    ])
    dailyData.value = dailyRes.data?.daily || []
    errorData.value = errRes.data?.by_a || []
  } catch (error) {
    console.log('获取统计失败', error)
  } finally {
    loading.value = false
  }
}

// 加载业务下拉
const loadBusinesses = async () => {
  try {
    const res = await businessApi.list({ per_page: 200 })
    businessOptions.value = res.data?.data || []
  } catch (e) {
    console.log('加载业务失败', e)
  }
}

// 业务变更 → 联动加载该业务下产品，并清空已选产品
const onBusinessChange = async () => {
  filters.value.productId = null
  productOptions.value = []
  if (!filters.value.businessId) return
  try {
    const res = await productApi.listByBusiness(filters.value.businessId)
    const list = res.data?.data || []
    productOptions.value = list.map(p => ({
      ...p,
      optionLabel: `${p.name} (${p.sku_code})`
    }))
  } catch (e) {
    console.log('加载产品失败', e)
  }
}

const resetFilters = () => {
  filters.value = defaultFilters()
  productOptions.value = []
  fetchAll()
}

onMounted(async () => {
  fetchAll()
  await loadBusinesses()
})
</script>