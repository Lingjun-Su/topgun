<template>
  <q-select
    v-model="internalValue"
    :options="options"
    option-value="pid"
    :option-label="row => row.name + (row.organization ? ` (${row.organization.short_name})` +row.pid : '')"
    emit-value
    map-options
    use-input
    outlined
    dense
    clearable
    label="选择Pid"
    :loading="loading"
    @filter="filterFn"
  >
    <template v-slot:no-option>
      <q-item>
        <q-item-section class="text-grey">
          未找到匹配的Pid
        </q-item-section>
      </q-item>
    </template>

    <template v-slot:option="scope">
      <q-item v-bind="scope.itemProps">
        <q-item-section>
          <q-item-label>{{ scope.opt.name+scope.opt.pid }}</q-item-label>
          <q-item-label caption v-if="scope.opt.organization">
            所属组织: {{ scope.opt.organization.short_name }}
          </q-item-label>
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref, computed } from 'vue'
import { api } from 'boot/axios'

// 定义 Props
const props = defineProps({
  modelValue: {
    type: [Number, String, null],
    default: null
  }
})

// 定义 Emits 用于实现 v-model 绑定
const emit = defineEmits(['update:modelValue'])

const options = ref([])
const loading = ref(false)

// 计算属性：同步内部值与外部 v-model
const internalValue = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

/**
 * 远程搜索过滤函数
 * @param val 用户输入的搜索词
 * @param update 更新下拉列表的回调
 * @param abort 中止操作的回调
 */
const filterFn = async (val, update, abort) => {
  // 如果输入字数太少，可以根据需要限制不触发请求
  // if (val.length < 1) { abort(); return }

  update(async () => {
    loading.value = true
    try {
      const response = await api.get('/api_v2/ThirdChannel/channels', {
        params: {
          search: val,
          with_org: 1 // 告知后端需要关联组织数据
        }
      })
      // 假设后端返回的是 data.data (Laravel 分页结构)
      options.value = response.data.data.data
    } catch (error) {
      console.error('获取产品列表失败:', error)
      abort()
    } finally {
      loading.value = false
    }
  })
}
</script>
