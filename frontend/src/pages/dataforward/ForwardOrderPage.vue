<template>
  <div class="q-pa-md">
    <q-card flat bordered class="my-card">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="text-h6">数据中转记录管理</div>
        <q-space />
        <q-input
          outlined
          dense
          v-model="filter.source_order_no"
          label="原始订单号"
          class="col-12 col-sm-3"
          clearable
        />
        <q-select
          outlined
          dense
          v-model="filter.forward_status"
          :options="statusOptions"
          label="中转状态"
          class="col-12 col-sm-2"
          emit-value
          map-options
          clearable
        />
        <q-btn color="primary" icon="search" label="搜索" @click="fetchData" />
        <q-btn icon="refresh" label="刷新" color="secondary" flat @click="fetchData" />
      </q-card-section>

      <q-separator />

      <q-table
        flat
        :rows="rows"
        :columns="columns"
        row-key="id"
        selection="multiple"
        v-model:selected="selectedRecords"
        :loading="loading"
        v-model:pagination="pagination"
        @request="fetchData"
      >
        <template v-slot:body-cell-forward_status="props">
          <q-td :props="props">
            <q-chip
              :color="getStatusColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ getStatusLabel(props.value) }}
            </q-chip>
            <q-tooltip v-if="props.row.forward_error" class="bg-red">
              {{ props.row.forward_error }}
            </q-tooltip>
          </q-td>
        </template>

        <template v-slot:body-cell-callback_status="props">
          <q-td :props="props">
            <q-chip
              :color="getCallbackColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ getCallbackLabel(props.value) }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props" class="q-gutter-xs">
            <q-btn
              size="sm"
              color="info"
              round
              icon="visibility"
              @click="showDetail(props.row)"
            >
              <q-tooltip>查看详情</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.forward_status != 1"
              size="sm"
              color="orange"
              round
              icon="refresh"
              @click="retryPush(props.row)"
              :loading="props.row.pushing"
            >
              <q-tooltip>重新中转</q-tooltip>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <!-- 详情对话框 -->
    <q-dialog v-model="detailDialog" full-width>
      <q-card>
        <q-card-section>
          <div class="text-h6">数据中转详情 #{{ detailData.id }}</div>
        </q-card-section>
        <q-card-section>
          <q-markup-table flat bordered>
            <tbody>
              <tr><td class="text-right text-weight-medium">原始订单号</td><td>{{ detailData.source_order_no }}</td></tr>
              <tr><td class="text-right text-weight-medium">源渠道PID</td><td>{{ detailData.source_pid }}</td></tr>
              <tr><td class="text-right text-weight-medium">目标渠道PID</td><td>{{ detailData.target_pid }}</td></tr>
              <tr><td class="text-right text-weight-medium">中转状态</td><td><q-chip :color="getStatusColor(detailData.forward_status)" text-color="white" dense>{{ getStatusLabel(detailData.forward_status) }}</q-chip></td></tr>
              <tr><td class="text-right text-weight-medium">错误信息</td><td>{{ detailData.forward_error || '无' }}</td></tr>
              <tr><td class="text-right text-weight-medium">回调状态</td><td><q-chip :color="getCallbackColor(detailData.callback_status)" text-color="white" dense>{{ getCallbackLabel(detailData.callback_status) }}</q-chip></td></tr>
              <tr><td class="text-right text-weight-medium">重试次数</td><td>{{ detailData.retry_count }}</td></tr>
              <tr><td class="text-right text-weight-medium">创建时间</td><td>{{ detailData.created_at }}</td></tr>
              <tr><td class="text-right text-weight-medium">更新时间</td><td>{{ detailData.updated_at }}</td></tr>
              <tr v-if="detailData.source_data">
                <td class="text-right text-weight-medium">原始数据</td>
                <td><pre class="bg-grey-2 q-pa-sm" style="max-height:200px;overflow:auto;">{{ JSON.stringify(detailData.source_data, null, 2) }}</pre></td>
              </tr>
              <tr v-if="detailData.transformed_data">
                <td class="text-right text-weight-medium">转换后数据</td>
                <td><pre class="bg-grey-2 q-pa-sm" style="max-height:200px;overflow:auto;">{{ JSON.stringify(detailData.transformed_data, null, 2) }}</pre></td>
              </tr>
              <tr v-if="detailData.target_response">
                <td class="text-right text-weight-medium">目标系统响应</td>
                <td><pre class="bg-grey-2 q-pa-sm" style="max-height:200px;overflow:auto;">{{ JSON.stringify(detailData.target_response, null, 2) }}</pre></td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="关闭" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()

const rows = ref([])
const loading = ref(false)
const selectedRecords = ref([])
const detailDialog = ref(false)
const detailData = ref({})

const filter = reactive({
  source_order_no: '',
  forward_status: null
})

const pagination = ref({
  sortBy: 'id',
  descending: true,
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0
})

const statusOptions = [
  { label: '待处理', value: 0 },
  { label: '成功', value: 1 },
  { label: '失败', value: 2 }
]

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'center' },
  { name: 'source_order_no', label: '原始订单号', field: 'source_order_no', align: 'left' },
  { name: 'source_pid', label: '源渠道', field: 'source_pid', align: 'center' },
  { name: 'target_pid', label: '目标渠道', field: 'target_pid', align: 'center' },
  { name: 'forward_status', label: '中转状态', field: 'forward_status', align: 'center' },
  { name: 'callback_status', label: '回调状态', field: 'callback_status', align: 'center' },
  { name: 'retry_count', label: '重试次数', field: 'retry_count', align: 'center' },
  { name: 'created_at', label: '创建时间', field: 'created_at', align: 'center' },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' }
]

const fetchData = async (props) => {
  loading.value = true
  try {
    const { page, rowsPerPage } = props?.pagination || pagination.value
    const params = {
      page,
      limit: rowsPerPage,
      source_order_no: filter.source_order_no,
      forward_status: filter.forward_status
    }

    const res = await api.get('/api/v1/forward-orders', { params })
    rows.value = (res.data.data?.data || []).map(item => ({ ...item, pushing: false }))
    pagination.value.rowsNumber = res.data.data?.total || 0
    pagination.value.page = page
    pagination.value.rowsPerPage = rowsPerPage
  } catch (error) {
    console.log('加载数据失败', error)
    $q.notify({ type: 'negative', message: '加载数据失败' })
  } finally {
    loading.value = false
  }
}

const getStatusColor = (status) => {
  const map = { 0: 'grey', 1: 'positive', 2: 'negative' }
  return map[status] || 'black'
}

const getStatusLabel = (status) => {
  const map = { 0: '待处理', 1: '成功', 2: '失败' }
  return map[status] || '未知'
}

const getCallbackColor = (status) => {
  const map = { 0: 'grey', 1: 'positive', 2: 'negative' }
  return map[status] || 'black'
}

const getCallbackLabel = (status) => {
  const map = { 0: '待回调', 1: '已回调', 2: '回调失败' }
  return map[status] || '未知'
}

const showDetail = async (row) => {
  try {
    const res = await api.get(`/api/v1/forward-orders/${row.id}`)
    if (res.data.code === 0) {
      detailData.value = res.data.data
      detailDialog.value = true
    }
  } catch {
    $q.notify({ type: 'negative', message: '加载详情失败' })
  }
}

const retryPush = async (row) => {
  row.pushing = true
  try {
    const res = await api.post(`/api/v1/forward-orders/${row.id}/retry`)
    if (res.data.code === 0) {
      $q.notify({ type: 'positive', message: '中转任务已重新派发' })
      fetchData()
    } else {
      $q.notify({ type: 'warning', message: res.data.msg || '操作失败' })
    }
  } catch {
    $q.notify({ type: 'negative', message: '请求失败' })
  } finally {
    row.pushing = false
  }
}

onMounted(() => fetchData())
</script>