<template>
  <q-select
    v-model="modelValue"
    :options="options"
    :loading="loading"
    :label="label"
    option-value="id"
    option-label="short_name"
    emit-value
    map-options
    outlined
    dense
    clearable
    @update:model-value="onUpdate"
    @filter="filterFn"
    use-input
    input-debounce="300"
  >
    <template v-slot:no-option>
      <q-item>
        <q-item-section class="text-grey">
          未找到匹配的业务
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { businessApi } from 'src/api/modules';

/**
 * 严格定义的 Props
 */
const props = defineProps({
  modelValue: [Number, String],
  label: {
    type: String,
    default: '选择业务'
  }
});

const emit = defineEmits(['update:modelValue', 'change']);

// 状态控制
const options = ref([]);
const fullOptions = ref([]); // 缓存全量数据
const loading = ref(false);

/**
 * 格式化 v-model 绑定
 */
const modelValue = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
});

/**
 * 获取运营商数据
 */
const fetchCarriers = async () => {
  loading.value = true;
  try {
    // 假设后端接口返回 { data: [...] }
    const response = await businessApi.list();
    fullOptions.value = response.data;
    options.value = response.data;
  } catch (error) {
    console.error('获取业务失败:', error);
  } finally {
    loading.value = false;
  }
};

/**
 * 过滤功能（前端搜索）
 */
const filterFn = (val, update) => {
  if (val === '') {
    update(() => {
      options.value = fullOptions.value;
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    options.value = fullOptions.value.filter(
      v => v.short_name.toLowerCase().indexOf(needle) > -1
    );
  });
};

const onUpdate = (val) => {
  emit('change', val);
};

onMounted(() => {
  fetchCarriers();
});
</script>
