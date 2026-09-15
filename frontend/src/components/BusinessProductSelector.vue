<template>
  <div class="row q-col-gutter-md">
    <div class="col-12 col-sm-6">
      <q-select
        v-model="selectedBusiness"
        :options="businessOptions"
        label="选择业务单位"
        option-value="id"
        option-label="name"
        map-options
        emit-value
        outlined
        dense
        :loading="loadingBusiness"
        @update:model-value="onBusinessChange"
      >
        <template v-slot:no-option>
          <q-item><q-item-section class="text-grey">无可用数据</q-item-section></q-item>
        </template>
      </q-select>
    </div>

    <div class="col-12 col-sm-6">
      <q-select
        v-model="selectedProduct"
        :options="productOptions"
        label="选择产品"
        option-value="id"
        option-label="name"
        map-options
        emit-value
        outlined
        dense
        :disable="!selectedBusiness"
        :loading="loadingProduct"
        @update:model-value="emitChange"
      >
        <template v-slot:no-option>
          <q-item><q-item-section class="text-grey">请先选择业务或无产品</q-item-section></q-item>
        </template>
      </q-select>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineEmits, defineProps, watch } from 'vue'
import { businessApi, productApi } from 'src/api/modules'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ bus_code: null, sku_code: null })
  },
  allowedBusinessIds: {
    type: Array,
    default: null
  },
  allowedProductIds: {
    type: Array,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// 状态变量
const selectedBusiness = ref(null)
const selectedProduct = ref(null)
const businessOptions = ref([])
const productOptions = ref([])
const loadingBusiness = ref(false)
const loadingProduct = ref(false)

/**
 * 初始化：加载业务单位列表
 */
const loadBusinesses = async () => {
  loadingBusiness.value = true
  try {
    const res = await businessApi.list({ status: 1 }) // 仅获取启用状态
    let list = res.data.data || res.data
    // 如果有权限限制，只显示允许的业务
    if (props.allowedBusinessIds && props.allowedBusinessIds.length > 0) {
      list = list.filter(item => props.allowedBusinessIds.includes(item.id))
    }
    businessOptions.value = list
  } catch (error) {
    console.error('加载业务列表失败:', error)
  } finally {
    loadingBusiness.value = false
  }
}

/**
 * 业务单位切换逻辑
 * @param {Number|String} busId 业务ID
 */
const onBusinessChange = async (busId) => {
  // 重置产品选择
  selectedProduct.value = null
  productOptions.value = []

  if (!busId) return

  loadingProduct.value = true
  try {
    // 根据业务ID获取关联产品
    const res = await productApi.list({ business_id: busId })
    let list = res.data.data || res.data
    // 如果有权限限制，只显示允许的产品
    if (props.allowedProductIds && props.allowedProductIds.length > 0) {
      list = list.filter(item => props.allowedProductIds.includes(item.id))
    }
    productOptions.value = list
  } catch (error) {
    console.error('加载产品列表失败:', error)
  } finally {
    loadingProduct.value = false
  }

  emitChange()
}

/**
 * 向上级组件同步数据
 */
const emitChange = () => {
  const result = {
    business_id: selectedBusiness.value,
    product_id: selectedProduct.value
  }
  emit('update:modelValue', result)
  emit('change', result)
}

// 监听外部 modelValue 变化（用于回显）
watch(() => props.modelValue, (newVal) => {
  if (newVal.business_id !== selectedBusiness.value) {
    selectedBusiness.value = newVal.business_id
    if (newVal.business_id) onBusinessChange(newVal.business_id)
  }
  selectedProduct.value = newVal.product_id
}, { deep: true })

// 当权限配置变化时重新加载业务列表
watch(() => props.allowedBusinessIds, () => {
  loadBusinesses()
})

onMounted(() => {
  loadBusinesses()
})
</script>
