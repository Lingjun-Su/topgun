<template>
  <q-select
    v-model="innerValue"
    :options="provinceOptions"
    option-value="code"
    option-label="name"
    :loading="loading"
    label="所属省份"
    outlined
    dense
    clearable
    map-options
    emit-value
    options-dense
    @filter="filterFn"
    @update:model-value="onChanged"
  >
  </q-select>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { areaApi } from 'src/api/modules';
import { useQuasar } from 'quasar';

// 接收父组件的 v-model
const props = defineProps({
  modelValue: [String, Number]
});
const emit = defineEmits(['update:modelValue', 'change']);

const $q = useQuasar();
const loading = ref(false);
const innerValue = ref(props.modelValue);
const allProvinces = ref([]); // 原始全量数据
const provinceOptions = ref([]); // 渲染用的过滤数据

// 监听父组件值变化
watch(() => props.modelValue, (newVal) => {
  innerValue.value = newVal;
});

/**
 * 核心：加载省份数据
 */
const loadProvinces = async () => {
  loading.value = true;
  try {

    const res = await areaApi.getProvinces();
    console.log("province",res)
    // 严谨判断：通常 Laravel 返回 res.data 或 res.data.data
    allProvinces.value = res.data || [];
    provinceOptions.value = allProvinces.value;
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: '省份数据加载失败',
      caption: error.message
    });
  } finally {
    loading.value = false;
  }
};

/**
 * 过滤功能：支持全量显示下的快速检索
 */
const filterFn = (val, update) => {
  if (val === '') {
    update(() => {
      provinceOptions.value = allProvinces.value;
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    provinceOptions.value = allProvinces.value.filter(
      v => v.name.toLowerCase().indexOf(needle) > -1 || v.code.indexOf(needle) > -1
    );
  });
};

const onChanged = (val) => {
  console.log('change province',val)
  emit('update:modelValue', val);
  emit('change', val);
};

// 初始化
onMounted(() => {
  loadProvinces();
});
</script>
