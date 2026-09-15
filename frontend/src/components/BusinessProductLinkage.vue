<template>
  <div class="row q-col-gutter-sm">
    <div class="col-12 col-sm-6">
      <BusinessSelect
        v-model="proxyBizId"
        @change="handleBizChange"
      />
    </div>

    <div class="col-12 col-sm-6">
      <OrderProductSelect
        v-model="proxyProdId"
        :business-id="proxyBizId"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import BusinessSelect from './BusinessSelect.vue'
import OrderProductSelect from './OrderProductSelect.vue'

const props = defineProps({
  businessId: [Number, String],
  productId: [Number, String]
})

const emit = defineEmits(['update:businessId', 'update:productId', 'linkage-change'])

// 计算属性实现双向绑定
const proxyBizId = computed({
  get: () => props.businessId,
  set: (val) => emit('update:businessId', val)
})

const proxyProdId = computed({
  get: () => props.productId,
  set: (val) => emit('update:productId', val)
})

/**
 * 当业务切换时的处理
 */
const handleBizChange = (val) => {
  // 触发一个自定义事件，方便父组件监听并清除报表数据等操作
  emit('linkage-change', { businessId: val, productId: null })
}
</script>
