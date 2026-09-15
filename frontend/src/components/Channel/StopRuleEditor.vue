<template>
  <div>
    <!-- 顶部说明 -->
    <div class="text-caption text-grey-6 q-mb-sm">
      配置 C → A 请求的停止条件：满足任一启用条件即切断转发并返回对应提示。条件类型与表单由系统预定义，只需填写阈值/时间等参数。
    </div>

    <!-- 添加条件 -->
    <div class="row items-center q-gutter-sm q-mb-md">
      <q-select
        v-model="selectedType"
        :options="typeOptions"
        label="选择条件类型"
        dense outlined
        emit-value
        map-options
        style="width: 260px"
      />
      <q-btn flat color="primary" icon="add" label="添加条件" :disable="!selectedType" @click="addCondition" />
    </div>

    <!-- 条件列表 -->
    <div v-if="list.length" class="q-gutter-md">
      <q-card v-for="(cond, ci) in list" :key="cond.id" flat bordered :class="cond.enabled ? '' : 'text-grey-5'">
        <q-card-section class="row items-center q-py-sm"
          :class="cond.enabled ? 'bg-positive-1' : 'bg-grey-2'">
          <q-toggle
            v-model="cond.enabled"
            :true-value="true"
            :false-value="false"
            label="启用"
            dense
            class="q-mr-sm"
          />
          <q-icon name="stop_circle" size="sm" :color="cond.enabled ? 'negative' : 'grey-6'" />
          <!-- 类型切换下拉 -->
          <q-select
            v-model="cond.condition_type"
            :options="typeOptions"
            dense
            borderless
            emit-value
            map-options
            class="q-ml-sm"
            style="min-width: 150px"
            @update:model-value="switchType(cond)"
          />
          <q-space />
          <q-btn flat round dense icon="delete" color="negative" size="sm" @click="removeCondition(ci)" />
        </q-card-section>

        <q-card-section class="q-pt-sm q-gutter-sm">
          <!-- 产品维度（按产品分开算、填不同阈值） -->
          <q-select
            v-model="cond.product_id"
            :options="productOptions"
            label="适用产品"
            dense
            outlined
            emit-value
            map-options
            clearable
            hint="选择该条件统计/生效的产品，选全部产品则不限"
          />

          <!-- 触发逻辑摘要 -->
          <div class="text-caption bg-grey-2 rounded-borders q-pa-sm">
            <q-icon name="alt_route" size="xs" class="q-mr-xs" />
            触发逻辑：{{ summary(cond) }}
          </div>

          <!-- 提示文案 -->
          <q-input v-model="cond.message" label="拦截提示文案" dense outlined
            placeholder="返回给下游的提示语"
            :error="!cond.message" error-message="请填写拦截提示文案" />

          <!-- 生效环节 -->
          <div class="row items-center">
            <span class="text-caption q-mr-sm text-grey-7">生效环节</span>
            <q-toggle v-model="condStep[ci].getCode" label="getCode" dense />
            <q-toggle v-model="condStep[ci].submit" label="submit" dense />
          </div>

          <!-- 按类型 schema 渲染参数表单 -->
          <div class="row q-col-gutter-sm">
            <div v-for="f in schemaOf(cond.condition_type)" :key="f.key" class="col-6">
              <q-input
                v-if="f.type === 'number'"
                v-model="cond.params[f.key]"
                :label="f.label"
                type="number"
                dense
                outlined
                :hint="f.hint || undefined"
              />
              <q-input
                v-else-if="f.type === 'text'"
                v-model="cond.params[f.key]"
                :label="f.label"
                dense
                outlined
                :hint="f.hint || undefined"
              />
              <q-input
                v-else-if="f.type === 'time'"
                v-model="cond.params[f.key]"
                :label="f.label"
                dense
                outlined
                mask="##:##"
                placeholder="HH:MM"
                :hint="f.hint || undefined"
              />
              <q-select
                v-else-if="f.type === 'select'"
                v-model="cond.params[f.key]"
                :options="f.options || []"
                :label="f.label"
                dense
                outlined
                emit-value
                map-options
                :hint="f.hint || undefined"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>
    </div>

    <div v-else class="text-center q-py-md text-grey-6">
      尚未配置停止条件，添加一个以启用拦截。
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { channelApi } from 'src/api/modules'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  // 产品选项（按产品隔离统计/填不同阈值）
  products: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const productOptions = computed(() => [
  { label: '全部产品', value: null },
  ...props.products.map((p) => ({ label: p.product_name || `产品(${p.product_id})`, value: p.product_id })),
])

// 条件类型清单：{ typeKey -> {label, cost, schema} }
const typeRegistry = ref({})
const typeOptions = computed(() =>
  Object.entries(typeRegistry.value).map(([value, t]) => ({ label: t.label, value }))
)
const selectedType = ref(null)

// 条件列表（内部响应式副本）
const list = ref([])
// 每个条件的 step 开关辅助状态
const condStep = ref([])

let seq = 0
const newId = () => `cond_${Date.now()}_${seq++}`

const schemaOf = (t) => (typeRegistry.value[t] && typeRegistry.value[t].schema) || []
// 触发逻辑摘要：把已有类型的描述文案拼进去，若无则是 schema 参数拼接
const typeDesc = (t) => typeRegistry.value[t]?.desc || ''

function summary(cond) {
  if (!cond || !cond.condition_type) return ''
  const desc = typeDesc(cond.condition_type)
  if (desc) return desc
  const parts = schemaOf(cond.condition_type).map((f) => {
    const v = cond.params?.[f.key]
    if (v === undefined || v === null || v === '') return null
    return `${f.label} ${v}`
  }).filter(Boolean)
  return parts.length ? parts.join('，') : '填写下方参数后生效'
}

// 切换条件类型：重置参数为该类型默认值，保留启用/环节/文案
function switchType(cond) {
  cond.params = defaultParams(cond.condition_type)
  emitModel()
}

function init() {
  list.value = props.modelValue.map(adaptFromServer)
  syncCondStep()
}

function adaptFromServer(c) {
  const steps = c.step || ['getCode', 'submit']
  return {
    id: c.id || newId(),
    condition_type: c.condition_type,
    enabled: !!c.enabled,
    message: c.message || '',
    params: { ...defaultParams(c.condition_type), ...(c.params || {}) },
    step: steps,
    product_id: c.product_id ?? null,
  }
}

function defaultParams(type) {
  const out = {}
  for (const f of schemaOf(type)) out[f.key] = f.default ?? ''
  return out
}

function syncCondStep() {
  condStep.value = list.value.map((c) => ({
    getCode: c.step.includes('getCode'),
    submit: c.step.includes('submit'),
  }))
}

function addCondition() {
  if (!selectedType.value) return
  const cond = {
    id: newId(),
    condition_type: selectedType.value,
    enabled: true,
    message: '',
    params: defaultParams(selectedType.value),
    step: ['getCode', 'submit'],
    product_id: null,
  }
  list.value.push(cond)
  syncCondStep()
  selectedType.value = null // 添加后复位，便于继续添加
}

function removeCondition(ci) {
  list.value.splice(ci, 1)
  condStep.value.splice(ci, 1)
  emitModel()
}

function emitModel() {
  const out = list.value.map((c, ci) => ({
    id: c.id,
    condition_type: c.condition_type,
    enabled: c.enabled,
    message: c.message,
    params: c.params,
    product_id: c.product_id ?? null,
    step: condStep.value[ci]
      ? [condStep.value[ci].getCode ? 'getCode' : null, condStep.value[ci].submit ? 'submit' : null].filter(Boolean)
      : ['getCode', 'submit'],
  }))
  emit('update:modelValue', out)
}

// 任何变动都会触发同步
watch(
  list,
  () => emitModel(),
  { deep: true }
)
watch(condStep, () => emitModel(), { deep: true })

onMounted(async () => {
  try {
    const res = await channelApi.stopConditionTypes()
    typeRegistry.value = res?.data || {}
  } catch (e) {
    console.error('加载停止条件类型失败', e)
  }
  init()
})
</script>
