<template>
  <q-page padding>
    <q-card flat bordered class="q-pa-md mx-auto" style="max-width: 800px">
      <q-card-section>
        <div class="text-h6 text-primary">
          <q-icon name="science" /> 产品订单测试数据推送 (Laravel 12 + SQL Server)
        </div>
        <div class="text-subtitle2 text-grey-7">此操作将在 product_order_tests 表中生成记录用于数据审计校验</div>
      </q-card-section>

      <q-separator inset />

      <q-card-section>
        <q-form @submit="onSubmit" class="row q-col-gutter-md">
          <div class="col-12 col-md-6">
            <q-input
              v-model="form.order_no"
              label="订单编号 *"
              hint="自动生成或手动输入"
              dense
              outlined
              :rules="[val => !!val || '请输入订单编号']"
            />
          </div>

          <div class="col-12 col-md-6">
            <q-input
              v-model.number="form.product_id"
              type="number"
              label="产品 ID *"
              dense
              outlined
              :rules="[val => !!val || '请输入关联产品ID']"
            />
          </div>

          <div class="col-12 col-md-4">
            <q-input
              v-model.number="form.quantity"
              type="number"
              label="数量 *"
              dense
              outlined
              :rules="[val => val > 0 || '数量必须大于0']"
            />
          </div>

          <div class="col-12 col-md-4">
            <q-input
              v-model.number="form.unit_price"
              type="number"
              step="0.01"
              label="单价 *"
              dense
              outlined
              :rules="[val => val >= 0 || '单价不能为负数']"
            />
          </div>

          <div class="col-12 col-md-4">
            <q-input
              :model-value="totalPrice"
              label="总金额 (自动计算)"
              dense
              outlined
              readonly
              bg-color="grey-2"
            />
          </div>

          <div class="col-12 col-md-6">
            <q-select
              v-model="form.status"
              :options="statusOptions"
              label="订单状态"
              dense
              outlined
              emit-value
              map-options
            />
          </div>

          <div class="col-12">
            <q-input
              v-model="form.remark"
              type="textarea"
              label="备注 (Audit 跟踪测试)"
              dense
              outlined
              rows="3"
            />
          </div>

          <div class="col-12 q-mt-md text-right">
            <q-btn
              label="生成测试数据"
              type="submit"
              color="primary"
              :loading="loading"
              icon="send"
            />
            <q-btn
              label="重置"
              type="reset"
              flat
              color="grey"
              class="q-ml-sm"
              @click="resetForm"
            />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useQuasar } from 'quasar'
import { productOrderTestApi } from 'src/api/modules'

const $q = useQuasar()
const loading = ref(false)

/**
 * 初始化表单数据
 * 符合 SQL Server 2019 字段规范
 */
const initialForm = {
  order_no: 'ORD-' + Date.now(),
  product_id: null,
  quantity: 1,
  unit_price: 0,
  status: 'draft',
  remark: 'System auto-generated test data'
}

const form = ref({ ...initialForm })

// 状态选项
const statusOptions = [
  { label: '草稿', value: 'draft' },
  { label: '已确认', value: 'confirmed' },
  { label: '已完成', value: 'completed' }
]

// 计算总价
const totalPrice = computed(() => {
  return (form.value.quantity * form.value.unit_price).toFixed(2)
})

/**
 * 提交数据至后端
 * 调用 productOrderTestApi.store
 */
async function onSubmit() {
  loading.value = true
  try {
    // 组装数据，包含计算后的总价
    const payload = {
      ...form.value,
      total_amount: totalPrice.value
    }

    const response = await productOrderTestApi.store(payload)

    $q.notify({
      type: 'positive',
      message: '测试订单推送成功！',
      caption: `ID: ${response.data.id} 已记录在 audits 表中`,
      position: 'top'
    })

    // 提交成功后刷新订单号，防止重复冲突
    form.value.order_no = 'ORD-' + Date.now()
  } catch (error) {
    console.error('Submission failed:', error)
    $q.notify({
      type: 'negative',
      message: '推送失败',
      caption: error.response?.data?.message || '服务器连接异常'
    })
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.value = { ...initialForm, order_no: 'ORD-' + Date.now() }
}
</script>
