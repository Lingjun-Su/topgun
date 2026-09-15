<template>
  <q-page padding class="bg-grey-2">
    <div class="row q-col-gutter-md">
      <div class="col-12 col-lg-6">
        <q-card flat bordered class="shadow-1">
          <q-card-section class="bg-teal-9 text-white">
            <div class="row items-center">
              <q-icon name="call_received" size="sm" class="q-mr-md" />
              <div>
                <div class="text-h6">回调推送模拟器</div>
                <div class="text-caption">模拟下游系统向通用回调端点推送处理结果</div>
              </div>
            </div>
          </q-card-section>

          <q-card-section>
            <div class="text-subtitle2 text-grey-8 q-mb-sm">
              <q-icon name="link" /> 回调端点
            </div>
            <q-input
              v-model="endpoint"
              readonly
              outlined
              dense
              bg-color="grey-2"
              class="q-mb-md"
            >
              <template v-slot:append>
                <q-btn flat round dense icon="content_copy" @click="copyEndpoint">
                  <q-tooltip>复制 URL</q-tooltip>
                </q-btn>
              </template>
            </q-input>

            <div class="text-subtitle2 text-grey-8 q-mb-sm">
              <q-icon name="tune" /> 回调类型
            </div>
            <q-select
              v-model="callbackType"
              :options="callbackTypeOptions"
              outlined
              dense
              class="q-mb-md"
              @update:model-value="loadPreset"
            />

            <q-separator class="q-mb-md" />

            <div class="text-subtitle2 text-grey-8 q-mb-sm">
              <q-icon name="list_alt" /> 请求参数
            </div>

            <q-list bordered separator class="q-mb-md">
              <q-item v-for="(item, index) in params" :key="index" dense>
                <q-item-section>
                  <q-input
                    v-model="item.value"
                    :label="item.key"
                    dense
                    outlined
                    :hint="item.desc"
                  />
                </q-item-section>
              </q-item>
            </q-list>

            <div class="row q-col-gutter-sm">
              <div class="col-6">
                <q-btn
                  label="GET 发送"
                  color="primary"
                  icon="send"
                  class="full-width"
                  :loading="sending"
                  @click="sendRequest('GET')"
                />
              </div>
              <div class="col-6">
                <q-btn
                  label="POST 发送"
                  color="teal"
                  icon="send"
                  class="full-width"
                  :loading="sending"
                  @click="sendRequest('POST')"
                />
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-lg-6">
        <q-card flat bordered class="shadow-1">
          <q-card-section class="bg-grey-9 text-white">
            <div class="row items-center">
              <q-icon name="terminal" size="sm" class="q-mr-md" />
              <div class="text-h6">响应结果</div>
            </div>
          </q-card-section>

          <q-card-section>
            <div v-if="!response" class="text-grey-6 text-center q-py-xl">
              <q-icon name="arrow_back" size="lg" />
              <div class="q-mt-sm">点击左侧按钮发送请求</div>
            </div>

            <template v-else>
              <div class="row q-mb-sm" style="gap: 8px">
                <q-chip
                  :color="response.status === 200 ? 'green' : 'red'"
                  text-color="white"
                  dense
                >
                  HTTP {{ response.status }}
                </q-chip>
                <q-chip dense>{{ response.method }}</q-chip>
                <q-chip dense>{{ response.duration }}ms</q-chip>
              </div>

              <div class="text-subtitle2 text-grey-8 q-mb-sm">请求 URL</div>
              <q-input
                :model-value="response.requestUrl"
                readonly
                outlined
                dense
                class="q-mb-md"
              />

              <div class="text-subtitle2 text-grey-8 q-mb-sm">响应内容</div>
              <q-input
                type="textarea"
                :model-value="response.body"
                readonly
                outlined
                rows="6"
                class="q-mb-md"
              />

              <q-separator class="q-mb-md" />

              <q-btn
                label="查看数据库记录"
                color="info"
                icon="storage"
                @click="goToCallbackLogs"
                flat
              />
            </template>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'

const router = useRouter()

const baseUrl = window.location.origin
const callbackType = ref('province_order_result')
const sending = ref(false)
const response = ref(null)

const callbackTypeOptions = [
  { label: '省份订单结果回调', value: 'province_order_result' },
]

const endpoint = computed(() => {
  return `${baseUrl}/api/v2/third-channel/callback-receive/${callbackType.value}`
})

const params = reactive([
  { key: 'mobile', value: '13800138000', desc: '手机号' },
  { key: 'transId', value: 'TEST-' + Date.now().toString(36).toUpperCase(), desc: '验证码链接ID' },
  { key: 'resCode', value: '00000', desc: '结果码，00000=成功' },
  { key: 'resMsg', value: '订购成功', desc: '结果消息' },
  { key: 'orderStatus', value: '0', desc: '0=成功, 1=失败' },
  { key: 'channelId', value: '5', desc: '渠道ID' },
  { key: 'cpid', value: 'CP001', desc: '合作方ID' },
  { key: 'cpparm', value: '', desc: '透传参数' },
  { key: 'fee', value: '10.00', desc: '金额' },
  { key: 'errorCode', value: '', desc: '错误码' },
  { key: 'productId', value: 'P001', desc: '产品ID' },
  { key: 'orderTime', value: '2026-08-17 10:00:00', desc: '订单时间' },
  { key: 'province', value: '广东', desc: '省份' },
])

const presets = {
  province_order_result: { mobile: '13800138000', resCode: '00000', orderStatus: '0', channelId: '5', cpid: 'CP001', fee: '10.00', productId: 'P001', province: '广东' },
}

const loadPreset = (type) => {
  const preset = presets[type]
  if (!preset) return
  params.forEach(p => {
    if (preset[p.key] !== undefined) p.value = preset[p.key]
  })
  params.find(p => p.key === 'transId').value = 'TEST-' + Date.now().toString(36).toUpperCase()
  params.find(p => p.key === 'orderTime').value = new Date().toISOString().slice(0, 19).replace('T', ' ')
}

const copyEndpoint = () => {
  navigator.clipboard.writeText(endpoint.value)
}

const sendRequest = async (method) => {
  sending.value = true
  response.value = null
  const start = Date.now()

  try {
    const queryParams = {}
    params.forEach(p => { if (p.value !== '') queryParams[p.key] = p.value })

    let res
    if (method === 'GET') {
      res = await api.get(`/api/v2/third-channel/callback-receive/${callbackType.value}`, { params: queryParams })
    } else {
      const formData = new URLSearchParams()
      Object.entries(queryParams).forEach(([k, v]) => formData.append(k, v))
      res = await api.post(`/api/v2/third-channel/callback-receive/${callbackType.value}`, formData, {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      })
    }

    response.value = {
      method,
      status: res.status,
      body: typeof res.data === 'string' ? res.data : JSON.stringify(res.data, null, 2),
      requestUrl: endpoint.value,
      duration: Date.now() - start,
    }
  } catch (e) {
    response.value = {
      method,
      status: e.response?.status || 0,
      body: e.response?.data || e.message,
      requestUrl: endpoint.value,
      duration: Date.now() - start,
    }
  } finally {
    sending.value = false
  }
}

const goToCallbackLogs = () => {
  router.push('/callback-logs')
}
</script>