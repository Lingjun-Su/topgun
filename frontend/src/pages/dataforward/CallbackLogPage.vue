<template>
  <div class="q-pa-md">
    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="text-h6">回调日志查询</div>
        <q-space />
        <q-input
          outlined dense
          v-model="filter.channel_pid"
          label="渠道PID"
          class="col-12 col-sm-2"
          clearable
        />
        <q-select
          outlined dense
          v-model="filter.callback_type"
          :options="callbackTypeOptions"
          label="回调类型"
          class="col-12 col-sm-2"
          clearable
        />
        <q-select
          outlined dense
          v-model="filter.status"
          :options="statusOptions"
          label="处理状态"
          class="col-12 col-sm-2"
          emit-value map-options
          clearable
        />
        <q-input
          outlined dense
          v-model="filter.date_start"
          label="开始日期"
          type="date"
          class="col-12 col-sm-2"
          clearable
        />
        <q-input
          outlined dense
          v-model="filter.date_end"
          label="结束日期"
          type="date"
          class="col-12 col-sm-2"
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
        :loading="loading"
        v-model:pagination="pagination"
        @request="fetchData"
      >
        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-chip
              :color="getStatusColor(props.value)"
              text-color="white"
              dense
              class="text-weight-bold"
            >
              {{ getStatusLabel(props.value) }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-flow_direction="props">
          <q-td :props="props">
            <q-chip color="primary" text-color="white" dense outline>
              A → B → C
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-request_params="props">
          <q-td :props="props">
            <q-btn flat dense color="primary" icon="code" label="查看" @click="showParams(props.row)">
              <q-tooltip>查看请求参数</q-tooltip>
            </q-btn>
          </q-td>
        </template>

        <template v-slot:body-cell-process_result="props">
          <q-td :props="props">
            <q-btn
              v-if="props.value"
              flat dense color="info" icon="info" label="查看"
              @click="showResult(props.row)"
            >
              <q-tooltip>查看处理结果</q-tooltip>
            </q-btn>
            <span v-else class="text-grey">-</span>
          </q-td>
        </template>

        <template v-slot:body-cell-process_error="props">
          <q-td :props="props">
            <span v-if="props.value" class="text-red">
              {{ props.value.length > 60 ? props.value.substring(0, 60) + '...' : props.value }}
            </span>
            <span v-else class="text-grey">-</span>
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
          </q-td>
        </template>
      </q-table>
    </q-card>

    <!-- 详情对话框 -->
    <q-dialog v-model="detailDialog" full-width>
      <q-card style="max-width: 900px">
        <q-card-section class="row items-center">
          <div class="text-h6">回调日志详情 #{{ detailRow?.id }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-list bordered separator>
            <q-item>
              <q-item-section>
                <q-item-label caption>渠道PID</q-item-label>
                <q-item-label>{{ detailRow?.channel_pid }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>回调类型</q-item-label>
                <q-item-label>{{ detailRow?.callback_type }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>请求方法</q-item-label>
                <q-item-label>{{ detailRow?.request_method }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>处理状态</q-item-label>
                <q-chip :color="getStatusColor(detailRow?.status)" text-color="white" dense>
                  {{ getStatusLabel(detailRow?.status) }}
                </q-chip>
              </q-item-section>
            </q-item>
            <q-item>
              <q-item-section>
                <q-item-label caption>关联中转记录</q-item-label>
                <q-item-label>{{ detailRow?.forward_order_id ?? '-' }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>关联产品订单</q-item-label>
                <q-item-label>{{ detailRow?.product_order_id ?? '-' }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>接收时间</q-item-label>
                <q-item-label>{{ detailRow?.created_at }}</q-item-label>
              </q-item-section>
              <q-item-section>
                <q-item-label caption>处理时间</q-item-label>
                <q-item-label>{{ detailRow?.processed_at ?? '-' }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>

          <q-separator class="q-my-md" />

          <div class="text-subtitle2 text-grey-8 q-mb-sm">请求 URL</div>
          <q-input
            readonly
            dense
            outlined
            :model-value="detailRow?.request_url"
            class="q-mb-md"
          />

          <div class="text-subtitle2 text-grey-8 q-mb-sm">请求参数</div>
          <q-input
            type="textarea"
            readonly
            outlined
            :model-value="formatJson(detailRow?.request_params)"
            rows="8"
            class="q-mb-md"
          />

          <div v-if="detailRow?.raw_body" class="q-mb-md">
            <div class="text-subtitle2 text-grey-8 q-mb-sm">原始 Body</div>
            <q-input
              type="textarea"
              readonly
              outlined
              :model-value="detailRow.raw_body"
              rows="4"
            />
          </div>

          <div v-if="detailRow?.process_result" class="q-mb-md">
            <div class="text-subtitle2 text-grey-8 q-mb-sm">处理结果</div>
            <q-input
              type="textarea"
              readonly
              outlined
              :model-value="formatJson(detailRow.process_result)"
              rows="6"
            />
          </div>

          <div v-if="detailRow?.process_error" class="q-mb-md">
            <div class="text-subtitle2 text-red q-mb-sm">处理错误</div>
            <q-input
              type="textarea"
              readonly
              outlined
              :model-value="detailRow.process_error"
              rows="4"
            />
          </div>

          <div class="q-mb-md">
            <div class="text-subtitle2 text-grey-8 q-mb-sm">通知结果（B→C）</div>
            <q-chip
              :color="detailRow?.status === 1 ? 'positive' : detailRow?.status === 2 ? 'negative' : 'grey'"
              text-color="white"
              dense
            >
              {{ detailRow?.status === 1 ? '已通知推广方 C' : detailRow?.status === 2 ? '通知失败' : '待通知' }}
            </q-chip>
            <div class="text-caption text-grey-6 q-mt-xs">
              A 公司回调后，B 系统通过 FlowRouter 将处理结果通知给 C 公司（推广方）
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- 参数弹窗 -->
    <q-dialog v-model="paramsDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="row items-center">
          <div class="text-h6">请求参数</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-input type="textarea" readonly outlined :model-value="formatJson(paramsContent)" rows="12" />
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- 结果弹窗 -->
    <q-dialog v-model="resultDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="row items-center">
          <div class="text-h6">处理结果</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-input type="textarea" readonly outlined :model-value="formatJson(resultContent)" rows="10" />
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { api } from 'boot/axios'

const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'desc', descending: false, page: 1, rowsPerPage: 15, rowsNumber: 0 })
const filter = reactive({ channel_pid: '', callback_type: '', status: '', date_start: '', date_end: '' })

const detailDialog = ref(false)
const detailRow = ref(null)
const paramsDialog = ref(false)
const paramsContent = ref(null)
const resultDialog = ref(false)
const resultContent = ref(null)

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'channel_pid', label: '渠道PID', field: 'channel_pid', align: 'left' },
  { name: 'callback_type', label: '回调类型', field: 'callback_type', align: 'left' },
  { name: 'flow_direction', label: '流向', field: 'flow_direction', align: 'center' },
  { name: 'request_method', label: '方法', field: 'request_method', align: 'center' },
  { name: 'status', label: '状态', field: 'status', align: 'center' },
  { name: 'request_params', label: '请求参数', field: 'request_params', align: 'center' },
  { name: 'forward_order_id', label: '中转记录', field: 'forward_order_id', align: 'center' },
  { name: 'process_result', label: '处理结果', field: 'process_result', align: 'center' },
  { name: 'process_error', label: '错误信息', field: 'process_error', align: 'left' },
  { name: 'created_at', label: '接收时间', field: 'created_at', align: 'left', sortable: true },
  { name: 'actions', label: '操作', field: 'actions', align: 'center' },
]

const callbackTypeOptions = [
  { label: '省份订单结果回调', value: 'province_order_result' },
]

const statusOptions = [
  { label: '待处理', value: 0 },
  { label: '已处理', value: 1 },
  { label: '处理失败', value: 2 },
]

const getStatusColor = (val) => {
  if (val === 0) return 'orange'
  if (val === 1) return 'green'
  if (val === 2) return 'red'
  return 'grey'
}

const getStatusLabel = (val) => {
  if (val === 0) return '待处理'
  if (val === 1) return '已处理'
  if (val === 2) return '处理失败'
  return '未知'
}

const formatJson = (val) => {
  if (!val) return ''
  if (typeof val === 'string') {
    try { return JSON.stringify(JSON.parse(val), null, 2) } catch { return val }
  }
  return JSON.stringify(val, null, 2)
}

const fetchData = async (props) => {
  loading.value = true
  try {
    const { page, rowsPerPage } = props?.pagination || pagination.value
    const params = {
      page,
      per_page: rowsPerPage,
    }
    if (filter.channel_pid) params.channel_pid = filter.channel_pid
    if (filter.callback_type) params.callback_type = filter.callback_type
    if (filter.status !== '') params.status = filter.status
    if (filter.date_start) params.date_start = filter.date_start
    if (filter.date_end) params.date_end = filter.date_end

    // 数据源：v1 callback-logs（后端 T-11 后基于 forward_orders；契约见 接口文档 v1 受保护组）
    const res = await api.get('/api/v1/callback-logs', { params })
    rows.value = res.data.data.data
    pagination.value.rowsNumber = res.data.data.total
    pagination.value.page = page
    pagination.value.rowsPerPage = rowsPerPage
  } catch (e) {
    console.error('获取回调日志失败', e)
  } finally {
    loading.value = false
  }
}

const showDetail = (row) => {
  detailRow.value = row
  detailDialog.value = true
}

const showParams = (row) => {
  paramsContent.value = row.request_params
  paramsDialog.value = true
}

const showResult = (row) => {
  resultContent.value = row.process_result
  resultDialog.value = true
}
</script>