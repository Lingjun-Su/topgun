<template>
  <div>
    <div class="text-subtitle2 text-grey-8 q-mb-sm">
      入站字段映射（外部字段 → B 内部字段）
    </div>
    <div class="text-caption text-grey-6 q-mb-sm">
      定义对方系统调用接口时字段名称/结构的翻译规则。未配置时按系统默认内部字段名直接对接。
    </div>

    <div class="row q-gutter-sm">
      <q-tabs v-model="tab" align="left" dense class="bg-grey-2 rounded-borders q-pa-xs" style="min-height:auto">
        <q-tab name="order" label="收单 order" />
        <q-tab name="verify" label="验证码 verify" />
      </q-tabs>
    </div>

    <q-list bordered separator class="q-mt-sm" v-if="section.rows.length > 0">
      <q-item v-for="(row, index) in section.rows" :key="index" dense>
        <q-item-section>
          <div class="row q-col-gutter-xs items-center">
            <div class="col-3">
              <q-select
                v-model="row.internal"
                :options="refFields"
                option-label="label"
                option-value="value"
                emit-value
                map-options
                use-input
                input-debounce="0"
                @filter="filterFields"
                label="内部字段"
                dense
                outlined
                placeholder="选择内部字段"
              />
            </div>
            <div class="col-1 text-center text-grey-5" style="font-size: 18px;">←</div>
            <div class="col-4">
              <q-input v-model="row.source" label="来源/表达式" dense outlined
                placeholder="外部字段名 或 data.a.b 或 值:固定值" />
            </div>
            <div class="col-2">
              <q-select v-model="row.convert" :options="converterOptions" label="转换" dense outlined emit-value map-options />
            </div>
            <div class="col-2">
              <q-input v-if="row.convert === 'datetime'" v-model="row.convertParam" label="格式" dense outlined
                placeholder="如: YYYYMMDDHHmmss" />
              <q-input v-else-if="row.convert === 'concat'" v-model="row.convertParam" label="拼接源(以|分隔)" dense outlined
                placeholder="如: areaCode|phone" @click.stop />
              <q-input v-else-if="row.convert === 'strip'" v-model="row.convertParam" label="去除字符" dense outlined
                placeholder="如: +86空格" />
              <q-input v-else-if="row.convert === 'enum'" v-model="row.convertParam" label="映射JSON" dense outlined
                placeholder='如: {"A":1}' class="text-caption" />
              <div v-else-if="!['int','numeric','bool'].includes(row.convert)" class="text-caption text-grey-5 q-pt-sm">—</div>
            </div>
          </div>
          <div class="row q-col-gutter-xs items-center q-mt-xs">
            <div class="col-3">
              <q-input v-model="row.defaultValue" label="默认值(可选)" dense outlined @update:model-value="normalizeRow(row)" />
            </div>
            <div class="col-2">
              <q-toggle v-model="row.ignore" label="仅取数不校验" dense left-label />
            </div>
            <div class="col-4 offset-3 text-right">
              <q-btn flat round dense color="negative" icon="delete" @click="removeRow(index)" />
            </div>
          </div>
        </q-item-section>
      </q-item>
    </q-list>

    <div v-else class="text-caption text-grey-6 q-py-md">
      暂无映射，可点击下方"添加映射"。（留空表示该入口按系统默认内部字段对接）
    </div>

    <div class="row q-gutter-sm items-center q-mt-sm">
      <q-btn flat dense color="primary" icon="add" label="添加映射" size="sm" @click="addRow" />
      <q-toggle v-model="section.raw" label="透传原始未映射字段(__raw__)" dense left-label />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { referenceFields, converterOptions } from 'src/composables/useReceiveMapping'

const props = defineProps({
  modelValue: { type: Object, required: true }, // { order, verify } UI 状态
})

const tab = ref('order')
const section = computed(() => props.modelValue[tab.value])

const refFields = ref(referenceFields)
const filterFields = (val, update) => {
  if (!val) return update(() => { refFields.value = referenceFields })
  const q = val.toLowerCase()
  update(() => { refFields.value = referenceFields.filter(f => f.label.toLowerCase().includes(q) || f.value.includes(q)) })
}

const addRow = () => {
  section.value.rows.push({ internal: '', source: '', convert: null, convertParam: '', defaultValue: null, ignore: false })
}
const removeRow = (index) => {
  section.value.rows.splice(index, 1)
}
const normalizeRow = () => {
  // 保持响应式；默认值字符串交由提取时归一
}
</script>