<template>
  <div class="q-pa-md">
    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="text-h6">停止信息查询</div>
        <q-space />
        <q-select
          outlined dense
          v-model="filter.channel_pid"
          :options="channelOptions"
          label="渠道"
          class="col-12 col-sm-2"
          emit-value map-options
          clearable
          :loading="channelsLoading"
        />
        <q-input outlined dense v-model="filter.mobile" label="手机号" class="col-12 col-sm-2" clearable />
        <q-input outlined dense v-model="filter.source_order_no" label="订单号" class="col-12 col-sm-2" clearable />
        <q-select
          outlined dense
          v-model="filter.step"
          :options="stepOptions"
          label="生效环节"
          class="col-12 col-sm-2"
          emit-value map-options
          clearable
        />
        <q-select
          outlined dense
          v-model="filter.condition_type"
          :options="conditionTypeOptions"
          label="条件类型"
          class="col-12 col-sm-2"
          emit-value map-options
          clearable
        />
        <q-input outlined dense v-model="filter.date_start" label="开始日期" type="date" class="col-12 col-sm-2" clearable />
        <q-input outlined dense v-model="filter.date_end" label="结束日期" type="date" class="col-12 col-sm-2" clearable />
        <q-btn color="primary" icon="search" label="搜索" @click="onSearch" />
        <q-btn icon="refresh" label="刷新" color="secondary" flat @click="fetchData" />
      </q-card-section>

      <q-separator />

      <q-table
        flat
        :rows="rows"
        :columns="columns"
        row-key="id"
        :loading="loading"
        v-model:pagination="pagination"
        @request="fetchData"
      >
        <template v-slot:body-cell-step="props">
          <q-td :props="props">
            <q-chip :color="props.value === 'getCode' ? 'primary' : 'accent'" text-color="white" dense>
              {{ props.value }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-condition_type="props">
          <q-td :props="props">
            <q-chip color="negative" text-color="white" dense outline>
              {{ conditionTypeLabel(props.value) }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-rule_message="props">
          <q-td :props="props">
            <span :class="props.value.length > 40 ? 'text-grey-8' : ''">
              {{ truncate(props.value, 40) }}
            </span>
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn size="sm" color="info" round icon="visibility"
              @click="showDetail(props.row)">
              <q-tooltip>查看详情</q-tooltip>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <!-- 详情对话框 -->
    <q-dialog v-model="detailDialog" full-width>
      <q-card style="max-width: 900px">
        <q-card-section class="row items-center">
          <div class="text-h6">停止记录详情 #{{ detailRow?.id }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-list bordered separator>
            <q-item>
              <q-item-section>
                <q-item-label caption>渠道PID</q-item-label>
                <q-item-label>{{ detailRow?.source_pid ?? '-' }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>手机号</q-item-label>
                <q-item-label>{{ detailRow?.mobile ?? '-' }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>订单号</q-item-label>
                <q-item-label>{{ detailRow?.source_order_no ?? '-' }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>生效环节</q-item-label>
                <q-item-label>{{ detailRow?.step ?? '-' }}</q-item-label>
              </q-item-section>
            </q-item>
            <q-item>
              <q-item-section>
                <q-item-label caption>条件ID</q-item-label>
                <q-item-label>{{ detailRow?.condition_id ?? '-' }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>条件类型</q-item-label>
                <q-item-label>{{ conditionTypeLabel(detailRow?.condition_type) }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>触发时间</q-item-label>
                <q-item-label>{{ detailRow?.created_at ?? '-' }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 text-grey-8 q-mb-sm">拦截提示</div>
          <q-input readonly dense outlined :model-value="detailRow?.rule_message" class="q-mb-md" />

          <div class="text-subtitle2 text-grey-8 q-mb-sm">请求数据留痕</div>
          <q-input type="textarea" readonly outlined :model-value="formatJson(detailRow?.request_data)" rows="12" />
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { channelApi } from 'src/api/modules'

const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'desc', descending: false, page: 1, rowsPerPage: 15, rowsNumber: 0 })
const filter = reactive({ channel_pid: '', mobile: '', source_order_no: '', step: '', condition_type: '', date_start: '', date_end: '' })

const detailDialog = ref(false)
const detailRow = ref(null)

// 渠道下拉选项：value=pid, label=名称(PID)
const channelOptions = ref([])
const channelsLoading = ref(false)

// 条件类型注册表（用于显示类型中文名）
const typeRegistry = ref({})
const conditionTypeOptions = computed(() =>
  Object.entries(typeRegistry.value).map(([value, t]) => ({ label: t.label, value }))
)
const conditionTypeLabel = (t) => typeRegistry.value[t]?.label || t

const stepOptions = [
  { label: 'getCode', value: 'getCode' },
  { label: 'submit', value: 'submit' },
]

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'source_pid', label: '渠道PID', field: 'source_pid', align: 'left' },
  { name: 'mobile', label: '手机号', field: 'mobile', align: 'left' },
  { name: 'source_order_no', label: '订单号', field: 'source_order_no', align: 'left' },
  { name: 'step', label: '生效环节', field: 'step', align: 'center' },
  { name: 'condition_type', label: '条件类型', field: 'condition_type', align: 'center' },
  { name: 'rule_message', label: '拦截提示', field: 'rule_message', align: 'left' },
  { name: 'created_at', label: '触发时间', field: 'created_at', align: 'left', sortable: true },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' },
]

const truncate = (s, n) => (s && s.length > n ? s.substring(0, n) + '...' : s)

const formatJson = (val) => {
  if (!val) return ''
  if (typeof val === 'string') {
    try { return JSON.stringify(JSON.parse(val), null, 2) } catch { return val }
  }
  return JSON.stringify(val, null, 2)
}

const buildParams = (page, rowsPerPage) => {
  const params = { page, per_page: rowsPerPage }
  if (filter.channel_pid) params.channel_pid = filter.channel_pid
  if (filter.mobile) params.mobile = filter.mobile
  if (filter.source_order_no) params.source_order_no = filter.source_order_no
  if (filter.step) params.step = filter.step
  if (filter.condition_type) params.condition_type = filter.condition_type
  if (filter.date_start) params.date_start = filter.date_start
  if (filter.date_end) params.date_end = filter.date_end
  return params
}

const fetchData = async (props) => {
  loading.value = true
  try {
    const { page, rowsPerPage } = props?.pagination || pagination.value
    const res = await channelApi.forwardStopLogs(buildParams(page, rowsPerPage))
    rows.value = res.data.data || []
    pagination.value.rowsNumber = res.data.total || 0
    pagination.value.page = page
    pagination.value.rowsPerPage = rowsPerPage
  } catch (e) {
    console.error('获取停止记录失败', e)
  } finally {
    loading.value = false
  }
}

const onSearch = () => {
  pagination.value.page = 1
  fetchData()
}

const showDetail = (row) => {
  detailRow.value = row
  detailDialog.value = true
}

const loadChannels = async () => {
  channelsLoading.value = true
  try {
    const res = await channelApi.list({ per_page: 1000 })
    channelOptions.value = (res.data.data || []).map((c) => ({
      label: `${c.name || c.pid} (${c.pid})`,
      value: c.pid,
    }))
  } catch (e) {
    console.error('加载渠道列表失败', e)
  } finally {
    channelsLoading.value = false
  }
}

onMounted(async () => {
  try {
    const res = await channelApi.stopConditionTypes()
    typeRegistry.value = res?.data || {}
  } catch (e) {
    console.error('加载停止条件类型失败', e)
  }
  loadChannels()
  fetchData()
})
</script>