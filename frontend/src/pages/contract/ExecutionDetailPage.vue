<template>
  <q-page padding>
    <div class="row q-col-gutter-md justify-center">
      <div class="col-12 col-md-9">
        <div class="row items-center q-mb-md">
          <q-btn icon="arrow_back" flat round @click="$router.back()" />
          <div class="text-h6 q-ml-sm">执行合同详情</div>
          <q-space />
          <q-badge :color="getStatusColor(data.status)" size="lg" class="q-pa-sm">
            {{ getStatusLabel(data.status) }}
          </q-badge>
        </div>

        <q-card flat bordered class="q-mb-md">
          <q-card-section class="bg-grey-1">
            <div class="text-subtitle1 text-weight-bold">基本信息</div>
          </q-card-section>
          <q-separator />
          <q-card-section>
            <div class="row q-col-gutter-lg">
              <div class="col-12 col-sm-6">
                <label class="text-grey-7">系统编号：</label>
                <div class="text-body1">{{ data.system_no }}</div>
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-grey-7">合同标题：</label>
                <div class="text-body1 text-weight-medium">{{ data.title }}</div>
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-grey-7">合同类型：</label>
                <div class="text-body1">{{ data.type === 'REVENUE' ? '收入型' : '成本型' }}</div>
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-grey-7">关联框架合同：</label>
                <div class="text-body1 text-primary">
                  {{ data.master_contract?.title }} ({{ data.master_contract?.contract_no }})
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card flat bordered class="q-mb-md">
          <q-card-section class="bg-grey-1">
            <div class="text-subtitle1 text-weight-bold">金额与财务</div>
          </q-card-section>
          <q-separator />
          <q-card-section>
            <div class="row q-col-gutter-lg">
              <div class="col-12 col-sm-4 text-center">
                <div class="text-grey-7">合同总金额</div>
                <div class="text-h5 text-primary">¥ {{ formatMoney(data.total_amount) }}</div>
              </div>
              <div class="col-12 col-sm-4 text-center">
                <div class="text-grey-7">税率 (%)</div>
                <div class="text-h5">{{ data.tax_rate }} %</div>
              </div>
              <div class="col-12 col-sm-4 text-center">
                <div class="text-grey-7">占比 (Ratio)</div>
                <div class="text-h5">{{ data.ratio }} %</div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card flat bordered>
          <q-card-section class="bg-grey-1">
            <div class="text-subtitle1 text-weight-bold">详细内容</div>
          </q-card-section>
          <q-separator />
          <q-card-section>
            <div class="text-body1" style="white-space: pre-wrap;">
              {{ data.content || '暂无详细内容说明' }}
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card flat bordered>
          <q-card-section class="text-subtitle2">数据审计</q-card-section>
          <q-separator />
          <q-list dense>
            <q-item>
              <q-item-section class="text-grey-7">创建时间：</q-item-section>
              <q-item-section side>{{ data.created_at }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section class="text-grey-7">最后更新：</q-item-section>
              <q-item-section side>{{ data.updated_at }}</q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { masterContractApi } from 'src/api/masterContract'

const route = useRoute()
const data = ref({})
const loading = ref(true)

// 加载数据
const loadDetail = async () => {
  try {
    console.log(route.params.id)
    // 拦截器已处理 data 拆包
    const res = await masterContractApi.showExecution(route.params.id)
    data.value = res
  } catch (error) {
    console.error('详情加载失败', error)
  } finally {
    loading.value = false
  }
}

// 金额格式化
const formatMoney = (val) => {
  if (!val) return '0.00'
  return parseFloat(val).toLocaleString('zh-CN', { minimumFractionDigits: 2 })
}

// 状态字典映射
const getStatusLabel = (s) => {
  const map = { 0: '草稿', 1: '审批中', 2: '已生效', 3: '已过期', 4: '已终止', 5: '作废' }
  return map[s] || '未知'
}

const getStatusColor = (s) => {
  const map = { 0: 'grey-7', 1: 'orange', 2: 'positive', 5: 'negative' }
  return map[s] || 'blue'
}

onMounted(() => {
  if (route.params.id) loadDetail()
})
</script>
