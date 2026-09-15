<template>
  <q-page padding class="bg-grey-2">
    <div class="row q-col-gutter-md">
      <div class="col-12 col-lg-8">
        <q-card flat bordered class="shadow-1">
          <q-card-section class="bg-blue-grey-10 text-white">
            <div class="row items-center">
              <q-icon name="security" size="sm" class="q-mr-md" />
              <div>
                <div class="text-h6">渠道推送模拟器 (Webhook Simulator)</div>
                <div class="text-caption">模拟外部渠道向本系统异步推送订单数据</div>
              </div>
              <q-space />
              <q-btn label="全量随机生成业务数据" color="secondary" outline icon="auto_fix_high" @click="generateAllMockData" />
            </div>
          </q-card-section>

          <q-card-section class="bg-amber-1 q-pb-md">
            <div class="text-subtitle2 q-mb-sm text-orange-9"><q-icon name="key" /> 渠道鉴权配置 (必须手动输入)</div>
            <div class="row q-col-gutter-md">
              <q-input
                v-model="auth.pid"
                label="渠道 PID *"
                placeholder="例如：CH_88021"
                outlined dense bg-color="white" class="col-6"
                :rules="[val => !!val || 'PID 不能为空']"
              />
              <q-input
                v-model="auth.secret_key"
                label="通信密匙 (Secret Key) *"
                type="password"
                placeholder="用于计算签名"
                outlined dense bg-color="white" class="col-6"
                :rules="[val => !!val || 'Key 不能为空']"
              />
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section class="q-gutter-y-md">
            <div class="text-subtitle2 text-primary"># 订单业务报文</div>

            <div class="row q-col-gutter-sm">
              <q-input v-model="form.bus_code" label="业务标识" outlined dense class="col-4" />
              <q-input v-model="form.sku_code" label="产品标识" outlined dense class="col-4" />
              <q-input v-model="form.user_phone" label="用户手机 *" outlined dense class="col-4" />

              <q-input v-model="form.order_no" label="外部订单号" outlined dense class="col-6" />
              <q-select
                v-model="form.order_status"
                :options="[{label:'首次订购', value:1}, {label:'继订', value:2}, {label:'退订', value:3}]"
                emit-value map-options label="订单状态" outlined dense class="col-3"
              />
              <q-input v-model="form.price" type="number" label="单价" outlined dense class="col-3" />
            </div>

            <q-input
              v-model="form.ext_json"
              type="textarea"
              label="差异化扩展参数 (ext_json)"
              outlined
              dense
              rows="3"
            />

            <div class="bg-grey-9 text-green-13 q-pa-md rounded-borders shadow-2">
              <div class="row justify-between items-center">
                <span class="text-weight-bold">实时签名预览 (Sign Calculation):</span>
                <q-badge color="green">{{ auth.algo }}</q-badge>
              </div>
              <div class="q-mt-xs text-break" style="font-family: monospace;">
                {{ computedSignature }}
              </div>
            </div>

            <div class="row justify-end q-gutter-sm">
              <q-btn label="重置" flat color="grey" @click="resetForm" />
              <q-btn
                label="执行渠道推送 (POST)"
                color="red-10"
                icon="send"
                :loading="loading"
                @click="submitSimulation"
              />
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-lg-4">
        <q-table
          title="推送历史 (逻辑删除记录)"
          :rows="history"
          :columns="columns"
          row-key="id"
          flat bordered
        >
          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn size="sm" color="negative" flat icon="delete" @click="handleDelete(props.row.id)" />
            </q-td>
          </template>
        </q-table>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import CryptoJS from 'crypto-js'
import { channelSimulatorApi } from 'src/api/thirdChannel'

const $q = useQuasar()
const loading = ref(false)
const history = ref([])

// 1. 必须手动输入的鉴权信息
const auth = ref({
  pid: '',
  secret_key: '',
  algo: 'HMAC-SHA256'
})

// 2. 业务数据模型
const form = ref({
  bus_code: 'HEALTH_001',
  sku_code: 'SKU_99',
  user_phone: '',
  order_no: '',
  order_status: 1,
  price: 0,
  quantity: 1,
  total_amount: 0,
  ext_json: '{}',
  create_time_ts: ''
})

// 3. 核心：计算签名
// 逻辑：将 PID + OrderNo + Price + SecretKey 进行排序拼接后加密
const computedSignature = computed(() => {
  if (!auth.value.pid || !auth.value.secret_key) return '等待输入 PID 和 Key...'

  const rawString = `pid=${auth.value.pid}&order_no=${form.value.order_no}&price=${form.value.price}&key=${auth.value.secret_key}`
  return CryptoJS.HmacSHA256(rawString, auth.value.secret_key).toString(CryptoJS.enc.Hex).toUpperCase()
})

// 4. 逻辑：随机生成业务部分（不覆盖 PID/Key）
const generateAllMockData = () => {
  const ts = Math.floor(Date.now() / 1000).toString()
  form.value = {
    ...form.value,
    user_phone: '13' + Math.floor(Math.random() * 1000000000).toString().padStart(9, '0'),
    order_no: 'EXT' + ts + Math.floor(Math.random() * 100),
    price: (Math.random() * 500).toFixed(2),
    create_time_ts: ts,
    ext_json: JSON.stringify({ ip: '182.11.22.33', source: 'ADS' })
  }
}

// 5. 推送提交
const submitSimulation = async () => {
  if (!auth.value.pid || !auth.value.secret_key) {
    $q.notify({ type: 'negative', message: '必须手动输入 PID 和 Key 才能生成合法签名！' })
    return
  }

  loading.value = true
  try {
    const payload = {
      ...form.value,
      pid: auth.value.pid,
      sign: computedSignature.value, // 发送计算好的签名
      pushed_at: new Date().toISOString()
    }

    await channelSimulatorApi.store(payload)
    $q.notify({ type: 'positive', message: '渠道报文推送成功，签名校验已通过', icon: 'verified' })
    loadHistory()
  } catch (e) {
    $q.notify({ type: 'negative', message: '推送被拦截: ' + e.message })
  } finally {
    loading.value = false
  }
}

// 逻辑删除与历史加载
const handleDelete = (id) => {
  $q.dialog({ title: '确认删除', message: '进行逻辑删除？', cancel: true })
    .onOk(async () => {
      await channelSimulatorApi.remove(id)
      loadHistory()
    })
}

const loadHistory = async () => {
  const res = await channelSimulatorApi.list()
  history.value = res.data
}

const columns = [
  { name: 'id', label: 'ID', field: 'id' },
  { name: 'pid', label: '渠道', field: 'pid' },
  { name: 'order_no', label: '订单号', field: 'order_no' },
  { name: 'actions', label: '操作' }
]

onMounted(() => {
  generateAllMockData()
  loadHistory()
})
</script>

<style scoped>
.text-break { word-break: break-all; }
</style>
