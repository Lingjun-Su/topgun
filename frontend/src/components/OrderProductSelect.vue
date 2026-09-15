<template>
  <q-select
    v-model="internalValue"
    :options="options"
    option-value="id"
    :option-label="row => row.name + (row.organization ? ` (${row.organization.short_name})` : '')"
    emit-value
    map-options
    use-input
    outlined
    dense
    clearable
    :label="label"
    :loading="loading"
    :disable="disableByBiz"
    @filter="filterFn"
  >
    <template v-slot:no-option>
      <q-item>
        <q-item-section class="text-grey">
          {{ businessId ? '该业务下未找到匹配产品' : '请先选择业务' }}
        </q-item-section>
      </q-item>
    </template>
    </q-select>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { productApi } from 'src/api/modules'

const props = defineProps({
  modelValue: [Number, String, null],
  businessId: [Number, String, null], // 新增：接收业务ID 在
  label: { type: String, default: '选择产品' }
})

const emit = defineEmits(['update:modelValue'])

const options = ref([])
const loading = ref(false)

// 如果要求必须选了业务才能选产品，可以设置 disable
const disableByBiz = computed(() => !props.businessId)

const internalValue = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

// 关键：当业务 ID 改变时，重置当前选中的产品
watch(() => props.businessId, () => {
  internalValue.value = null
})

const filterFn = async (val, update, abort) => {
  // 如果没有业务ID且逻辑要求强制关联，则不请求
  if (!props.businessId) {
    abort()
    return
  }

  update(async () => {
    loading.value = true

    try {
      const response = await productApi.list({
        search: val,
        business_id: props.businessId, // 将业务ID传给后端过滤 [cite: 1]
        with_org: 1

      })
      // 根据你提供的 Laravel 分页结构解析
      options.value = response.data.data || []

    } catch (error) {
      console.log("error",error);
      abort()
    } finally {
      loading.value = false
    }
  })
}
</script>
