<template>
  <div class="q-pa-md">
    <q-card flat bordered class="my-card">
      <q-card-section class="row q-col-gutter-sm items-center">
        <q-input
          outlined
          dense
          v-model="filter.mobile"
          label="手机号"
          class="col-12 col-sm-3"
          clearable
        />
        <q-select
          outlined
          dense
          v-model="filter.sync_status"
          :options="statusOptions"
          label="推送状态"
          class="col-12 col-sm-2"
          emit-value
          map-options
          clearable
        />
        <q-btn color="primary" icon="search" label="搜索" @click="fetchData" />
        <q-space />
        <q-btn
          color="negative"
          icon="send"
          label="批量推送失败项"
          :disable="!selectedRecords.length"
          @click="confirmBatchPush"
        />
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
        <template v-slot:body-cell-sync_status="props">
          <q-td :props="props">
            <q-chip
              :color="getStatusColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ getStatusLabel(props.value) }}
            </q-chip>
            <q-tooltip v-if="props.row.sync_error" class="bg-red">
              {{ props.row.sync_error }}
            </q-tooltip>
          </q-td>
        </template>
        <template v-slot:body-cell-order_status="props">
          <q-td :props="props">
            <q-chip
              :color="getOrderStatusColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ orderStatusLabel(props.value) }}
            </q-chip>
          </q-td>
        </template>
        <template v-slot:body-cell-cancel_sync_status="props">
          <q-td :props="props">
            <q-chip
              :color="getOrderStatusColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ getStatusLabel(props.value) }}
            </q-chip>
            <q-tooltip v-if="props.row.cancel_sync_error" class="bg-red">
              {{ props.row.cancel_sync_error }}
            </q-tooltip>
          </q-td>
        </template>
        <template v-slot:body-cell-settle_status="props">
          <q-td :props="props">
            <q-chip
              :color="getStatusColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ settleStatusLabel(props.value) }}
            </q-chip>
            <q-tooltip v-if="props.row.sync_error" class="bg-red">
              {{ props.row.sync_error }}
            </q-tooltip>
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props" class="q-gutter-xs">
            <q-btn
              v-if="props.row.sync_status != 1"
              size="sm"
              color="orange"
              round
              icon="refresh"
              @click="singlePush(props.row)"
              :loading="props.row.pushing"
            >
              <q-tooltip>重新推送</q-tooltip>
            </q-btn>
            <q-btn
              v-if="props.row.sync_status == 1 && props.row.order_status==1 && (props.row.cancel_sync_status==0 ||  props.row.cancel_sync_status==2)"
              size="sm"
              color="negative"
              round
              icon="face_retouching_off"
              @click="cancelPush(props.row)"
              :loading="props.row.pushing"
            >
              <q-tooltip>退订推送</q-tooltip>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()

// --- 响应式数据 ---
const rows = ref([])
const loading = ref(false)
const selectedRecords = ref([])
const filter = reactive({
  mobile: '',
  sync_status: null
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
  { label: '同步成功', value: 1 },
  { label: '同步失败', value: 2 }
]

const columns = [
  { name: 'id', label: 'ID', field: 'id',  },
  { name: 'organization', label: '公司', field: row => row.organization ? row.organization.short_name : '未知',  },
  { name: 'product', label: '产品', field: row => row.product ? row.product.name+'('+row.product.organization.short_name+')' : '未知',  },
  { name: 'mobile', label: '手机号', field: 'mobile', align: 'left' },
  { name: 'order_no', label: '订单号', field: 'order_no', align: 'left' },
  { name: 'total_amount', label: '金额', field: 'total_amount' },
  { name: 'order_status', label: '订单状态', field: 'order_status', align: 'center' },
  { name: 'settle_status', label: '结算状态', field: 'settle_status', align: 'center' },
  { name: 'sync_status', label: '订阅同步', field: 'sync_status', align: 'center' },
  { name: 'cancel_sync_status', label: '退订同步', field: 'cancel_sync_status', align: 'center' },
  { name: 'created_at', label: '创建时间', field: 'created_at' },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' }
]

// --- 逻辑方法 ---

const fetchData = async (props) => {
  loading.value = true
  try {
    const { page, rowsPerPage } = props?.pagination || pagination.value

    // 严谨规范：合并分页与搜索参数
    const params = {
      page,
      limit: rowsPerPage,
      mobile: filter.mobile,
      status: filter.sync_status
    }

    const response = await api.get('/api_v2/ThirdChannel/quanyuIndex', { params })
    console.log(response.data);
    rows.value = response.data.data.data.map(item => ({ ...item, pushing: false }))
    pagination.value.rowsNumber = response.data.data.total
    pagination.value.page = page
    pagination.value.rowsPerPage = rowsPerPage
  } catch (error) {
    console.log("加载数据失败",error)
    $q.notify({ type: 'negative', message: '加载数据失败' })
  } finally {
    loading.value = false
  }
}

const getStatusColor = (status) => {
  const map = { 0: 'grey', 1: 'positive', 2: 'negative' }
  return map[status] || 'black'
}
const getOrderStatusColor = (status) => {
  const map = { 1: 'grey', 0: 'positive' }
  return map[status] || 'black'
}

const orderStatusLabel = (status) => {
  const map = { 0: '正常', 1: '退订' }
  return map[status] || '未知'
}
const settleStatusLabel = (status) => {
  const map = { 0: '未结算', 1: '已结算'}
  return map[status] || '未知'
}
const getStatusLabel = (status) => {
  const map = { 0: '未推送', 1: '已成功', 2: '失败' }
  return map[status] || '未知'
}

// 单个重发
const singlePush = async (row) => {
  row.pushing = true
  try {
    // 对应你后端定义的 retry 路由
    const res = await api.post(`/api_v2/ThirdChannel/${row.id}/retryPush`)

    if (res.data.sync_status==1) {
      $q.notify({ type: 'positive', message: `订单 ${row.id} 推送成功` })
      fetchData() // 刷新列表
    } else {
      $q.notify({ type: 'warning', message: res.message })
    }
  } catch (error) {
    console.log("请求服务器错误",error);
    $q.notify({ type: 'negative', message: '请求服务器错误' })
  } finally {
    row.pushing = false
  }
}

//退订推送
const cancelPush = async (row) => {
  row.pushing = true
  try {
    // 对应你后端定义的 retry 路由
    const res = await api.post(`/api_v2/ThirdChannel/${row.id}/retryCancel`)

    if (res.data.sync_status==1) {
      $q.notify({ type: 'positive', message: `订单 ${row.id} 退订成功` })
      fetchData() // 刷新列表
    } else {
      $q.notify({ type: 'warning', message: res.message })
    }
  } catch (error) {
    console.log("请求服务器错误",error);
    $q.notify({ type: 'negative', message: '请求服务器错误' })
  } finally {
    row.pushing = false
  }
}

// 批量重发确认
const confirmBatchPush = () => {
  const targets = selectedRecords.value.filter(r => r.sync_status !== 1)
  if (targets.length === 0) {
    $q.notify({ message: '所选项中没有需要推送的记录', color: 'orange' })
    return
  }

  $q.dialog({
    title: '批量推送确认',
    message: `确定要为选中的 ${targets.length} 条失败订单重新推送吗？`,
    cancel: true,
    persistent: true
  }).onOk(() => {
    executeBatchPush(targets)
  })
}

// 执行批量推送
const executeBatchPush = async (targets) => {
  loading.value = true
  let successCount = 0

  for (const record of targets) {
    try {
      const res = await api.post(`/api_v2/ThirdChannel/${record.id}/retryPush`)
      if (res.data.sync_status==1) successCount++
    } catch (e) {
      /* 单个失败不中断循环 */
      console.log("",e)
    }
  }

  $q.notify({
    type: 'info',
    message: `批量操作完成：成功 ${successCount} 条，失败 ${targets.length - successCount} 条`
  })
  selectedRecords.value = []
  fetchData()
}

onMounted(() => fetchData())
</script>
