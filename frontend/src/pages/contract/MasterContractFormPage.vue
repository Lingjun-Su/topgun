<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-lg">
      <div class="text-h6 q-ml-sm">{{ isEdit ? '修改框架合同' : '新建框架合同' }}</div>
    </div>

    <q-form @submit="handleSubmit" ref="formRef">
      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-8">
          <q-card flat bordered>
            <q-card-section class="bg-blue-grey-1 text-subtitle2 text-weight-bold text-white">
              主体信息 (Contract Parties)
            </q-card-section>

            <q-card-section class="q-gutter-y-md">
              <div class="row q-col-gutter-md">
                <div class="col-12 col-md-6">
                  <div class="text-caption q-mb-xs text-primary text-weight-bold">甲方公司 *</div>
                  <organization-tree-select
                    v-model="form.org_a_id"
                    placeholder="请选择甲方公司"
                    :rules="[val => !!val || '请选择甲方']"
                    @update:model-value="onPartyAChange"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <div class="text-caption q-mb-xs text-secondary text-weight-bold">乙方公司 *</div>
                  <organization-tree-select
                    v-model="form.org_b_id"
                    placeholder="请选择乙方公司"
                    :rules="[val => !!val || '请选择乙方']"
                    @update:model-value="onPartyBChange"
                  />
                </div>
              </div>

              <div class="row q-col-gutter-sm">
                <q-input v-model="form.contact_a" label="甲方联系人" outlined dense class="col-12 col-md-3" />
                <q-input v-model="form.contact_a_phone" label="甲方电话" outlined dense class="col-12 col-md-3" />
                <q-input v-model="form.contact_b" label="乙方联系人" outlined dense class="col-12 col-md-3" />
                <q-input v-model="form.contact_b_phone" label="乙方电话" outlined dense class="col-12 col-md-3" />
              </div>

              <q-separator />

              <q-input
                v-model="form.contract_no"
                label="合同编号 *"
                outlined dense
                :rules="[val => !!val || '合同编号不能为空']"
              />

              <q-input
                v-model="form.title"
                label="合同名称 *"
                outlined dense
                :rules="[val => !!val || '合同名称不能为空']"
              />


              <q-input
                v-model="form.summary"
                type="textarea"
                label="核心合同内容 *"
                outlined dense rows="4"
                :rules="[val => !!val || '请输入合同内容']"
              />
            </q-card-section>
          </q-card>

          <q-card flat bordered class="q-mt-md">
            <q-card-section>
              <q-input v-model="form.remarks" type="textarea" label="备注" outlined dense rows="2" />
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-md-4">
          <q-card flat bordered class="q-mb-md">
            <q-card-section class="bg-indigo-1 text-subtitle2 text-weight-bold">关键日期</q-card-section>
            <q-card-section class="q-gutter-y-md">
              <q-input v-model="form.signed_date" label="签订日期" type="date" outlined dense stack-label />
              <q-input v-model="form.effective_date" label="开始日期" type="date" outlined dense stack-label />
              <q-input v-model="form.expiry_date" label="结束日期" type="date" outlined dense stack-label

              />
            </q-card-section>
          </q-card>

          <q-card flat bordered class="q-mb-md">
            <q-card-section class="bg-green-1 text-subtitle2 text-weight-bold">财务额度</q-card-section>
            <q-card-section>
              <q-input
                v-model.number="form.total_limit"
                label="合同总额度 *"
                type="number"
                outlined dense prefix="¥"
                :disable="isEdit && form.status !== 0"
                :rules="[val => val >= 0 || '无效金额']"
              />
            </q-card-section>
          </q-card>

          <div class="q-gutter-y-sm">
            <q-btn type="submit" color="primary" class="full-width" icon="send" :label="isEdit ? '保存修改' : '提交审批'" :loading="submitting" />
            <q-btn v-if="!isEdit || form.status === 0" outline color="grey-7" class="full-width" label="暂存草稿" @click="saveAsDraft" />
            <q-btn flat color="negative" class="full-width" label="返回列表" @click="router.back()" />
          </div>
        </div>
      </div>
    </q-form>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { masterContractApi } from 'src/api//masterContract'
// 导入自定义组件
import OrganizationTreeSelect from 'components/OrganizationTreeSelect.vue'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const formRef = ref(null)

const isEdit = computed(() => !!route.params.id)
const submitting = ref(false)

const form = reactive({
  id: null,
  contract_no:null,//合同编号
  title:null,//合同名称
  org_a_id: null,//甲方ID
  org_b_id: null,//乙方ID
  sign_date: '',//签订日期
  effective_date: '',//生效日期
  expiry_date: '',//有效期至
  summary: '',//内容摘要
  contact_a: '',//甲方联系人
  contact_a_phone: '',//甲方联系电话
  contact_b: '',//乙方联系人
  contact_b_phone: '',//乙方联系电话
  remarks: '',//备注
  total_limit: 0,//金额
  status: 0,//状态
})

// --- 业务联动逻辑 ---

/**
 * 当甲方公司变动时，可以根据业务需求自动填充联系人（可选）
 */
const onPartyAChange = (val) => {
  console.log('甲方已变动 ID:', val)
  // 这里可以调用接口查询该机构的默认联系人
}

const onPartyBChange = (val) => {
  console.log('乙方已变动 ID:', val)
}

// 加载详情
const loadDetail = async () => {
  if (isEdit.value) {
    $q.loading.show()
    try {
      const res = await masterContractApi.show(route.params.id)
      Object.assign(form, res)
    } finally {
      $q.loading.hide()
    }
  }
}

const handleSubmit = async () => {
  const success = await formRef.value.validate()
  if (!success) return

  submitting.value = true
  try {
    const payload = { ...form }
    if (!isEdit.value) {
      payload.status = 1 // 提交则进入审批流
      await masterContractApi.store(payload)
    }else{//修改
      await masterContractApi.update(payload.id,payload)
    }
    $q.notify({ type: 'positive', message: '提交成功', position: 'top' })
    router.push({ name: 'contract-list' })
  } finally {
    submitting.value = false
  }
}

const saveAsDraft = async () => {
  submitting.value = true
  try {
    form.status = 0
    await masterContractApi.store(form)
    $q.notify({ type: 'info', message: '已存入草稿箱' })
    router.push({ name: 'contract-list' })
  } finally {
    submitting.value = false
  }
}

onMounted(loadDetail)
</script>
