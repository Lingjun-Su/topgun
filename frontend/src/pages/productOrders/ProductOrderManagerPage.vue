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
          <div class="col-12 col-md-4">
            <BusinessProductSelector v-model="filterModel" @change="fetchData" />
          </div>
          <div class="col-12 col-md-2">
            <q-input v-model="filters.order_no" label="订单号" outlined dense clearable />
          </div>
          <div class="col-12 col-md-2">
            <q-input v-model="filters.user_phone" label="会员手机" outlined dense clearable />
          </div>
          <div class="col-12 col-md-2">
            <q-btn color="primary" icon="search" label="搜索" class="full-width" @click="fetchData" />
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
          <q-badge :color="props.value === 0 ? 'green' : (props.value === 2 ? 'negative' : 'warning')">
            {{ props.value === 0 ? '首订' : (props.value === 2 ? '继订' : '退订') }}
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
    </q-table>
  </q-page>
</template>

<script setup>
import { ref, onMounted,computed } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { productOrderApi } from 'src/api/productOrder'
import BusinessProductSelector from 'components/BusinessProductSelector.vue'

// import { route } from 'quasar/wrappers'
const router = useRouter()
const $q = useQuasar()

// --- 表格列定义 ---
const allColumns = [
  // 核心字段
  { name: 'id', label: 'ID', field: 'id', sortable: true, align: 'left' },
  { name: 'order_no', label: '订单号', field: 'order_no', sortable: true, align: 'left' },
  { name: 'user_phone', label: '会员手机', field: 'user_phone', align: 'left' },
  { name: 'code', label: '验证码', field: 'code', align: 'left' },
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
  { name: 'sync_error', label: '失败原因', field: 'sync_error', align: 'left' }
]

// 默认可见列
const DEFAULT_COLUMNS = ['order_no', 'user_phone','code', 'bus_code', 'total_amount','pid','order_status',  'order_time']
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
const filters = ref({
  order_no: '',
  user_phone: '',
  sync_status: null
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
      ...filterModel.value
    }
    const res = await productOrderApi.list(params)
    console.log("res",res.data);
    rows.value = res.data
    pagination.value.rowsNumber = res.total
  } finally {
    loading.value = false
  }
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
    message: `确认推送选中的 ${ids.length} 条记录到上家吗？`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await productOrderApi.pushBatch({ ids }) // 需在api模块补充此方法
      $q.notify({ type: 'positive', message: '批量推送指令已下发' })
      selected.value = []
      fetchData()
    } catch (e) {
      console.log("推送失败",e);
    }
  })
}

const showDetails =(id)=>router.push(`product-order-details/${id}`);


onMounted(() => fetchData())
</script>
