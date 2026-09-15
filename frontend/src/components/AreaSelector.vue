<template>
  <div class="row q-col-gutter-sm">
    <div class="col-12 col-sm-3">
      <q-select
        v-model="modelValue.province_code"
        :options="provinces"
        label="省份"
        option-value="code"
        option-label="name"
        emit-value
        map-options
        outlined
        @update:model-value="onProvinceChange"
        dense
        style="min-width: 100px;"
      />
    </div>

    <div class="col-12 col-sm-3">
      <q-select
        v-model="modelValue.city_code"
        :options="cities"
        :disable="!modelValue.province_code"
        label="城市"
        option-value="code"
        option-label="name"
        emit-value
        map-options
        outlined
        @update:model-value="onCityChange"
        dense
        style="min-width: 100px;"
      />
    </div>

    <div class="col-12 col-sm-3">
      <q-select
        v-model="modelValue.district_code"
        :options="districts"
        :disable="!modelValue.city_code"
        label="区/县"
        option-value="code"
        option-label="name"
        emit-value
        map-options
        outlined
        @update:model-value="onDistrictChange"
        dense
        style="min-width: 100px;"
      />
    </div>

    <div class="col-12 col-sm-3">
      <q-select
        v-model="modelValue.street_code"
        :options="streets"
        :disable="!modelValue.district_code"
        label="街道/乡镇"
        option-value="code"
        option-label="name"
        emit-value
        map-options
        outlined
        @update:model-value="onStreetChange"
        dense
        style="min-width: 100px;"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted ,computed,watch} from 'vue'
import { areaApi } from 'src/api/modules' // 假设你已配置好 axios boot 文件

// 响应式数据：各级选项列表
const provinces = ref([])
const cities = ref([])
const districts = ref([])
const streets = ref([])
const parent_id =ref('');//父页面传递过来的ID

const props = defineProps({
  // v-model 默认对应的就是 modelValue
  modelValue: {
    type: Object,
    default: () => ({
      province_code: null,
      city_code: null,
      district_code: null,
      street_code: null
    })
  }
})
const modelValue = computed({
  get: () => props.modelValue,
})



/**
 * 初始化：获取省级数据
 */
const fetchProvinces = async () => {
  try {
    const res = await areaApi.getProvinces('0');

    provinces.value = res.data.data || [];
    if(modelValue.value.province_code!=null){//如果已经有省
      fetchChildren(modelValue.value.province_code,cities)
    }
  } catch (error) {
    console.error('获取省级数据失败:', error)
  }
}

/**
 * 通用获取子级数据函数
 * @param {Number} parentId 父级ID
 * @param {Ref} targetOptions 目标渲染的数组
 */
const fetchChildren = async (parentId, targetOptions) => {
  if (!parentId) return
  try {
    const res = await areaApi.fetchChildren(parentId)
    targetOptions.value = res.data.data || [];
    if(targetOptions.value==cities.value && modelValue.value.city_code!=null){//如果有市级，读取区
      fetchChildren(modelValue.value.city_code,districts);
    }
    if(targetOptions.value==districts.value && modelValue.value.district_code!=null){//如果已有区级，读取街
      fetchChildren(modelValue.value.district_code,streets);
    }
  } catch (error) {
    console.error('获取子级数据失败:', error)
  }
}

// --- 联动逻辑处理 ---

const onProvinceChange = (val) => {
  modelValue.value.province_code = val

  if(parent_id.value!=modelValue.value.id){//如果ID不一致，表示父页面重新传递数据过来
    parent_id.value =modelValue.value.id;
  }else{
    cities.value = []
    districts.value = []
    streets.value = []
    modelValue.value.city_code = null
    modelValue.value.district_code = null
    modelValue.value.street_code = null
  }

  if (val) fetchChildren(val, cities)
}

const onCityChange = (val) => {
  districts.value = []
  streets.value = []

  modelValue.value.city_code = val
  modelValue.value.district_code = null
  modelValue.value.street_code = null
  if (val) fetchChildren(val, districts)
}

const onDistrictChange = (val) => {
  streets.value = []

  modelValue.value.district_code = val
  modelValue.value.street_code = null
  if (val) fetchChildren(val, streets)
  // emitValue(modelValue)
}

const onStreetChange = (val) => {
  modelValue.value.street_code = val
}
watch(() => props.modelValue.province_code, (newVal) => {

  // 走到这里，说明变化来自【父页面】传递参数
  console.log('执行父页面同步指令'+newVal)
  onProvinceChange(newVal)
}, { deep: true })

// 组件挂载时加载省份
onMounted(() => {
  fetchProvinces()
})
</script>
