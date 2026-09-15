<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h6">合同审批：{{ contract.title }}</div>
      <q-space />
      <q-badge color="orange-8" padding="sm" label="待审批" />
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-8">
        <q-card flat bordered>
          <q-card-section class="bg-blue-grey-1 text-white row items-center">
            <q-icon name="fact_check" size="sm" class="q-mr-sm" />
            <div class="text-subtitle1">核心条款核 对 </div>
          </q-card-section>

          <q-card-section class="q-pa-lg">
            <div class="q-mb-md">
              <div class="text-body1"><span class="text-caption text-grey-7">合同编号：</span> {{contract.contract_no}}</div>
            </div>
            <div class="row q-col-gutter-md q-mb-lg">
              <div class="col-6">
                <div class="text-caption text-grey-7">甲方 (承办方)</div>
                <div class="text-body1 text-weight-bold text-primary">
                  <span class="text-caption">公 司 名 称 ：</span> {{ contract?.organization_a?.name || '未指定' }}
                </div>
                <div class="text-body1 text-weight-bold text-primary">
                  <span class="text-caption">签 &nbsp; &nbsp;订&nbsp;&nbsp; 人 ： </span> {{ contract.signer_a }}
                </div>
                <div class="text-body1 text-weight-bold text-primary">
                  <span class="text-caption">联系人/电话：</span>{{ contract.contact_a }}/{{ contract.contact_a_phone }}
                </div>
              </div>
              <div class="col-6">
                <div class="text-caption text-grey-7">乙方 (合作方)</div>
                <div class="text-body1 text-weight-bold text-secondary">{{ contract?.organization_b?.name || '未指定' }}</div>
                <div class="text-body1 text-weight-bold text-secondary">({{ contract.signer_b }})</div>
              </div>
            </div>

            <q-separator spaced />

            <div class="q-mb-md">
              <div class="text-caption text-grey-7">合同摘要</div>
              <div class="text-body2 q-mt-xs bg-grey-2 q-pa-md rounded-borders">
                {{ contract.summary }}
              </div>
            </div>
            <div class="q-mb-md">
              <div class="text-caption text-grey-7">合同备注</div>
              <div class="text-body2 q-mt-xs bg-grey-2 q-pa-md rounded-borders">
                {{ contract.remarks }}
              </div>
            </div>

            <div class="row q-col-gutter-md">
              <div class="col-4">
                <div class="text-caption text-grey-7">合同总额度</div>
                <div class="text-h6 text-negative">{{ formatMoney(contract.total_limit) }}</div>
              </div>
              <div class="col-4">
                <div class="text-caption text-grey-7">签订日期</div>
                <div class="text-body1">{{ contract.signed_date }}</div>
              </div>
              <div class="col-4">
                <div class="text-caption text-grey-7">有效期</div>
                <div class="text-body1">{{ contract.effective_date }}</div>
                <div class="text-body1">{{ contract.expiry_date }}</div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card flat bordered class="q-mt-md">
          <q-card-section class="text-subtitle2 text-grey-8">流转历史</q-card-section>
          <q-card-section>
            <q-list bordered>
              <template  v-for="log in contract.logs" :key="log.id">
                <q-item v-ripple>
                  <q-item-section avatar>
                    <q-icon :name="log.to_status === 1 ? 'send' : 'history'" color="grey" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>
                      <span class="text-subtitle">{{ actionTypeChange(log.action_type)||'未知状态' }}</span>
                      <span class="text-caption">({{ log.operator?.name }} - {{ log.created_at }})</span>
                    </q-item-label>
                    <q-item-label v-if="log.remark" class="text-italic">备注: {{ log.remark }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-separator></q-separator>
              </template>
            </q-list>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-4">
        <q-card flat bordered class="sticky-top">
          <q-card-section class="bg-primary text-white">
            <div class="text-subtitle1">审批决策</div>
          </q-card-section>

          <q-card-section class="q-gutter-y-md">
            <q-input
              v-model="auditForm.comment"
              type="textarea"
              label="审核意见 *"
              outlined
              placeholder="请输入通过理由或驳回原因..."
              :rules="[val => !!val || '审核意见是强制要求的']"
              rows="6"
            />

            <div class="text-caption text-warning row items-center">
              <q-icon name="warning" class="q-mr-xs" />
              请确保已线下核对合同文本与系统数据一致。
            </div>

            <q-btn
              unelevated
              color="positive"
              class="full-width q-py-sm"
              label="核准通过"
              icon="done_all"
              :loading="submitting"
              @click="submitAudit(1)"
            />

            <q-btn
              outline
              color="negative"
              class="full-width q-py-sm"
              label="驳回修改"
              icon="close"
              :loading="submitting"
              @click="submitAudit(2)"
            />
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { masterContractApi } from 'src/api/masterContract'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()

const contract = ref({ logs: [] })
const submitting = ref(false)
const auditForm = reactive({
  comment: ''
})

const formatMoney = (val) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(val || 0)
}

const loadDetail = async () => {
  $q.loading.show({ message: '正在加载合同待审数据...' })

  try {
    const res = await masterContractApi.show(route.params.id)
    contract.value = res
    // 如果不是审批中状态，禁止在此页面操作
    if (contract.value.status !== 1) {
      $q.notify({ type: 'warning', message: '该合同当前不在审批流中',position:'center' })
      router.back()
    }
  } finally {
    $q.loading.hide()
  }
}

const submitAudit = async (action) => {
  if (!auditForm.comment) {
    $q.notify({ type: 'negative', message: '请填写审核意见' ,position:'center'})
    return
  }

  const actionText = action === 1 ? '通过' : '驳回';//0-提交, 1-通过, 2-驳回, 3-终止, 4-作废

  $q.dialog({
    title: '审批确认',
    message: `确定要 [${actionText}] 这份合同吗？此操作将记录至审计台账。`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    submitting.value = true
    try {
      await masterContractApi.audit(contract.value.id, {
        to_status: action,
        action_type: action,//动作
        remarks: auditForm.comment,
        master_id:contract.value.id,
        from_status:contract.value.status,//原状态，有可能驳回之后再通过
      })
      // $q.notify({ type: 'positive', message: `合同已成功${actionText}` })
      router.push({ name: 'contract-list' })
    } finally {
      submitting.value = false
    }
  })
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
.sticky-top {
  position: sticky;
  top: 20px;
}
.bg-grey-2 {
  background-color: #f5f5f5;
  border: 1px solid #e0e0e0;
}
</style>
