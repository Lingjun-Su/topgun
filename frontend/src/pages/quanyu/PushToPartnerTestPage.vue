<template>
  <q-page padding>
    <q-card flat bordered class="q-mx-auto" style="max-width: 900px">
      <q-card-section class="bg-indigo text-white row items-center">
        <div class="text-h6">下家公司接口推送测试 (调试专用)</div>
        <q-space />
        <q-badge color="white" text-color="indigo">目标: xinquanyu.top</q-badge>
      </q-card-section>

      <q-card-section class="row q-col-gutter-md">
        <div class="col-12 col-md-7">
          <div class="text-subtitle2 q-mb-sm q-qutter-md row">
            <q-select v-model="type" :options="['order','cancel']" label="推送数据内容 (Payload)" outlined dense style="min-width:100px;"/>
            <product-select
              v-model="formData.product_id"
              style="max-width: 220px"
            />
            <pid-select
              v-model="formData.pid"
              style="max-width: 220px"
            />
          </div>


          <q-form @submit="handlePush" class="row q-col-gutter-sm">
            <div class="col-6">
              <q-input v-model="formData.mobile" label="手机号" dense outlined />
            </div>
            <div class="col-6">
              <q-input v-model="formData.order_no" label="订单号" dense outlined />
            </div>
            <div class="col-4">
              <q-input v-model="formData.pid" label="PID" dense outlined />
            </div>
            <div class="col-4">
              <q-input v-model="formData.bus_code" label="业务标识" dense outlined />
            </div>
            <div class="col-4">
              <q-input v-model="formData.sku_code" label="产品标识" dense outlined />
            </div>
            <div class="col-4">
              <q-input v-model="formData.price" label="单价" dense outlined />
            </div>
            <div class="col-4">
              <q-input v-model="formData.quantity" label="数量" dense outlined />
            </div>
            <div class="col-4">
              <q-input v-model="formData.total_amount" label="小计" dense outlined />
            </div>

            <div class="col-12">
              <q-separator class="q-my-md" />
              <div class="text-subtitle2 q-mb-sm">签名配置</div>
              <q-input v-model="appKey" label="下家提供的 Key" dense outlined password />
            </div>

            <div class="col-12 q-mt-md">
              <q-btn label="发起推送请求" color="indigo" :loading="loading" type="submit" icon="send" />
              <q-btn label="生成随机订单" color="grey" flat @click="randomOrder" />
            </div>
          </q-form>
        </div>

        <div class="col-12 col-md-5">
          <div class="text-subtitle2 q-mb-sm">待发送签名 (Sign)</div>
          <div class="q-pa-sm bg-grey-2 rounded-borders text-break all">
            <code class="text-primary text-weight-bold">{{ sign }}</code>
          </div>

          <div class="text-subtitle2 q-mt-md q-mb-sm">执行反馈</div>
          <div class="response-box q-pa-sm bg-black text-green rounded-borders shadow-2">
            <pre v-if="responseLog" class="q-ma-none">{{ responseLog }}</pre>
            <div v-else class="text-grey-7 text-italic">等待请求...</div>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { api } from 'boot/axios'
import CryptoJS from 'crypto-js'
import { useQuasar } from 'quasar'
import ProductSelect from 'components/OrderProductSelect.vue';
import PidSelect from 'components/PidSelect.vue';

const $q = useQuasar()
const loading = ref(false)
const responseLog = ref(null)
const appKey = ref('1252KS25D7F3ZC7J')
const sign=ref('');
const type =ref('order');//

const formData = reactive({
  mobile: '15511329132',
  // pid: '',
  bus_code: '96339749',
  sku_code: 'SCJKGJHYYK',
  order_no: 'PUSH_' + Date.now(),
  create_time: Math.floor(Date.now() / 1000).toString(),
  type: '1',
  platform: '333',
  pack: 'eee',
  url: 'www.baidu.com',
  ip: '127.0.0.1',
  sms_time: Math.floor(Date.now() / 1000).toString(),
  code: '123456'
})

// 实时计算签名预览
const currentSign = ((timestamp) => {
  const params = { ...formData }
  const sortedKeys = Object.keys(params).sort()
  let baseString = sortedKeys.map(k => `${k}=${params[k]}`).join('&')
  baseString += `&key=${appKey.value}&timestamp=${timestamp}`
  return CryptoJS.MD5(baseString).toString().toUpperCase()
})

const randomOrder = () => {
  formData.order_no = 'PUSH_' + Date.now()
  formData.create_time = Math.floor(Date.now() / 1000).toString()
}

const handlePush = async () => {
  loading.value = true
  responseLog.value = '正在建立连接...'
  let url ='';
  switch(type.value){
    case 'order':
      url ='/api_v2/ThirdChannel/receive';
      break;
    case 'cancel':
      url ='/api_v2/ThirdChannel/cancelOrder';
      break;
      case 'test':
        url ='/api_v2/ThirdChannel/test';
        break;
  }

  try {
    // 严谨起见，直接调用后端中转接口的“重试/手动推送”逻辑
    // 或者如果你想完全在前端模拟，就直接 post 到对方地址（注意跨域限制）

    // 这里采用调用后端逻辑，因为后端不受跨域限制，且能记录审计日志
    let t =Math.floor(Date.now() / 1000).toString();
    sign.value =currentSign(t);
    const res = await api.post(url, {
      data: formData,
      sign: sign.value,
      pid: formData.pid,
      timestamp:t,
      url:url,
    })

    responseLog.value = JSON.stringify(res.data, null, 2)

    if (res.data.code === 0) {
      $q.notify({ color: 'positive', message: '下家公司返回：同步成功' })
    } else {
      $q.notify({ color: 'warning', message: '推送已发出，但对方拒绝了数据' })
    }
  } catch (error) {
    responseLog.value = error.response ? JSON.stringify(error.response.data, null, 2) : error.message
    $q.notify({ color: 'negative', message: '请求网络错误'})
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.response-box {
  min-height: 200px;
  max-height: 400px;
  overflow-y: auto;
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
}
.text-break {
  word-break: break-all;
}
</style>
