<template>
  <q-page class="q-pa-md bg-grey-1">
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="text-h6 text-primary">
          <q-icon name="assignment" size="sm" class="q-mr-sm" />
          产品订单管理
        </div>
        <q-space />

        <q-select
          v-model="visibleColumns"
          multiple
          outlined
          dense
          options-dense
          display-value="配置显示列"
          emit-value
          map-options
          :options="columnOptions"
          option-value="name"
          style="min-width: 200px"
          bg-color="white"
        >
          <template v-slot:before-options>
            <q-item>
              <q-item-section>
                <q-btn label="恢复默认" color="primary" flat dense @click="resetColumns" />
              </q-item-section>
            </q-item>
          </template>
        </q-select>
      </q-card-section>

      <q-separator />

      <q-card-section>
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-3">
            <BusinessProductSelector
              v-model="filterModel"
              :allowed-business-ids="allowedBusinessIds"
              :allowed-product-ids="allowedProductIds"
              @change="fetchData"
            />
          </div>
          <div class="col-12 col-md-2">
            <q-input v-model="filters.order_no" label="订单号" outlined dense clearable @clear="fetchData" />
          </div>
          <div class="col-12 col-md-2">
            <q-input v-model="filters.user_phone" label="会员手机" outlined dense clearable @clear="fetchData" />
          </div>
          <div class="col-12 col-md-2">
            <template v-if="channelDisabled">
              <q-input
                :model-value="channelDisplay"
                label="渠道"
                outlined
                dense
                readonly
                filled
                bg-color="grey-2"
              >
                <template v-slot:append>
                  <q-icon name="lock" color="grey-6" />
                </template>
              </q-input>
            </template>
            <q-select
              v-else
              v-model="filters.pid"
              :options="channelOptions"
              option-value="pid"
              option-label="name"
              label="渠道"
              outlined
              dense
              clearable
              emit-value
              map-options
              @clear="fetchData"
            />
          </div>
          <div class="col-12 col-md-1">
            <q-btn color="primary" icon="search" label="搜索" class="full-width" @click="fetchData" />
          </div>
        </div>
        <div class="row q-col-gutter-md q-mt-sm">
          <div class="col-12 col-md-3">
            <q-input v-model="dateDisplay" label="订购日期" dense outlined readonly>
              <template v-slot:append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="filters.dateRange" range mask="YYYY-MM-DD" @update:model-value="onDateChange" />
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-2">
            <q-select
              v-model="filters.order_status"
              :options="orderStatusOptions"
              option-value="value"
              option-label="label"
              label="订单状态"
              outlined
              dense
              clearable
              emit-value
              map-options
              @clear="fetchData"
              @update:model-value="fetchData"
            />
          </div>
          <div class="col-12 col-md-2">
            <q-select
              v-model="filters.sync_status"
              :options="syncStatusOptions"
              option-value="value"
              option-label="label"
              label="推送状态"
              outlined
              dense
              clearable
              emit-value
              map-options
              @clear="fetchData"
              @update:model-value="fetchData"
            />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <q-table
      flat
      bordered
      :rows="rows"
      :columns="allColumns"
      row-key="id"
      selection="multiple"
      v-model:selected="selected"
      :visible-columns="visibleColumns"
      :loading="loading"
      v-model:pagination="pagination"
      @request="onRequest"
      binary-state-sort
    >
      <template v-slot:body-cell-id="props">
        <q-td>
          <q-btn flat round size="sm" color="info" icon="visibility"  @click="showDetails(props.value)">
            <q-tooltip>查看详情</q-tooltip>
          </q-btn>
        </q-td>
      </template>
      <template v-slot:top-left>
        <q-btn-group outline>
          <q-btn color="secondary" icon="send" label="批量推送" :disable="!selected.length" @click="batchPush" />
          <q-btn color="primary" icon="file_download" label="导出" @click="exportExcel" />
          <q-btn color="info" icon="bar_chart" label="统计" @click="showStats" />
        </q-btn-group>
      </template>

      <template v-slot:body-cell-sync_status="props">
        <q-td :props="props">
          <q-badge :color="props.value === 1 ? 'positive' : (props.value === 2 ? 'negative' : 'warning')">
            {{ props.value === 1 ? '成功' : (props.value === 2 ? '失败' : '待处理') }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-order_status="props">
        <q-td :props="props">
          <q-badge :color="props.value === 1 ? 'positive' : 'warning'">
            {{ props.value === 1 ? '首订' : '未付款' }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-code="props">
        <q-td :props="props">
          {{ props.value!='' && props.value!=null?props.value:'****'}}
        </q-td>
      </template>

      <template v-slot:body-cell-total_amount="props">
        <q-td :props="props" class="text-weight-bold text-red">
          {{ props.value }}
        </q-td>
      </template>

      <template v-slot:body-cell-forward_log="props">
        <q-td :props="props">
          <q-btn
            v-if="props.value"
            flat dense size="sm" color="primary" icon="article"
            @click="showForwardLog(props.row)"
          >
            <q-tooltip>查看转发日志</q-tooltip>
          </q-btn>
          <span v-else class="text-grey-5">-</span>
        </q-td>
      </template>
    </q-table>

    <!-- 转发日志详情弹窗 -->
    <q-dialog v-model="forwardLogDialog.show" maximized>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">反向链路状态</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <!-- 反向链路概览 -->
          <div class="row q-col-gutter-md q-mb-md">
            <div class="col-12 col-md-4">
              <q-card flat bordered class="bg-grey-1">
                <q-card-section>
                  <div class="text-caption text-grey-7">B→A 推送状态</div>
                  <div class="q-mt-sm">
                    <q-chip :color="forwardLogDialog.syncStatus === 1 ? 'positive' : forwardLogDialog.syncStatus === 2 ? 'negative' : 'grey'"
                      text-color="white" dense>
                      {{ forwardLogDialog.syncStatus === 1 ? '已推送' : forwardLogDialog.syncStatus === 2 ? '推送失败' : forwardLogDialog.syncStatus === 0 ? '待推送' : forwardLogDialog.syncStatus === -1 ? '处理中' : '未知' }}
                    </q-chip>
                  </div>
                  <div class="text-caption text-grey-6 q-mt-xs" v-if="forwardLogDialog.pushedAt">
                    推送时间: {{ forwardLogDialog.pushedAt }}
                  </div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-12 col-md-4">
              <q-card flat bordered class="bg-grey-1">
                <q-card-section>
                  <div class="text-caption text-grey-7">A→B 回调状态</div>
                  <div class="q-mt-sm">
                    <q-chip :color="forwardLogDialog.hasNotifyLog ? 'positive' : 'grey'" text-color="white" dense>
                      {{ forwardLogDialog.hasNotifyLog ? '已收到回调' : '待回调' }}
                    </q-chip>
                  </div>
                  <div class="text-caption text-grey-6 q-mt-xs">
                    A 公司处理完成后回调通知 B 系统
                  </div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-12 col-md-4">
              <q-card flat bordered class="bg-grey-1">
                <q-card-section>
                  <div class="text-caption text-grey-7">B→C 通知状态</div>
                  <div class="q-mt-sm">
                    <q-chip :color="forwardLogDialog.hasNotifyLog ? 'positive' : 'grey'" text-color="white" dense>
                      {{ forwardLogDialog.hasNotifyLog ? '已通知推广方' : '待通知' }}
                    </q-chip>
                  </div>
                  <div class="text-caption text-grey-6 q-mt-xs">
                    B 系统通过 FlowRouter 将结果通知 C 公司
                  </div>
                </q-card-section>
              </q-card>
            </div>
          </div>

          <q-separator class="q-my-md" />
          <div class="text-subtitle2 text-grey-8 q-mb-sm">转发日志详情</div>
          <pre class="text-body2" style="white-space: pre-wrap; word-break: break-all; background: #f5f5f5; padding: 16px; border-radius: 4px; font-family: monospace;">{{ forwardLogDialog.content }}</pre>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- 订单状态统计弹窗 -->
    <q-dialog v-model="statsDialog.show">
      <q-card style="min-width: 600px; max-width: 800px;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">
            <q-icon name="bar_chart" class="q-mr-sm" />
            订单状态统计
          </div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section v-if="statsDialog.loading" class="text-center q-pa-lg">
          <q-spinner-dots size="40px" color="primary" />
          <div class="q-mt-sm text-grey-7">正在统计...</div>
        </q-card-section>
        <q-card-section v-else>
          <!-- 总览卡片 -->
          <div class="row q-col-gutter-md q-mb-lg">
            <div class="col-4">
              <q-card flat bordered class="text-center">
                <q-card-section>
                  <div class="text-h4 text-primary text-weight-bold">{{ statsDialog.data.total }}</div>
                  <div class="text-caption text-grey-7">订单总数</div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-4">
              <q-card flat bordered class="text-center">
                <q-card-section>
                  <div class="text-h4 text-positive text-weight-bold">{{ statsDialog.data.sent_code }}</div>
                  <div class="text-caption text-grey-7">已发送验证码</div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-4">
              <q-card flat bordered class="text-center">
                <q-card-section>
                  <div class="text-h4 text-negative text-weight-bold">{{ statsDialog.data.not_sent_code }}</div>
                  <div class="text-caption text-grey-7">未发送验证码</div>
                </q-card-section>
              </q-card>
            </div>
          </div>
          <!-- 已提交验证码 -->
          <div class="q-mb-lg">
            <q-card flat bordered>
              <q-card-section class="bg-primary text-white text-center">
                <div class="text-h5 text-weight-bold">{{ statsDialog.data.submitted }}</div>
                <div class="text-caption">已提交验证码</div>
              </q-card-section>
            </q-card>
          </div>
          <!-- 未发送验证码原因分析 -->
          <div class="text-subtitle1 text-weight-bold q-mb-sm">
            <q-icon name="info" class="q-mr-sm" />
            未发送验证码原因分析
          </div>
          <q-list bordered separator>
            <q-item v-for="(reason, idx) in statsDialog.data.error_reasons" :key="idx">
              <q-item-section>
                <q-item-label>{{ reason.label }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge :color="reason.count > 0 ? 'negative' : 'grey-5'" class="text-body2">
                  {{ reason.count }}
                </q-badge>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { productOrderApi } from 'src/api/productOrder'
import { channelApi } from 'src/api/modules'
import BusinessProductSelector from 'components/BusinessProductSelector.vue'
import { useAuthStore } from 'src/stores/auth'

// 默认当前月
const now = new Date()
const currYear = now.getFullYear()
const currMonth = String(now.getMonth() + 1).padStart(2, '0')
const firstDay = `${currYear}-${currMonth}-01`
const lastDay = new Date(currYear, now.getMonth() + 1, 0).getDate()
const lastDayStr = `${currYear}-${currMonth}-${String(lastDay).padStart(2, '0')}`

// import { route } from 'quasar/wrappers'
const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()

// --- 表格列定义 ---
const allColumns = [
  // 核心字段
  { name: 'id', label: 'ID', field: 'id', sortable: true, align: 'left' },
  { name: 'order_no', label: '订单号', field: 'order_no', sortable: true, align: 'left' },
  { name: 'user_phone', label: '会员手机', field: 'user_phone', align: 'left' },
  { name: 'code', label: '验证码', field: 'code', align: 'left' },
  { name: 'link_id', label: 'linkId', field: 'link_id', align: 'left' },
  { name: 'total_amount', label: '总金额', field: 'total_amount', align: 'right' },
  { name: 'internal_amount', label: '内部金额', field: 'internal_amount', align: 'right' },
  { name: 'sync_status', label: '推送状态', field: 'sync_status', align: 'center' },

  // 对接标识
  { name: 'pid', label: '渠道ID', field: 'pid', align: 'left' },
  { name: 'bus_code', label: '业务名称', field: row=>row.business.short_name, align: 'left' },
  { name: 'sku_code', label: '产品名称', field: row=>row.products.name, align: 'left' },

  // 用户详细信息
  { name: 'user_nick', label: '会员昵称', field: 'user_nick', align: 'left' },
  { name: 'user_type', label: '会员类型', field: 'user_type', align: 'left' },
  { name: 'user_status', label: '用户状态', field: 'user_status', align: 'center', format: v => v === 0 ? '启用' : '停用' },
  { name: 'user_source', label: '来源', field: 'user_source', align: 'left' },

  // 微信相关
  { name: 'wx_nick', label: '微信昵称', field: 'wx_nick', align: 'left' },
  { name: 'wx_unionid', label: 'UnionID', field: 'wx_unionid', align: 'left' },

  // 区域信息
  { name: 'province_code', label: '省份代码', field: 'province_code', align: 'left' },
  { name: 'city_code', label: '城市代码', field: 'city_code', align: 'left' },

  // 订单时间与状态
  { name: 'order_time', label: '订购时间', field: 'order_time', align: 'left' },
  { name: 'order_status', label: '订单状态', field: 'order_status', align: 'center' },

  // 优惠券
  { name: 'coupon_code', label: '券号', field: 'coupon_code', align: 'left' },

  // 投流与环境
  { name: 'platform', label: '投放平台', field: 'platform', align: 'left' },
  { name: 'ip', label: '提交IP', field: 'ip', align: 'left' },

  // 财务
  { name: 'price', label: '单价', field: 'price', align: 'right' },
  { name: 'quantity', label: '数量', field: 'quantity', align: 'right' },

  // 审计与时间戳
  { name: 'created_at', label: '接收时间', field: 'created_at', align: 'left', sortable: true },
  { name: 'pushed_at', label: '推送时间', field: 'pushed_at', align: 'left' },
  { name: 'sync_error', label: '失败原因', field: 'sync_error', align: 'left' },

  // 转发日志
  { name: 'forward_log', label: '转发日志', field: 'forward_log', align: 'center' }
]

// 默认可见列
const DEFAULT_COLUMNS = ['order_no', 'user_phone','code', 'link_id', 'bus_code', 'total_amount','pid','order_status', 'forward_log', 'order_time']
const visibleColumns = ref([...DEFAULT_COLUMNS])
// 用于下拉框显示的选项（过滤掉 ID 等不常手动切换的）
const columnOptions = computed(() => allColumns.map(col => ({
  name: col.name,
  label: col.label
})))
const resetColumns = () => {
  visibleColumns.value = [...DEFAULT_COLUMNS]
}
// --- 状态数据 ---
const rows = ref([])
const selected = ref([])
const loading = ref(false)
const filterModel = ref({ business_id: null, product_id: null })
const channelOptions = ref([])

// 数据权限相关计算属性
const dp = computed(() => authStore.dataPermissions)
const channelDisabled = computed(() => !!(dp.value?.channel_ids?.length))
const channelDisplay = computed(() => {
  if (!channelDisabled.value || !channelOptions.value.length) return '已限制渠道访问'
  const ids = dp.value.channel_ids
  return channelOptions.value
    .filter(c => ids.includes(c.pid))
    .map(c => c.name)
    .join('、')
})
const allowedBusinessIds = computed(() => dp.value?.business_ids || null)
const allowedProductIds = computed(() => dp.value?.product_ids || null)
const filters = ref({
  order_no: '',
  user_phone: '',
  order_status: null,
  sync_status: null,
  pid: null,
  dateRange: { from: firstDay, to: lastDayStr }
})

const orderStatusOptions = [
  { label: '未付款', value: 0 },
  { label: '首订', value: 1 },
  { label: '继订中', value: 2 },
  { label: '已退订', value: 3 }
]

const syncStatusOptions = [
  { label: '待处理', value: 0 },
  { label: '成功', value: 1 },
  { label: '失败', value: 2 }
]
const dateDisplay = computed(() => {
  const r = filters.value.dateRange
  if (!r?.from) return '请选择日期'
  return r.to ? `${r.from} ~ ${r.to}` : r.from
})

const pagination = ref({
  sortBy: 'id',
  descending: true,
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0
})

// const syncOptions = [
//   { label: '待处理', value: 0 },
//   { label: '成功', value: 1 },
//   { label: '失败', value: 2 }
// ]

// --- 方法 ---
const fetchData = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.rowsPerPage,
      ...filters.value,
      ...filterModel.value,
      start_date: filters.value.dateRange?.from || null,
      end_date: filters.value.dateRange?.to || null
    }
    delete params.dateRange
    const res = await productOrderApi.list(params)
    rows.value = res.data.data || []
    pagination.value.rowsNumber = res.data.total || 0
  } catch (error) {
    console.error('获取产品订单列表失败:', error)
  } finally {
    loading.value = false
  }
}

const onDateChange = () => {
  fetchData()
}

const onRequest = (props) => {
  const { page, rowsPerPage } = props.pagination
  pagination.value.page = page
  pagination.value.rowsPerPage = rowsPerPage
  fetchData()
}

const batchPush = async () => {
  const ids = selected.value.map(v => v.id)
  $q.dialog({
    title: '批量推送',
    message: `确认推送选中的 ${ids.length} 条记录到下家吗？`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await productOrderApi.pushBatch(ids)
      $q.notify({ type: 'positive', message: '批量推送指令已下发' })
      selected.value = []
      fetchData()
    } catch (e) {
      console.log("推送失败",e);
    }
  })
}

const exportExcel = async () => {
  try {
    const params = {
      ...filters.value,
      ...filterModel.value,
      start_date: filters.value.dateRange?.from || null,
      end_date: filters.value.dateRange?.to || null
    }
    delete params.dateRange
    const res = await productOrderApi.export(params)
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `product-orders-${new Date().getTime()}.xlsx`
    link.click()
    window.URL.revokeObjectURL(url)
    $q.notify({ type: 'positive', message: '导出成功' })
  } catch (e) {
    console.error('导出失败:', e)
    $q.notify({ type: 'negative', message: '导出失败' })
  }
}

const showDetails =(id)=>router.push(`product-order-details/${id}`);

// 转发日志弹窗
const forwardLogDialog = ref({ show: false, content: '', syncStatus: null, pushedAt: null, hasNotifyLog: false })
const showForwardLog = (row) => {
  const log = row.forward_log || ''
  forwardLogDialog.value.content = log || '无日志内容'
  forwardLogDialog.value.syncStatus = row.sync_status
  forwardLogDialog.value.pushedAt = row.pushed_at || null
  // 检测是否有 B→C 通知日志（FlowRouter 追加的 "B→C通知" 标记）
  forwardLogDialog.value.hasNotifyLog = log.includes('B→C通知')
  forwardLogDialog.value.show = true
}

// 订单状态统计
const statsDialog = ref({ show: false, loading: false, data: { total: 0, sent_code: 0, not_sent_code: 0, submitted: 0, error_reasons: [] } })
const showStats = async () => {
  statsDialog.value.show = true
  statsDialog.value.loading = true
  try {
    const params = {
      ...filters.value,
      ...filterModel.value,
      start_date: filters.value.dateRange?.from || null,
      end_date: filters.value.dateRange?.to || null
    }
    delete params.dateRange
    const res = await productOrderApi.statusStats(params)
    statsDialog.value.data = res.data
  } catch (error) {
    console.error('获取订单统计失败:', error)
    $q.notify({ type: 'negative', message: '获取订单统计失败' })
  } finally {
    statsDialog.value.loading = false
  }
}

// 获取渠道列表
const fetchChannels = async () => {
  try {
    const res = await channelApi.list({ per_page: 999 })
    channelOptions.value = res.data.data || []
  } catch {
    // 静默失败
  }
}

onMounted(async () => {
  // 先加载渠道列表，供渠道显示文本使用
  await fetchChannels()
  // 应用数据权限：渠道固定（禁用），业务/产品仅限制选项范围
  const dp = authStore.dataPermissions
  if (dp) {
    if (dp.channel_ids && dp.channel_ids.length > 0) {
      filters.value.pid = dp.channel_ids
    }
  }
  fetchData()
})
</script>
