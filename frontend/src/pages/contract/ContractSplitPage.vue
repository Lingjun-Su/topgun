<template>
  <q-page padding>
    <div class="row q-col-gutter-md justify-center">
      <div class="col-12 col-md-9">
        <!-- 头部操作栏 -->
        <div class="row items-center q-mb-md">
          <q-btn icon="arrow_back" flat round @click="$router.back()" />
          <div class="text-h6 q-ml-sm">执行合同详情</div>
          <q-space />

          <!-- 审批按钮组：仅在状态为 1 (审批中) 时显示 -->
          <div v-if="data.status === 1" class="q-gutter-sm">
            <q-btn label="驳回" color="negative" outline icon="close" @click="showApproveDialog('reject')" />
            <q-btn label="通过" color="positive" icon="check" @click="showApproveDialog('pass')" />
          </div>

          <q-badge :color="getStatusColor(data.status)" size="lg" class="q-pa-sm q-ml-md">
            {{ getStatusLabel(data.status) }}
          </q-badge>
        </div>
        <q-card flat bordered class="q-pa-md">
          <div class="text-h6 q-mb-md">框架合同拆分执行</div>

          <q-card flat bordered>
            <q-card-section horizontal>
              <q-card-section>
                <div>主合同: {{ master?.title }} ({{ master?.contract_no }})</div>
                <div>
                  <div>甲方：{{ master?.organization_a?.name }}</div>
                  <div>乙方：{{ master?.organization_b?.name }}</div>
                </div>
                <q-separator color="blue" ></q-separator>
                <div class="q-mt-sm">签订日期：{{ master?.signed_date }}</div>
                <div>开始日期：{{ master?.effective_date }}</div>
                <div>结束日期：{{ master?.effective_date }}</div>
                <q-separator color="blue"></q-separator>
                <div class="q-mt-sm">总额度: {{ master?.total_limit }}</div>
                <div>{{ master?.remarks }}</div>
              </q-card-section>

              <q-separator vertical color="blue"/>

              <q-card-section class="bg-grey-2" style="width: 99%;">
                <q-card-section>
                  <div class="text-h6">合同内容摘要</div>
                </q-card-section>
                <q-card-section class="q-pt-none">
                  {{ master?.summary }}
                </q-card-section>
              </q-card-section>
            </q-card-section>
          </q-card>

          <q-form @submit="handleSplit">
            <q-card flat bordered class="q-mt-sm">
              <q-card-section>
                <div class="row items-center no-wrap">
                  <div class="col">
                    <div class="text-h6">执行合同</div>
                  </div>
                </div>
              </q-card-section>

              <q-card-section>
                <template v-for="(item, index) in form.contracts" :key="index">
                  <div class="q-mb-sm q-mt-sm row">
                    <!--执行合同内容-->
                    <div class="col-11">
                      <div class="row q-col-gutter-md">
                        <div class="col-12 col-md-6">
                          <div class="text-caption q-mb-xs text-primary text-weight-bold">甲方公司 *</div>
                          <organization-tree-select
                            v-model="item.org_a_id"
                            placeholder="请选择甲方公司"
                            :rules="[val => !!val || '请选择甲方']"
                            @update:model-value="onPartyAChange"
                          />
                        </div>
                        <div class="col-12 col-md-6">
                          <div class="text-caption q-mb-xs text-secondary text-weight-bold">乙方公司 *</div>
                          <organization-tree-select
                            v-model="item.org_b_id"
                            placeholder="请选择乙方公司"
                            :rules="[val => !!val || '请选择乙方']"
                            @update:model-value="onPartyBChange"
                          />
                        </div>
                      </div>

                      <div class="row">
                        <div class="col q-mr-sm">
                          <q-input v-model="item.system_no" label="执行合同编号(唯一)" dense outlined :rules="[val => !!val || '必填']" />
                        </div>
                        <div class="col">
                          <q-input v-model="item.title" label="执行合同标题" dense outlined :rules="[val => !!val || '必填']" />
                        </div>
                      </div>

                      <div class="row q-gutter-md">
                        <q-select v-model="item.type" :options="['REVENUE', 'COST']" label="类型" dense outlined />
                        <q-input v-model.number="item.total_amount" type="number" label="金额" dense outlined  :rules="[val => !!val || '必填']" />
                        <q-input v-model.number="item.ratio" type="number" label="占比" dense outlined  :rules="[val => !!val || '必填']" />
                        <q-input v-model="item.radio" dense outlined label="区域"></q-input>
                      </div>
                      <div>
                        <q-input dense outlined v-model="item.remarks" label="备注"></q-input>
                      </div>

                    </div>
                    <!--删除按钮-->
                    <div class="col">
                      <q-btn icon="delete" color="negative" flat @click="removeRow(index)" v-if="form.contracts.length > 1" />
                    </div>
                  </div>
                  <q-separator size="6px" color="blue"></q-separator>
                </template>
              </q-card-section>

              <q-separator />

              <q-card-actions>
                <q-btn label="添加一行" color="primary" flat icon="add" @click="addRow" />
                <q-btn label="提交拆分" color="positive" type="submit" :loading="submitting" class="q-ml-sm" />
              </q-card-actions>
            </q-card>

          </q-form>
        </q-card>
      </div>
    </div>
  </q-page>

  <!-- 审批弹窗 -->
  <q-dialog v-model="approveDialog.show" persistent>
    <q-card style="min-width: 350px">
      <q-card-section>
        <div class="text-h6">{{ approveDialog.action === 'pass' ? '通过审批' : '驳回申请' }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input
          v-model="approveDialog.remark"
          type="textarea"
          label="审批备注/意见"
          outlined
          autofocus
          dense
        />
      </q-card-section>

      <q-card-actions align="right" class="text-primary">
        <q-btn flat label="取消" v-close-popup />
        <q-btn
          flat
          :label="approveDialog.action === 'pass' ? '确认通过' : '确认驳回'"
          :color="approveDialog.action === 'pass' ? 'positive' : 'negative'"
          @click="submitApprove(approveDialog)"
          :loading="approveDialog.loading"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>

</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { masterContractApi } from 'src/api/masterContract'
// 导入自定义组件组织
import OrganizationTreeSelect from 'components/OrganizationTreeSelect.vue'

const route = useRoute()
const router = useRouter()

const master = ref(null)
const submitting = ref(false)
// 审批弹窗状态
const approveDialog = reactive({
  show: false,
  action: '', // pass 或 reject
  remark: '',
  loading: false
})

const form = reactive({
  master_id: route.params.id,
  contracts: [
    { title: '', type: 'REVENUE', total_amount: 0, external_no: '' }
  ]
})

const addRow = () => {
  form.contracts.push({ title: '', type: 'REVENUE', total_amount: 0, external_no: '' })
}

const removeRow = (index) => {
  form.contracts.splice(index, 1)
}

const handleSplit = async () => {
  submitting.value = true
  try {
    // 拦截器会自动根据 200 code 弹出 Notify.positive
    await masterContractApi.splitContract(form)
    router.push('/execution-contract')
  } catch (error) {
    // 拦截器会自动处理 400 错误提示
    console.error(error)
  } finally {
    submitting.value = false
  }
}
// 唤起审批对话框
const showApproveDialog = (action) => {
  approveDialog.action = action
  approveDialog.remark = ''
  approveDialog.show = true
}

// 提交审批结果
const submitApprove = async (data) => {
  approveDialog.loading = true
  try {
    // 调用 API
    await masterContractApi.executionApprove(data.value.id, {
      action: approveDialog.action,
      remark: approveDialog.remark
    })

    // 成功后：关闭弹窗并刷新本地数据
    approveDialog.show = false
    // await loadDetail()
  } catch (e) {
    console.log("err",e);
    // 错误已被 axios 拦截器处理
  } finally {
    approveDialog.loading = false
  }
}
onMounted(async () => {
  if (form.master_id) {
    const res = await masterContractApi.show(form.master_id)
    master.value = res // 拦截器已拆包直接返回 data 层
  }
})
</script>
