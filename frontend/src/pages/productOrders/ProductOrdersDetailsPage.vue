<template>
  <q-page padding class="order-detail-page">
    <q-btn
      flat
      round
      dense
      icon="arrow_back"
      class="q-mb-md"
      @click="$router.back()"
    >
      <q-tooltip>返回</q-tooltip>
    </q-btn>

    <q-card flat bordered v-if="!loading && detail">
      <q-card-section class="bg-primary text-white">
        <div class="text-h6">订单详情</div>
        <div class="text-subtitle2">订单号：{{ detail.order_no || '-' }}</div>
      </q-card-section>

      <q-separator />

      <q-card-section>
        <div class="row q-col-gutter-md">
          <!-- 分组展示：基础信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">基础信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>ID</strong></q-item-section>
                <q-item-section>{{ detail.id }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>归属结算记录ID</strong></q-item-section>
                <q-item-section>{{ detail.settlement_id ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>结算状态</strong></q-item-section>
                <q-item-section>
                  <q-chip :color="detail.settle_status === 1 ? 'positive' : 'warning'" text-color="white" size="sm">
                    {{ detail.settle_status === 1 ? '已结算' : '未结算' }}
                  </q-chip>
                </q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>渠道ID</strong></q-item-section>
                <q-item-section>{{ detail.pid ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>业务标识</strong></q-item-section>
                <q-item-section>{{ detail.bus_code ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>产品标识(SKU)</strong></q-item-section>
                <q-item-section>{{ detail.sku_code ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>产品ID</strong></q-item-section>
                <q-item-section>{{ detail.product_id ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>业务ID</strong></q-item-section>
                <q-item-section>{{ detail.business_id ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>渠道公司ID</strong></q-item-section>
                <q-item-section>{{ detail.organization_id ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>业务类型</strong></q-item-section>
                <q-item-section>
                  {{ detail.type === '1' ? '平安健康业务' : detail.type === '2' ? '商超' : detail.type }}
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 用户信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">用户信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>会员手机</strong></q-item-section>
                <q-item-section>{{ detail.user_phone ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>会员昵称</strong></q-item-section>
                <q-item-section>{{ detail.user_nick ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>会员类型</strong></q-item-section>
                <q-item-section>{{ detail.user_type ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>会员开通时间</strong></q-item-section>
                <q-item-section>{{ formatDate(detail.user_create) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>会员状态</strong></q-item-section>
                <q-item-section>
                  <q-chip :color="detail.user_status === 0 ? 'positive' : 'negative'" text-color="white" size="sm">
                    {{ detail.user_status === 0 ? '使用中' : '停止使用' }}
                  </q-chip>
                </q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>用户来源</strong></q-item-section>
                <q-item-section>{{ detail.user_source ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>用户备注</strong></q-item-section>
                <q-item-section>{{ detail.user_remark ?? '-' }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 社交信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">微信信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>微信昵称</strong></q-item-section>
                <q-item-section>{{ detail.wx_nick ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>微信ID</strong></q-item-section>
                <q-item-section>{{ detail.wx_name ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>UnionID</strong></q-item-section>
                <q-item-section>{{ detail.wx_unionid ?? '-' }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 区域信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">区域信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>省份代码</strong></q-item-section>
                <q-item-section>{{ detail.province_code ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>城市代码</strong></q-item-section>
                <q-item-section>{{ detail.city_code ?? '-' }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 订单信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">订单信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>订单号</strong></q-item-section>
                <q-item-section>{{ detail.order_no ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>订购时间</strong></q-item-section>
                <q-item-section>{{ formatDate(detail.order_time) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>订单状态</strong></q-item-section>
                <q-item-section>
                  <q-chip :color="getOrderStatusColor(detail.order_status)" text-color="white" size="sm">
                    {{ formatOrderStatus(detail.order_status) }}
                  </q-chip>
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 优惠券信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">优惠券信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>券名</strong></q-item-section>
                <q-item-section>{{ detail.coupon ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>券号</strong></q-item-section>
                <q-item-section>{{ detail.coupon_code ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>券是否核销</strong></q-item-section>
                <q-item-section>
                  <q-chip :color="detail.coupon_write_off === 1 ? 'positive' : 'grey'" text-color="white" size="sm">
                    {{ detail.coupon_write_off === 1 ? '已核销' : '未核销' }}
                  </q-chip>
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 投流与环境信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">投放与环境</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>投放平台</strong></q-item-section>
                <q-item-section>{{ detail.platform ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>App包名</strong></q-item-section>
                <q-item-section>{{ detail.pack ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>推广落地页</strong></q-item-section>
                <q-item-section>
                  <a :href="detail.url" target="_blank" v-if="detail.url">{{ detail.url }}</a>
                  <span v-else>-</span>
                </q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>IP地址</strong></q-item-section>
                <q-item-section>{{ detail.ip ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>验证码下发时间</strong></q-item-section>
                <q-item-section>{{ detail.sms_time ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>验证码</strong></q-item-section>
                <q-item-section>{{ detail.code ?? '-' }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 财务信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">财务信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>单价</strong></q-item-section>
                <q-item-section>¥ {{ formatMoney(detail.price) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>数量</strong></q-item-section>
                <q-item-section>{{ detail.quantity ?? 1 }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>总额</strong></q-item-section>
                <q-item-section>¥ {{ formatMoney(detail.total_amount) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>内部总额</strong></q-item-section>
                <q-item-section>¥ {{ formatMoney(detail.internal_amount) }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 推送同步状态 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">推送同步状态</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>下家推送状态</strong></q-item-section>
                <q-item-section>
                  <q-chip :color="getSyncStatusColor(detail.sync_status)" text-color="white" size="sm">
                    {{ formatSyncStatus(detail.sync_status) }}
                  </q-chip>
                </q-item-section>
              </q-item>
              <q-item v-if="detail.sync_error">
                <q-item-section class="col-4 col-sm-3"><strong>推送失败原因</strong></q-item-section>
                <q-item-section>{{ detail.sync_error }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>成功推送时间</strong></q-item-section>
                <q-item-section>{{ formatDate(detail.pushed_at) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>退订推送状态</strong></q-item-section>
                <q-item-section>
                  <q-chip :color="getSyncStatusColor(detail.cancel_sync_status)" text-color="white" size="sm">
                    {{ formatSyncStatus(detail.cancel_sync_status) }}
                  </q-chip>
                </q-item-section>
              </q-item>
              <q-item v-if="detail.cancel_sync_error">
                <q-item-section class="col-4 col-sm-3"><strong>退订失败原因</strong></q-item-section>
                <q-item-section>{{ detail.cancel_sync_error }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 扩展字段 -->
          <div class="col-12" v-if="detail.ext_json">
            <div class="text-subtitle1 text-primary q-mb-sm">扩展参数(JSON)</div>
            <q-card flat bordered class="bg-grey-1">
              <q-card-section>
                <pre class="json-preview">{{ formatJson(detail.ext_json) }}</pre>
              </q-card-section>
            </q-card>
          </div>

          <!-- 关联数据：business -->
          <div class="col-12" v-if="detail.business">
            <div class="text-subtitle1 text-primary q-mb-sm">关联业务信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>业务名称</strong></q-item-section>
                <q-item-section>{{ detail.business.name ?? '-' }}</q-item-section>
              </q-item>
              <!-- 根据实际 business 表字段添加更多 -->
              <q-item v-if="detail.business.organization">
                <q-item-section class="col-4 col-sm-3"><strong>所属机构</strong></q-item-section>
                <q-item-section>{{ detail.business.organization.name ?? '-' }}</q-item-section>
              </q-item>
              <q-item v-if="detail.business.carrier">
                <q-item-section class="col-4 col-sm-3"><strong>运营商</strong></q-item-section>
                <q-item-section>{{ detail.business.carrier.name ?? '-' }}</q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 关联数据：productProvince -->
          <div class="col-12" v-if="detail.product_province">
            <div class="text-subtitle1 text-primary q-mb-sm">产品省份信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>省份名称</strong></q-item-section>
                <q-item-section>{{ detail.product_province.name ?? '-' }}</q-item-section>
              </q-item>
              <q-item v-if="detail.product_province.areas">
                <q-item-section class="col-4 col-sm-3"><strong>地区列表</strong></q-item-section>
                <q-item-section>
                  <div v-for="area in detail.product_province.areas" :key="area.id">
                    {{ area.name }}
                  </div>
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- 审计信息 -->
          <div class="col-12">
            <div class="text-subtitle1 text-primary q-mb-sm">审计信息</div>
            <q-list dense bordered separator class="rounded-borders">
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>创建人ID</strong></q-item-section>
                <q-item-section>{{ detail.created_by ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>修改人ID</strong></q-item-section>
                <q-item-section>{{ detail.updated_by ?? '-' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>创建时间</strong></q-item-section>
                <q-item-section>{{ formatDate(detail.created_at) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section class="col-4 col-sm-3"><strong>更新时间</strong></q-item-section>
                <q-item-section>{{ formatDate(detail.updated_at) }}</q-item-section>
              </q-item>
              <q-item v-if="detail.deleted_at">
                <q-item-section class="col-4 col-sm-3"><strong>删除时间</strong></q-item-section>
                <q-item-section>{{ formatDate(detail.deleted_at) }}</q-item-section>
              </q-item>
            </q-list>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- 加载状态 -->
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots color="primary" size="3rem" />
      <div class="q-ml-md">加载中...</div>
    </div>

    <!-- 错误状态 -->
    <div v-if="error" class="flex flex-center q-pa-xl">
      <q-icon name="error_outline" color="negative" size="3rem" />
      <div class="q-ml-md text-negative">{{ error }}</div>
      <q-btn flat color="primary" class="q-ml-md" @click="fetchDetail">重试</q-btn>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { productOrderApi } from 'src/api/productOrder'
import { useQuasar } from 'quasar'

const $q = useQuasar()
const route = useRoute()


const detail = ref(null)
const loading = ref(false)
const error = ref('')

const orderId = route.params.id  // 假设路由定义为 /order/:id

// 辅助函数
function formatDate(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr
  return date.toLocaleString('zh-CN')
}

function formatMoney(value) {
  if (value === null || value === undefined) return '0.00'
  return Number(value).toFixed(2)
}

function formatOrderStatus(status) {
  const map = { 0: '未付款', 1: '首次订购', 2: '继订中', 3: '退订' }
  return map[status] ?? '未知'
}

function getOrderStatusColor(status) {
  const map = { 0: 'grey', 1: 'primary', 2: 'info', 3: 'negative' }
  return map[status] ?? 'grey'
}

function formatSyncStatus(status) {
  const map = { 0: '待处理', 1: '成功', 2: '失败' }
  return map[status] ?? '未知'
}

function getSyncStatusColor(status) {
  const map = { 0: 'grey', 1: 'positive', 2: 'negative' }
  return map[status] ?? 'grey'
}

function formatJson(json) {
  if (!json) return ''
  try {
    const obj = typeof json === 'string' ? JSON.parse(json) : json
    return JSON.stringify(obj, null, 2)
  } catch {
    return json
  }
}

async function fetchDetail() {
  if (!orderId) {
    error.value = '缺少订单ID'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const response = await productOrderApi.show(orderId)
    // 假设后端返回结构 { code: 200, data: {...} }
      detail.value = response
  } catch (err) {
    console.log("err",err)
    error.value = '请求失败，请检查网络或联系管理员'
    $q.notify({ type: 'negative', message: error.value })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDetail()
})
</script>

<style scoped lang="scss">
.order-detail-page {
  max-width: 1200px;
  margin: 0 auto;
}

.json-preview {
  background: #f5f5f5;
  padding: 12px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 12px;
  white-space: pre-wrap;
  word-break: break-word;
  margin: 0;
}
</style>
