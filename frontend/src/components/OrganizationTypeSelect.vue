<template>
  <q-select
    v-model="innerValue"
    :options="options"
    :loading="loading"
    :label="label"
    option-value="code"
    option-label="label"
    emit-value
    map-options
    outlined
    dense
    clearable
    @update:model-value="onUpdate"
    class="full-width"
  >
    <template v-slot:no-option>
      <q-item>
        <q-item-section class="text-grey"> 无匹配数据 </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { api } from 'boot/axios'; // 假设你已配置 axios boot 文件

// 定义属性
const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    default: '公司类型'
  }
});

// 定义事件
const emit = defineEmits(['update:model-value', 'change']);

const innerValue = ref(props.modelValue);
const options = ref([]);
const loading = ref(false);

/**
 * 从服务器加载字典数据
 */
const loadOptions = async () => {
  loading.value = true;
  try {
    // 严格匹配后端接口：type='公司类型'
    const response = await api.get('/api/common/dictionaries', {
      params: { type: '公司类型' }
    });
    options.value = response.data;
  } catch (error) {
    console.error('获取公司类型数据失败:', error);
  } finally {
    loading.value = false;
  }
};

/**
 * 监听外部 v-model 变化
 */
watch(() => props.modelValue, (newVal) => {
  innerValue.value = newVal;
});

/**
 * 值变动时通知父组件
 */
const onUpdate = (val) => {
  emit('update:model-value', val);
  // 额外抛出 change 事件，方便父组件处理复杂逻辑
  emit('change', options.value.find(opt => opt.code === val));
};

// 组件挂载时初始化数据
onMounted(() => {
  loadOptions();
});
</script>
