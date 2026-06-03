<!-- src/components/RegionSelector.vue -->
<!--
  组件名称：RegionSelector
  功能描述：实现省市区镇四级联动选择器。
    - 支持一至四级选择（省、市、区、镇）。
    - 数据字段：adcode（区域代码，作为唯一标识）、parent_adcode（父级代码）、level（级别：0-省、1-市、2-区、3-镇）、name（区域名称，假设后端返回）。
    - 选择后，emit 'update:modelValue' 事件，返回最底层选中的adcode（如果有四级，否则返回最后一级）。
    - 使用Quasar的QSelect组件实现联动，下拉选项懒加载（通过API调用后端获取子级数据）。
    - 依赖Axios进行API请求（假设已在src/boot/axios.js中配置）。
    - 错误处理：API失败时显示通知。
    - 审计与删除：本组件不涉及数据修改，仅查询；后端需处理审计逻辑。
  命名规范：遵循Quasar PascalCase组件命名。
  使用示例：
    <RegionSelector v-model="selectedAdcode" />
-->
<template>
  <div class="row q-gutter-md">
    <!-- 省级选择器 -->
    <q-select
      v-model="province"
      :options="provinceOptions"
      label="省"
      outlined
      dense
      clearable
      @update:model-value="onProvinceChange"
      :loading="loading.province"
      style="width: 200px;"
    />

    <!-- 市级选择器（仅在省选中后显示） -->
    <q-select
      v-if="province"
      v-model="city"
      :options="cityOptions"
      label="市"
      outlined
      dense
      clearable
      @update:model-value="onCityChange"
      :loading="loading.city"
      style="width: 200px;"
    />

    <!-- 区级选择器（仅在市选中后显示） -->
    <q-select
      v-if="city"
      v-model="district"
      :options="districtOptions"
      label="区"
      outlined
      dense
      clearable
      @update:model-value="onDistrictChange"
      :loading="loading.district"
      style="width: 200px;"
    />

    <!-- 镇级选择器（仅在区选中后显示，如果有镇级数据） -->
    <q-select
      v-if="district"
      v-model="town"
      :options="townOptions"
      label="镇"
      outlined
      dense
      clearable
      @update:model-value="onTownChange"
      :loading="loading.town"
      style="width: 200px;"
    />
  </div>
</template>

<script setup>
import { ref, watch,onMounted } from 'vue';
import { useQuasar } from 'quasar';
// import axios from 'axios'; // 假设已在boot/axios.js中配置全局axios实例
import { api } from 'boot/axios'; // 假设已配置 axios boot

const $q = useQuasar();

// Props: 支持v-model绑定
const props = defineProps({
  modelValue: {
    type: String, // adcode字符串
    default: null
  }
});

// Emits: 更新modelValue
const emit = defineEmits(['update:modelValue']);

// 状态变量：当前选中值（对象形式，包含adcode和name）
const province = ref(null);
const city = ref(null);
const district = ref(null);
const town = ref(null);

// 选项列表
const provinceOptions = ref([]);
const cityOptions = ref([]);
const districtOptions = ref([]);
const townOptions = ref([]);

// 加载状态
const loading = ref({
  province: false,
  city: false,
  district: false,
  town: false
});

// 初始化：加载省级数据（level=0，parent_adcode=null或0）
async function loadProvinces() {
  loading.value.province = true;
  try {
    const response = await api.get('/regions', { params: { parent_adcode: null, level: 0 } });

    provinceOptions.value = response.data.map(item => ({
      label: item.name,
      value: item.adcode
    }));
  } catch (error) {
    console.log('加载省级数据失败',error);
    $q.notify({ type: 'negative', message: '加载省级数据失败' });
  } finally {
    loading.value.province = false;
  }
}

// 省级变更：加载市级，清除下级
async function onProvinceChange(value) {
  city.value = null;
  district.value = null;
  town.value = null;
  cityOptions.value = [];
  districtOptions.value = [];
  townOptions.value = [];
  if (value) {
    await loadCities(value);
  }
  updateSelectedAdcode();
}

// 加载市级数据
async function loadCities(parentAdcode) {
  loading.value.city = true;
  try {
    const response = await api.get('/regions', { params: { parent_adcode: parentAdcode, level: 1 } });
    cityOptions.value = response.data.map(item => ({
      label: item.name,
      value: item.adcode
    }));
  } catch (error) {
    console.log("加载市级数据失败",error);
    $q.notify({ type: 'negative', message: '加载市级数据失败' });
  } finally {
    loading.value.city = false;
  }
}

// 市级变更：加载区级，清除下级
async function onCityChange(value) {
  district.value = null;
  town.value = null;
  districtOptions.value = [];
  townOptions.value = [];
  if (value) {
    await loadDistricts(value);
  }
  updateSelectedAdcode();
}

// 加载区级数据
async function loadDistricts(parentAdcode) {
  loading.value.district = true;
  try {
    const response = await api.get('/regions', { params: { parent_adcode: parentAdcode, level: 2 } });
    districtOptions.value = response.data.map(item => ({
      label: item.name,
      value: item.adcode
    }));
  } catch (error) {
    console.log("加载区级数据失败",error);
    $q.notify({ type: 'negative', message: '加载区级数据失败' });
  } finally {
    loading.value.district = false;
  }
}

// 区级变更：加载镇级，清除下级
async function onDistrictChange(value) {
  town.value = null;
  townOptions.value = [];
  if (value) {
    await loadTowns(value);
  }
  updateSelectedAdcode();
}

// 加载镇级数据
async function loadTowns(parentAdcode) {
  loading.value.town = true;
  try {
    const response = await api.get('/regions', { params: { parent_adcode: parentAdcode, level: 3 } });
    townOptions.value = response.data.map(item => ({
      label: item.name,
      value: item.adcode
    }));
  } catch (error) {
    console.log("加载镇级数据失败",error);
    $q.notify({ type: 'negative', message: '加载镇级数据失败' });
  } finally {
    loading.value.town = false;
  }
}

// 镇级变更：更新选中adcode
function onTownChange() {
  updateSelectedAdcode();
}

// 更新emit值：返回最底层adcode（镇>区>市>省）
function updateSelectedAdcode() {
  let selected = null;
  if (town.value) selected = town.value;
  else if (district.value) selected = district.value;
  else if (city.value) selected = city.value;
  else if (province.value) selected = province.value;
  emit('update:modelValue', selected);
}

// 监听props变化（支持外部设置初始值，但本组件不实现反向填充，视需求扩展）
watch(() => props.modelValue, (newVal) => {
  console.log("watch",newVal);
  // 如果需要根据adcode反向填充各级，可扩展API查询路径，但当前不实现以保持简单。
});

// 组件挂载时加载省级
onMounted(() => {
  loadProvinces();
});
</script>
