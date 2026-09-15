<template>
  <q-field
    :value="innerValue"
    :rules="props.rules"
    :label="props.label"
    stack-label
    outlined
    class="q-mb-md"
  >
    <template v-slot:control>
      <div class="row no-gutter q-mt-xs" style="width: 100%">
        <!-- 加载状态 -->
        <template v-if="loading">
          <div class="col-12 text-grey">加载中...</div>
        </template>

        <!-- 有选项时渲染复选框 -->
        <template v-else-if="options.length > 0">
          <div
            v-for="option in options"
            :key="option.code"
            class="col-12 col-sm-6 col-md-4 col-lg-2 col-xl-1"
          >
            <q-checkbox
              v-model="innerValue"
              :val="option.code"
              :label="option.name"
              :disable="loading"
              dense
              size="sm"
            />
          </div>
        </template>

        <!-- 无选项提示 -->
        <template v-else>
          <div class="col-12 text-grey">暂无省份数据</div>
        </template>
      </div>
    </template>
  </q-field>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { areaApi } from 'src/api/modules';
import { useQuasar } from 'quasar';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  label: {
    type: String,
    default: '选择省份'
  },
  rules: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue', 'change']);

const $q = useQuasar();
const options = ref([]);
const loading = ref(false);
const innerValue = ref(props.modelValue);

const loadProvinces = async () => {
  loading.value = true;
  try {
    const response = await areaApi.getProvinces();
    options.value = response.data.data || [];
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: '加载省份数据失败，请检查网络或权限',
      position: 'top'
    });
    console.error('Fetch Areas Error:', error);
  } finally {
    loading.value = false;
  }
};

watch(() => props.modelValue, (newVal) => {
  innerValue.value = newVal;
}, { deep: true });

const emitUpdate = (val) => {
  emit('update:modelValue', val);
  emit('change', val);
};

watch(innerValue, (val) => {
  emitUpdate(val);
}, { deep: true });

onMounted(loadProvinces);
</script>
