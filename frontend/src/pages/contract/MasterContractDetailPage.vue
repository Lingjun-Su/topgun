<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">

      <div class="text-h6">合同详情: {{ contract.system_no }}</div>
      <q-chip :color="statusColor" text-color="white" class="q-ml-md" square>
        {{ statusLabel }}
      </q-chip>
      <q-space />
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-9">
        <q-card flat bordered>
          <q-tabs
            v-model="activeTab"
            dense
            class="text-grey"
            active-color="primary"
            indicator-color="primary"
            align="left"
            narrow-indicator
          >
            <q-tab name="basic" label="基本信息" icon="description" />
            <q-tab name="executions" label="执行合同明细" icon="assignment" />
            <q-tab name="amendments" label="补充协议" icon="art_track" />
            <q-tab name="changes" label="变更记录" icon="edit_note" />
            <q-tab name="logs" label="操作日志" icon="list_alt" />
          </q-tabs>

          <q-separator />

          <q-tab-panels v-model="activeTab" animated>
            <q-tab-panel name="basic" class="q-pa-lg">
              <div class="row q-col-gutter-lg">
                <div class="col-12 col-md-6">
                  <div class="text-subtitle2 text-grey-7">甲方名称</div>
                  <div class="text-body1 q-mb-md">{{ contract.organization_a.name || '-' }}</div>
                  <div class="text-subtitle2 text-grey-7">乙方名称</div>
                  <div class="text-body1 q-mb-md">{{ contract.organization_b.name || '-' }}</div>

                  <div class="text-subtitle2 text-grey-7">合同编号</div>
                  <div class="text-body1 q-mb-md">{{ contract.contract_no || '-' }}</div>

                  <div class="text-subtitle2 text-grey-7">合同标题</div>
                  <div class="text-body1 q-mb-md">{{ contract.title || '-' }}</div>

                  <div class="text-subtitle2 text-grey-7">甲方联系人/电话</div>
                  <div class="text-body1 q-mb-md">{{ contract.contact_a }}/{{ contract.contact_a_phone }}</div>

                  <div class="text-subtitle2 text-grey-7">乙方联系人/电话</div>
                  <div class="text-body1 q-mb-md">{{ contract.contact_a }}/{{ contract.contact_a_phone }}</div>

                  <div class="text-subtitle2 text-grey-7">生效日期范围</div>
                  <div class="text-body1 q-mb-md">
                    {{ contract.effective_date }} 至 {{ contract.expiry_date }}
                  </div>
                </div>

                <div class="col-12 col-md-6 bg-blue-1 rounded-borders q-pa-md">
                  <div class="text-subtitle2 text-blue-9">财务概要</div>
                  <q-list dense>
                    <q-item>
                      <q-item-section>合同原始总额</q-item-section>
                      <q-item-section side class="text-weight-bold">{{ formatMoney(contract.total_limit) }}</q-item-section>
                    </q-item>
                    <q-item>
                      <q-item-section>补充协议调整</q-item-section>
                      <q-item-section side :class="contract.amendment_total >= 0 ? 'text-positive' : 'text-negative'">
                        {{ contract.amendment_total >= 0 ? '+' : '' }}{{ formatMoney(contract.amendment_total) }}
                      </q-item-section>
                    </q-item>
                    <q-separator class="q-my-xs" />
                    <q-item>
                      <q-item-section class="text-subtitle1">当前框架总额度</q-item-section>
                      <q-item-section side class="text-subtitle1 text-weight-bolder text-primary">
                        {{ formatMoney(contract.total_limit) }}
                      </q-item-section>
                    </q-item>
                  </q-list>
                </div>

                <div class="col-12">
                  <div class="text-subtitle2 text-grey-7">合同内容</div>
                  <div class="text-body2 q-mt-xs border-grey-3 q-pa-sm bg-grey-2 rounded-borders">
                    {{ contract.summary || '无合同内容' }}
                  </div>
                </div>
                <div class="col-12">
                  <div class="text-subtitle2 text-grey-7">合同备注说明</div>
                  <div class="text-body2 q-mt-xs border-grey-3 q-pa-sm bg-grey-2 rounded-borders">
                    {{ contract.remarks || '无备注信息' }}
                  </div>
                </div>
              </div>
            </q-tab-panel>

            <q-tab-panel name="executions" class="q-pa-none">
              <q-table
                flat
                :rows="contract.executions"
                :columns="executionColumns"
                row-key="id"
                :pagination="{ rowsPerPage: 0 }"
                hide-bottom
              >
                <template v-slot:body-cell-amount="props">
                  <q-td :props="props" class="text-weight-bold">{{ formatMoney(props.value) }}</q-td>
                </template>
              </q-table>
            </q-tab-panel>

            <q-tab-panel name="logs">
              <q-timeline color="secondary" layout="comfortable" side="right">
                <q-timeline-entry
                  v-for="log in contract.logs"
                  :key="log.id"
                  :title="log.action"
                  :subtitle="log.created_at"
                  :icon="log.type_action === '审批通过' ? 'done' : 'history'"
                >
                  <div>动作:{{ actionTypeChange(log.action_type) }}({{ log.operator?.name }})</div>
                  <div v-if="log.comment" class="text-italic text-grey-7 q-mt-xs">
                    "{{ log.remarks }}"
                  </div>
                </q-timeline-entry>
              </q-timeline>
            </q-tab-panel>
          </q-tab-panels>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card flat bordered class="bg-primary text-white text-center q-pa-md">
          <div class="text-subtitle2 opacity-70">框架可用余额</div>
          <div class="text-h4 text-weight-bolder q-my-sm">
            {{ formatMoney(contract.balance) }}
          </div>
          <q-linear-progress
            :value="balanceRatio"
            color="white"
            class="q-mt-md"
            size="10px"
            rounded
          />
          <div class="text-caption q-mt-xs">已占用: {{ (balanceRatio * 100).toFixed(2) }}%</div>
        </q-card>

        <q-card flat bordered class="q-mt-md">
          <q-list dense separator>
            <q-item-label header>快速操作</q-item-label>
            <q-item clickable v-ripple @click="handlePrint">
              <q-item-section avatar><q-icon name="print" color="grey-7" /></q-item-section>
              <q-item-section>打印合同文本</q-item-section>
            </q-item>
            <q-item clickable v-ripple @click="handleExport">
              <q-item-section avatar><q-icon name="download" color="grey-7" /></q-item-section>
              <q-item-section>导出执行清单(Excel)</q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { masterContractApi } from 'src/api/masterContract'

const $q = useQuasar()
const route = useRoute()
// const router = useRouter()

const activeTab = ref('basic')
const contract = ref({
  executions: null,
  amendments: [],
  logs: [],
  changes: [],
  organization_a:{},
  organization_b:{},
})


// 状态映射
//审批动作：'动作: 0-提交, 1-通过, 2-驳回, 3-终止, 4-作废'
//对应合同状态状态: 0草稿 1审批中 2驳回 3已生效 4已过期 5已终止 6作废
const statusMap = {
  0: { label: '草稿', color: 'grey-7' },
  1: { label: '审批中', color: 'orange-8' },
  2: { label: '驳回', color: 'positive' },
  3: { label: '已生效', color: 'positive' },
  4: { label: '已过期', color: 'brown' },
  5: { label: '已终止', color: 'negative' },
  6: { label: '作废', color: 'grey-10' }
}

const statusLabel = computed(() => statusMap[contract.value.status]?.label || '未知')
const statusColor = computed(() => statusMap[contract.value.status]?.color || 'grey')

// 计算已使用的额度比例
const balanceRatio = computed(() => {
  if (!contract.value.total_amount || contract.value.total_amount === 0) return 0
  const used = contract.value.total_amount - contract.value.balance
  return Math.min(used / contract.value.total_amount, 1)
})

// 执行单列定义
const executionColumns = [
  { name: 'contract_no', label: '合同编号', field: 'contract_no', align: 'left' },
  { name: 'type', label: '类型', field: 'type', align: 'center' },
  { name: 'amount', label: '分切金额', field: 'amount', align: 'right' },
  { name: 'contract_date', label: '签订日期', field: 'contract_date', align: 'center' },
  { name: 'status', label: '状态', field: 'status', align: 'center' }
]

const formatMoney = (val) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(val || 0)
}

// 获取详情
const loadDetail = async () => {
  $q.loading.show()
  try {
    const res = await masterContractApi.show(route.params.id)
    contract.value = res
    // contract.value.executions =[];//
  } finally {
    $q.loading.hide()
  }
}


const actionTypeChange = action => ({
  submit:'提交',
  withdraw:'撤回',
  approve:'通过',
  reject:'驳回',
  void:'作废'
}[action] ?? '其他');

onMounted(loadDetail)
</script>

<style scoped>
.opacity-70 { opacity: 0.7; }
.border-grey-3 { border: 1px solid #e0e0e0; }
</style>
